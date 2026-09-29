<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\JournalActivite;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Encaissement au guichet. Le paiement en ligne (FedaPay) suivra dans un
 * contrôleur séparé — les deux canaux écrivent dans la même table
 * `paiements`, PaiementObserver recalcule `demandes.paiement_statut` dans
 * les deux cas de la même façon.
 *
 * Toujours le montant plein de la demande (pas de paiement partiel) : la
 * grille tarifaire est un forfait fixe par délai, pas un montant
 * négociable — voir Paiement::encaisserAuGuichet().
 */
class PaiementAdminController extends Controller
{
    /**
     * Détail d'un paiement pour le reçu imprimable. Le rendu (mise en page,
     * bouton Imprimer) est fait côté frontend avec window.print() : pas de
     * dépendance PDF côté serveur, et c'est la même route qui sert un
     * reçu du jour ou un ancien, tant que le paiement existe. Le scope
     * d'entité s'applique via Demande::find (BelongsToEntity).
     */
    public function show(int $id)
    {
        $paiement = Paiement::with(['demande', 'ressortissant', 'encaissePar:id,nom,prenom'])->find($id);

        if (!$paiement || !$paiement->demande) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement introuvable',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formaterRecu($paiement),
        ]);
    }

    /**
     * Récapitulatif de caisse pour une journée donnée (par défaut,
     * aujourd'hui) : liste des encaissements au guichet et total par mode
     * de paiement, pour la clôture de caisse d'un agent.
     *
     * Un agent ne voit que ses propres encaissements (accountabilité
     * individuelle) ; un admin/super_admin voit tout, avec un filtre
     * `agent_id` optionnel pour se concentrer sur un agent en particulier.
     */
    public function recapitulatif(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|date_format:Y-m-d',
            'agent_id' => 'sometimes|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $date = $request->input('date', now()->format('Y-m-d'));

        $query = Paiement::with(['demande:id,numero_dossier,type', 'ressortissant:id,nom,prenom', 'encaissePar:id,nom,prenom'])
            ->canal('guichet')
            ->reussi()
            ->whereDate('date_encaissement', $date)
            ->orderBy('date_encaissement');

        $user = $request->user();
        if ($user->role === 'agent') {
            $query->where('encaisse_par', $user->id);
        } elseif ($request->filled('agent_id')) {
            $query->where('encaisse_par', $request->integer('agent_id'));
        }

        $paiements = $query->get();

        $parMode = $paiements->groupBy('mode')->map(fn ($groupe) => [
            'nombre' => $groupe->count(),
            'total' => (float) $groupe->sum('montant'),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'paiements' => $paiements->map(fn (Paiement $p) => [
                    'id' => $p->id,
                    'heure' => $p->date_encaissement->format('H:i'),
                    'numero_recu' => $p->numero_recu,
                    'numero_dossier' => $p->demande->numero_dossier ?? '—',
                    'ressortissant' => $p->ressortissant?->nom_complet ?? $p->nom_payeur ?? '—',
                    'mode' => $p->mode,
                    'mode_label' => Paiement::MODES_GUICHET[$p->mode] ?? $p->mode,
                    'montant' => (float) $p->montant,
                    'agent' => $p->encaissePar ? trim("{$p->encaissePar->prenom} {$p->encaissePar->nom}") : '—',
                ]),
                'par_mode' => $parMode,
                'total' => (float) $paiements->sum('montant'),
                'devise' => $paiements->first()->devise ?? 'XOF',
            ],
        ]);
    }

    private function formaterRecu(Paiement $paiement): array
    {
        $demande = $paiement->demande;

        return [
            'id' => $paiement->id,
            'numero_recu' => $paiement->numero_recu,
            'date_encaissement' => $paiement->date_encaissement->format('d/m/Y à H:i'),
            'montant' => (float) $paiement->montant,
            'devise' => $paiement->devise,
            'mode' => $paiement->mode,
            'mode_label' => Paiement::MODES_GUICHET[$paiement->mode] ?? $paiement->mode,
            'nom_payeur' => $paiement->nom_payeur ?: ($paiement->ressortissant?->nom_complet ?? null),
            'telephone_payeur' => $paiement->telephone_payeur,
            'numero_dossier' => $demande->numero_dossier,
            'type_demande' => $demande->type,
            'agent' => $paiement->encaissePar ? trim("{$paiement->encaissePar->prenom} {$paiement->encaissePar->nom}") : null,
        ];
    }

    public function encaisser(Request $request, int $demandeId)
    {
        $validator = Validator::make($request->all(), [
            'mode' => 'required|in:' . implode(',', array_keys(Paiement::MODES_GUICHET)),
            'numero_recu' => 'required|string|max:100|unique:paiements,numero_recu',
            'nom_payeur' => 'nullable|string|max:150',
            'telephone_payeur' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $demande = Demande::find($demandeId);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande introuvable',
            ], 404);
        }

        if ($demande->paiement_statut === 'paye') {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande est déjà payée.',
            ], 422);
        }

        if (in_array($demande->statut, ['retire', 'rejete'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande est close, aucun paiement ne peut plus y être ajouté.',
            ], 422);
        }

        $paiement = Paiement::encaisserAuGuichet($demande, $validator->validated(), $request->user()->id);

        JournalActivite::enregistrer(
            'paiement.guichet',
            "Paiement guichet enregistré pour le dossier {$demande->numero_dossier} (reçu {$paiement->numero_recu})",
            $paiement,
            ['montant' => $paiement->montant, 'mode' => $paiement->mode]
        );

        return response()->json([
            'success' => true,
            'message' => 'Paiement enregistré.',
            'data' => [
                'id' => $paiement->id,
                'montant' => $paiement->montant,
                'devise' => $paiement->devise,
                'numero_recu' => $paiement->numero_recu,
                'paiement_statut' => $demande->fresh()->paiement_statut,
            ],
        ], 201);
    }
}
