<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Models\Paiement;
use App\Services\FedaPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paiement en ligne (canal FedaPay). Le paiement au guichet reste dans
 * PaiementAdminController — les deux écrivent dans la même table
 * `paiements`, PaiementObserver recalcule `demandes.paiement_statut` dans
 * les deux cas de la même façon.
 *
 * Le webhook est la source de vérité (asynchrone, signé), mais on ne
 * force pas l'utilisateur à l'attendre : verifier() interroge FedaPay en
 * direct au retour du checkout pour donner un retour immédiat.
 */
class PaiementEnLigneController extends Controller
{
    public function __construct(private FedaPayService $fedaPay)
    {
    }

    /**
     * Crée une transaction FedaPay pour la demande et renvoie l'URL de
     * paiement vers laquelle le front doit rediriger le ressortissant.
     *
     * Simplification V1 : chaque appel crée une nouvelle tentative
     * (nouveau Paiement + nouvelle transaction FedaPay), y compris si une
     * tentative précédente est restée en_attente. Seule une tentative
     * réussie compte pour paiement_statut (voir PaiementObserver), donc
     * plusieurs tentatives en_attente/echoue en parallèle ne posent pas
     * de problème de cohérence — juste quelques lignes orphelines dans
     * `paiements`.
     */
    public function initier(Request $request, int $demandeId)
    {
        $demande = $request->user()->demandes()->find($demandeId);

        if (!$demande) {
            return response()->json(['success' => false, 'message' => 'Demande introuvable'], 404);
        }

        if ($demande->paiement_statut === 'paye') {
            return response()->json(['success' => false, 'message' => 'Cette demande est déjà payée.'], 422);
        }

        if (in_array($demande->statut, ['retire', 'rejete'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande est close, aucun paiement ne peut plus y être ajouté.',
            ], 422);
        }

        $ressortissant = $request->user();

        $paiement = Paiement::create([
            'entity_id' => $demande->entity_id,
            'demande_id' => $demande->id,
            'ressortissant_id' => $ressortissant->id,
            'nom_payeur' => trim($ressortissant->prenom . ' ' . $ressortissant->nom),
            'telephone_payeur' => $ressortissant->telephone ?? $ressortissant->whatsapp,
            'email_payeur' => $ressortissant->email,
            'montant' => $demande->montant,
            'devise' => $demande->devise,
            'statut' => 'en_attente',
            'canal' => 'en_ligne',
        ]);

        try {
            $resultat = $this->fedaPay->initierTransaction(
                (float) $demande->montant,
                "Dossier {$demande->numero_dossier} — {$demande->type}",
                [
                    'firstname' => $ressortissant->prenom,
                    'lastname' => $ressortissant->nom,
                    'email' => $ressortissant->email,
                    'phone_number' => [
                        'number' => $ressortissant->telephone ?? $ressortissant->whatsapp,
                        'country' => 'bj',
                    ],
                ]
            );
        } catch (\Exception $e) {
            Log::error('Erreur initialisation transaction FedaPay: ' . $e->getMessage());
            $paiement->update(['statut' => 'echoue']);

            return response()->json([
                'success' => false,
                'message' => "Impossible de contacter le service de paiement pour le moment.",
            ], 502);
        }

        $paiement->update(['fedapay_transaction_id' => $resultat['transaction_id']]);

        return response()->json([
            'success' => true,
            'data' => ['checkout_url' => $resultat['checkout_url']],
        ]);
    }

    /**
     * Appelé par la page de retour du front (FEDAPAY_CALLBACK_URL) juste
     * après le checkout, pour donner un statut immédiat sans attendre le
     * webhook. Ne fait confiance qu'à la réponse FedaPay elle-même, jamais
     * aux paramètres d'URL fournis par le navigateur.
     */
    public function verifier(Request $request, string $transactionId)
    {
        $paiement = Paiement::withoutGlobalScope('entity')
            ->where('fedapay_transaction_id', $transactionId)
            ->where('ressortissant_id', $request->user()->id)
            ->first();

        if (!$paiement) {
            return response()->json(['success' => false, 'message' => 'Paiement introuvable'], 404);
        }

        if ($paiement->statut === 'reussi') {
            return response()->json([
                'success' => true,
                'data' => ['statut' => 'reussi', 'demande_id' => $paiement->demande_id],
            ]);
        }

        try {
            $transaction = $this->fedaPay->recupererTransaction($transactionId);
        } catch (\Exception $e) {
            Log::error('Erreur vérification transaction FedaPay: ' . $e->getMessage());

            return response()->json([
                'success' => true,
                'data' => ['statut' => $paiement->statut, 'demande_id' => $paiement->demande_id],
            ]);
        }

        $this->appliquerStatutFedaPay($paiement, $transaction->status, json_encode($transaction));

        return response()->json([
            'success' => true,
            'data' => ['statut' => $paiement->fresh()->statut, 'demande_id' => $paiement->demande_id],
        ]);
    }

    /**
     * Webhook FedaPay (public, signé). Source de vérité en arrière-plan,
     * indépendante de ce que fait ou non le navigateur du ressortissant.
     */
    public function webhook(Request $request)
    {
        $signature = $request->header('X-FEDAPAY-SIGNATURE', '');

        try {
            $event = $this->fedaPay->verifierWebhook($request->getContent(), $signature);
        } catch (\Exception $e) {
            Log::warning('Webhook FedaPay rejeté (signature invalide) : ' . $e->getMessage());

            return response()->json(['success' => false], 400);
        }

        $transactionId = (string) ($event->entity->id ?? '');
        $paiement = Paiement::withoutGlobalScope('entity')
            ->where('fedapay_transaction_id', $transactionId)
            ->first();

        if (!$paiement) {
            // Transaction FedaPay qui ne correspond à aucun paiement connu
            // (autre intégration, tentative jamais enregistrée côté nous...).
            // On accuse quand même réception pour que FedaPay arrête de réessayer.
            return response()->json(['success' => true]);
        }

        $this->appliquerStatutFedaPay($paiement, $event->entity->status ?? $event->name, $request->getContent());

        return response()->json(['success' => true]);
    }

    private function appliquerStatutFedaPay(Paiement $paiement, string $statutFedaPay, string $reponseBrute): void
    {
        $statut = match ($statutFedaPay) {
            'approved' => 'reussi',
            'declined', 'canceled' => 'echoue',
            default => $paiement->statut, // transferred/pending... on ne change rien tant que ce n'est pas définitif
        };

        $attributs = [
            'statut' => $statut,
            'fedapay_derniere_reponse' => $reponseBrute,
        ];

        if ($statut === 'reussi' && !$paiement->date_encaissement) {
            $attributs['date_encaissement'] = now();
        }

        $paiement->update($attributs);

        if ($statut === 'reussi') {
            JournalActivite::enregistrer(
                'paiement.en_ligne',
                "Paiement FedaPay confirmé pour le dossier #{$paiement->demande_id}",
                $paiement,
                ['montant' => $paiement->montant]
            );
        }
    }
}
