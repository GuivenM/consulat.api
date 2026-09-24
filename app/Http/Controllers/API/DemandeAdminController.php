<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\DemandeDocument;
use App\Models\JournalActivite;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Espace admin/agent : traitement des demandes déposées par les
 * ressortissants. L'entité courante suit l'utilisateur connecté (voir
 * CurrentEntity) — un admin ne voit jamais les demandes d'une autre
 * entité, même en devinant un ID.
 */
class DemandeAdminController extends Controller
{
    /**
     * Liste filtrable, pour le tableau de bord admin.
     */
    public function index(Request $request)
    {
        $query = Demande::with(['ressortissant:id,nom,prenom,numero_registre,ville,quartier'])
            ->orderByDesc('date_depot');

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('paiement_statut')) {
            $query->where('paiement_statut', $request->string('paiement_statut'));
        }
        if ($request->filled('recherche')) {
            $terme = $request->string('recherche');
            $query->where(function ($q) use ($terme) {
                $q->where('numero_dossier', 'like', "%{$terme}%")
                    ->orWhereHas('ressortissant', function ($rq) use ($terme) {
                        $rq->where('nom', 'like', "%{$terme}%")
                            ->orWhere('prenom', 'like', "%{$terme}%")
                            ->orWhere('numero_registre', 'like', "%{$terme}%");
                    });
            });
        }

        $demandes = $query->paginate($request->integer('par_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $demandes->through(fn (Demande $d) => $this->formaterDemande($d))->items(),
                'meta' => [
                    'total' => $demandes->total(),
                    'current_page' => $demandes->currentPage(),
                    'last_page' => $demandes->lastPage(),
                ],
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $demande = Demande::with(['ressortissant', 'documents.verifiePar', 'paiements', 'traitePar'])
            ->find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande introuvable',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formaterDemande($demande, detaille: true),
        ]);
    }

    /**
     * Valide ou rejette une pièce individuellement — ne touche pas au
     * statut global de la demande, que l'admin fait progresser lui-même
     * une fois qu'il juge le dossier prêt (voir changerStatut()).
     */
    /**
     * Sert le fichier d'une pièce pour affichage (photo, PDF) dans
     * l'espace admin. Le disque `local` est privé — cet endpoint, derrière
     * auth:sanctum + whereHas('demande') (même garde-fou de scope entité
     * que verifierDocument), est la seule façon d'y accéder.
     */
    public function telechargerDocument(Request $request, int $documentId)
    {
        $document = DemandeDocument::whereHas('demande')->find($documentId);

        if (!$document || !Storage::disk('local')->exists($document->fichier)) {
            return response()->json([
                'success' => false,
                'message' => 'Pièce introuvable',
            ], 404);
        }

        return Storage::disk('local')->response(
            $document->fichier,
            $document->nom_original,
            ['Content-Disposition' => 'inline; filename="' . $document->nom_original . '"']
        );
    }

    public function verifierDocument(Request $request, int $documentId)
    {
        $validator = Validator::make($request->all(), [
            'statut' => 'required|in:valide,rejete',
            'motif_rejet' => 'required_if:statut,rejete|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // whereHas('demande') n'est pas un filtre décoratif : DemandeDocument
        // n'a pas de scope entité propre, il hérite de celui de sa Demande.
        // La sous-requête générée par whereHas() interroge Demande::query(),
        // qui applique automatiquement le scope global 'entity' — c'est ce
        // qui empêche un agent de vérifier la pièce d'une autre entité en
        // devinant un ID.
        $document = DemandeDocument::whereHas('demande')->find($documentId);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Pièce introuvable',
            ], 404);
        }

        $document->verifier(
            $request->input('statut'),
            $request->user()->id,
            $request->input('motif_rejet')
        );

        JournalActivite::enregistrer(
            'demande_document.' . $request->input('statut'),
            "Pièce « {$document->label} » " . ($request->input('statut') === 'valide' ? 'validée' : 'rejetée')
                . " sur le dossier {$document->demande->numero_dossier}",
            $document
        );

        return response()->json([
            'success' => true,
            'message' => 'Pièce mise à jour',
            'data' => [
                'id' => $document->id,
                'statut' => $document->statut,
                'motif_rejet' => $document->motif_rejet,
            ],
        ]);
    }

    /**
     * Valide d'un coup toutes les pièces encore « en attente » d'un
     * dossier. Les pièces déjà rejetées ne sont pas touchées : un rejet est
     * une décision de l'agent, pas un état à écraser en lot. Une seule
     * entrée de journal pour l'ensemble, plutôt qu'une par pièce.
     */
    public function validerToutesPieces(Request $request, int $demandeId)
    {
        // Demande::find applique le scope d'entité : un agent ne peut pas
        // valider les pièces d'une autre entité en devinant un ID.
        $demande = Demande::find($demandeId);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande introuvable',
            ], 404);
        }

        if (in_array($demande->statut, ['retire', 'rejete'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Ce dossier est clos, ses pièces ne peuvent plus être modifiées.',
            ], 422);
        }

        $documents = $demande->documents()->where('statut', 'en_attente')->get();

        DB::transaction(function () use ($documents, $request) {
            foreach ($documents as $document) {
                $document->verifier('valide', $request->user()->id);
            }
        });

        if ($documents->isNotEmpty()) {
            JournalActivite::enregistrer(
                'demande_document.valide',
                "{$documents->count()} pièce(s) validée(s) en lot sur le dossier {$demande->numero_dossier}",
                $demande
            );
        }

        return response()->json([
            'success' => true,
            'message' => $documents->isEmpty() ? 'Aucune pièce en attente' : 'Pièces validées',
            'data' => ['validees' => $documents->count()],
        ]);
    }

    /**
     * Fait progresser une demande, ou la rejette. Les préconditions sont
     * vérifiées ici (pas dans le modèle) pour renvoyer des messages
     * explicites au front plutôt qu'une exception générique.
     */
    public function changerStatut(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'statut' => 'required|in:' . implode(',', [...Demande::PROGRESSION, 'rejete']),
            'motif_rejet' => 'required_if:statut,rejete|nullable|string|max:500',
            'note_interne' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $demande = Demande::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande introuvable',
            ], 404);
        }

        $nouveauStatut = $request->input('statut');

        if (in_array($demande->statut, ['retire', 'rejete'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande est close, son statut ne peut plus changer.',
            ], 422);
        }

        if ($nouveauStatut !== 'rejete') {
            $positionActuelle = array_search($demande->statut, Demande::PROGRESSION);
            $positionCible = array_search($nouveauStatut, Demande::PROGRESSION);

            if ($positionCible === false || $positionCible !== $positionActuelle + 1) {
                return response()->json([
                    'success' => false,
                    'message' => "Impossible de passer directement de « {$demande->statut_label} » à ce statut.",
                ], 422);
            }
        }

        if ($nouveauStatut === 'pret') {
            if (!$demande->documents_complets) {
                return response()->json([
                    'success' => false,
                    'message' => 'Toutes les pièces obligatoires doivent être validées avant de marquer le dossier prêt.',
                ], 422);
            }
            if ($demande->paiement_statut !== 'paye') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le paiement doit être complet avant de marquer le dossier prêt.',
                ], 422);
            }
        }

        $demande->traite_par = $request->user()->id;
        if ($request->filled('note_interne')) {
            $demande->note_interne = $request->input('note_interne');
        }
        $demande->save();

        $demande->changerStatut($nouveauStatut, $request->input('motif_rejet'));

        JournalActivite::enregistrer(
            'demande.changer_statut',
            "Dossier {$demande->numero_dossier} : statut changé vers « {$demande->statut_label} »",
            $demande,
            ['statut' => $nouveauStatut]
        );

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour',
            'data' => $this->formaterDemande($demande->fresh(), detaille: true),
        ]);
    }

    private function formaterDemande(Demande $demande, bool $detaille = false): array
    {
        $donnees = [
            'id' => $demande->id,
            'numero_dossier' => $demande->numero_dossier,
            'type' => $demande->type,
            'delai' => $demande->delai,
            'delai_label' => $demande->delai_label,
            'statut' => $demande->statut,
            'statut_label' => $demande->statut_label,
            'motif_rejet' => $demande->motif_rejet,
            'montant' => $demande->montant,
            'devise' => $demande->devise,
            'paiement_statut' => $demande->paiement_statut,
            'documents_complets' => $demande->documents_complets,
            'date_depot' => $demande->date_depot?->format('d/m/Y H:i'),
            'date_disponibilite_prevue' => $demande->date_disponibilite_prevue?->format('d/m/Y'),
            'date_pret' => $demande->date_pret?->format('d/m/Y H:i'),
            'date_retrait' => $demande->date_retrait?->format('d/m/Y H:i'),
            'ressortissant' => $demande->relationLoaded('ressortissant') && $demande->ressortissant ? [
                'id' => $demande->ressortissant->id,
                'nom_complet' => $demande->ressortissant->nom_complet,
                'numero_registre' => $demande->ressortissant->numero_registre,
                'ville' => $demande->ressortissant->ville,
                'quartier' => $demande->ressortissant->quartier,
            ] : null,
        ];

        if ($detaille) {
            $donnees['donnees_specifiques'] = $demande->donnees_specifiques;
            $donnees['note_interne'] = $demande->note_interne;
            $donnees['traite_par'] = $demande->traitePar?->nom_complet;
            $donnees['documents'] = $demande->documents->map(fn (DemandeDocument $d) => [
                'id' => $d->id,
                'code_document' => $d->code_document,
                'label' => $d->label,
                'nom_original' => $d->nom_original,
                'mime' => $d->mime,
                'fichier_url' => "/v1/admin/demandes/documents/{$d->id}/fichier",
                'statut' => $d->statut,
                'statut_label' => DemandeDocument::STATUTS[$d->statut] ?? $d->statut,
                'motif_rejet' => $d->motif_rejet,
                'verifie_par' => $d->verifiePar?->nom_complet,
                'verifie_at' => $d->verifie_at?->format('d/m/Y H:i'),
                'created_at' => $d->created_at->format('d/m/Y H:i'),
            ]);
            $donnees['paiements'] = $demande->paiements->map(fn (Paiement $p) => [
                'id' => $p->id,
                'montant' => $p->montant,
                'devise' => $p->devise,
                'statut' => $p->statut,
                'canal' => $p->canal,
                'canal_label' => Paiement::CANAUX[$p->canal] ?? $p->canal,
                'mode' => $p->mode,
                'mode_label' => $p->mode ? (Paiement::MODES_GUICHET[$p->mode] ?? $p->mode) : null,
                'numero_recu' => $p->numero_recu,
                'date_encaissement' => $p->date_encaissement?->format('d/m/Y H:i'),
                'created_at' => $p->created_at->format('d/m/Y H:i'),
            ]);
        }

        return $donnees;
    }
}
