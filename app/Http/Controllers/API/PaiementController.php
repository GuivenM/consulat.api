<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Models\Paiement;
use App\Services\FedaPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaiementController extends Controller
{
    /**
     * Initie un paiement FedaPay pour un billet d'événement payant.
     *
     * POST /v1/paiements/evenements/{id}
     */
    public function initierEvenement(Request $request, $id, FedaPayService $fedapay)
    {
        $evenement = Evenement::findOrFail($id);

        if (!$evenement->prix || $evenement->prix <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cet événement est gratuit, aucun paiement requis.'
            ], 422);
        }

        if ($evenement->est_complet) {
            return response()->json([
                'success' => false,
                'message' => 'Cet événement affiche complet.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'membre_id' => 'nullable|exists:membres,id',
            'nom_payeur' => 'nullable|string|max:255',
            'telephone_payeur' => 'nullable|string|max:30',
            'email_payeur' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $membre = !empty($data['membre_id']) ? \App\Models\Membre::find($data['membre_id']) : null;
        $nomPayeur = $data['nom_payeur'] ?? $membre?->nom_complet;
        $telephonePayeur = $data['telephone_payeur'] ?? $membre?->whatsapp;
        $emailPayeur = $data['email_payeur'] ?? $membre?->email;

        $paiement = Paiement::create([
            'type' => 'evenement',
            'membre_id' => $data['membre_id'] ?? null,
            'evenement_id' => $evenement->id,
            'nom_payeur' => $nomPayeur,
            'telephone_payeur' => $telephonePayeur,
            'email_payeur' => $emailPayeur,
            'montant' => $evenement->prix,
            'devise' => $evenement->devise ?: 'XOF',
            'statut' => 'en_attente',
        ]);

        return $this->demarrerTransaction($fedapay, $paiement, "Billet - {$evenement->titre}", $nomPayeur, $telephonePayeur, $emailPayeur);
    }

    private function demarrerTransaction(
        FedaPayService $fedapay,
        Paiement $paiement,
        string $description,
        ?string $nomPayeur,
        ?string $telephonePayeur,
        ?string $emailPayeur
    ) {
        try {
            [$prenom, $nom] = $this->splitNom($nomPayeur);

            $customer = array_filter([
                'firstname' => $prenom,
                'lastname' => $nom,
                'email' => $emailPayeur,
                'phone_number' => $telephonePayeur ? [
                    'number' => $telephonePayeur,
                    'country' => 'bj',
                ] : null,
            ]);

            $resultat = $fedapay->initierTransaction((float) $paiement->montant, $description, $customer);

            $paiement->update([
                'fedapay_transaction_id' => $resultat['transaction_id'],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'paiement_id' => $paiement->id,
                    'checkout_url' => $resultat['checkout_url'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur initiation FedaPay', ['erreur' => $e->getMessage(), 'paiement_id' => $paiement->id]);

            $paiement->update(['statut' => 'echoue']);

            return response()->json([
                'success' => false,
                'message' => "Impossible de contacter FedaPay pour l'instant, réessayez dans un instant.",
            ], 502);
        }
    }

    private function splitNom(?string $nomComplet): array
    {
        if (!$nomComplet) {
            return [null, null];
        }
        $parts = preg_split('/\s+/', trim($nomComplet), 2);
        return [$parts[0] ?? null, $parts[1] ?? null];
    }

    /**
     * Webhook FedaPay : notification serveur-à-serveur à chaque changement
     * d'état d'une transaction. Route publique, protégée par la vérification
     * de signature (X-FEDAPAY-SIGNATURE) plutôt que par Sanctum.
     *
     * POST /paiements/webhook
     */
    public function webhook(Request $request, FedaPayService $fedapay)
    {
        $signature = $request->header('X-FEDAPAY-SIGNATURE', '');

        try {
            $event = $fedapay->verifierWebhook($request->getContent(), $signature);
        } catch (\Exception $e) {
            Log::warning('Webhook FedaPay rejeté : signature invalide', ['erreur' => $e->getMessage()]);
            return response()->json(['success' => false], 400);
        }

        $transactionId = $event->entity->id ?? null;
        $paiement = $transactionId ? Paiement::where('fedapay_transaction_id', $transactionId)->first() : null;

        if (!$paiement) {
            // Transaction inconnue de notre système (ou déjà traitée) : on
            // répond 200 quand même pour éviter les tentatives répétées de FedaPay.
            return response()->json(['success' => true]);
        }

        $paiement->fedapay_derniere_reponse = json_encode($event);

        switch ($event->name ?? null) {
            case 'transaction.approved':
                $paiement->statut = 'reussi';
                $paiement->save();
                $this->appliquerPaiementReussi($paiement);
                break;

            case 'transaction.declined':
                $paiement->statut = 'echoue';
                $paiement->save();
                break;

            case 'transaction.canceled':
                $paiement->statut = 'annule';
                $paiement->save();
                break;

            default:
                $paiement->save();
                break;
        }

        return response()->json(['success' => true]);
    }

    /**
     * Répercute un paiement confirmé sur les données métier : marque la
     * cotisation payée, ou incrémente le nombre d'inscrits à l'événement.
     */
    private function appliquerPaiementReussi(Paiement $paiement): void
    {
        if ($paiement->type === 'evenement' && $paiement->evenement_id) {
            Evenement::whereKey($paiement->evenement_id)->increment('nombre_inscrits');
        }
    }
}
