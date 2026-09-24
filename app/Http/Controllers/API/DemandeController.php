<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\DemandeDocument;
use App\Models\DocumentTypeRequis;
use App\Models\Tarif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Espace membre : dépôt et suivi d'une demande (carte consulaire,
 * laissez-passer). Toute méthode ici part du principe qu'un Ressortissant
 * est authentifié (auth:sanctum, guard ressortissant) — l'entité courante
 * est donc déjà la sienne (voir CurrentEntity), et le scope global de
 * Demande/Tarif/DocumentTypeRequis fait le reste.
 *
 * Rien ici ne dépose de dossier "au nom" d'un autre ressortissant : toutes
 * les requêtes sont explicitement filtrées sur ressortissant_id en plus du
 * scope entité, par précaution — deux ressortissants de la même entité ne
 * doivent jamais pouvoir se lire l'un l'autre.
 */
class DemandeController extends Controller
{
    /**
     * Tarifs (par délai) et pièces à fournir pour un type de demande,
     * consommé par le front pour construire le formulaire de dépôt et ses
     * champs d'upload dynamiques.
     */
    public function configuration(Request $request, string $type)
    {
        $tarifs = Tarif::actif()->pourType($type)->orderBy('montant')->get();

        if ($tarifs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Ce type de demande n'est pas disponible actuellement.",
            ], 404);
        }

        $documents = DocumentTypeRequis::actif()->pourType($type)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $type,
                'tarifs' => $tarifs->map(fn (Tarif $t) => [
                    'delai' => $t->delai,
                    'delai_label' => Tarif::DELAIS[$t->delai] ?? $t->delai,
                    'montant' => $t->montant,
                    'devise' => $t->devise,
                ]),
                'documents_requis' => $documents->map(fn (DocumentTypeRequis $d) => [
                    'code_document' => $d->code_document,
                    'label' => $d->label,
                    'aide' => $d->aide,
                    'nombre_requis' => $d->nombre_requis,
                    'formats_acceptes' => $d->formats_acceptes_liste,
                    'taille_max_ko' => $d->taille_max_ko,
                    'obligatoire' => $d->obligatoire,
                ]),
            ],
        ]);
    }

    /**
     * Historique des demandes du ressortissant connecté.
     */
    public function index(Request $request)
    {
        $demandes = $request->user()->demandes()
            ->with('documents')
            ->orderByDesc('date_depot')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $demandes->map(fn (Demande $d) => $this->formaterDemande($d)),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $demande = $request->user()->demandes()->with('documents')->find($id);

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
     * Dépôt d'une nouvelle demande. Ne prend aucun fichier : les pièces
     * s'envoient ensuite une par une via uploadDocument(), une fois le
     * numéro de dossier connu — évite de perdre les fichiers déjà
     * sélectionnés si un champ texte du formulaire est invalide.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:50',
            'delai' => 'required|string|in:' . implode(',', array_keys(Tarif::DELAIS)),
            'donnees_specifiques' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $ressortissant = $request->user();

        if (!$ressortissant->numero_registre) {
            return response()->json([
                'success' => false,
                'message' => "Votre inscription au registre n'est pas encore finalisée.",
            ], 403);
        }

        $donnees = $validator->validated();

        try {
            $demande = Demande::creerPour(
                $ressortissant,
                $donnees['type'],
                $donnees['delai'],
                $donnees['donnees_specifiques'] ?? []
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Ce type de demande ou ce délai n'est pas configuré actuellement.",
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Erreur création demande: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du dépôt de la demande',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande enregistrée. Envoyez à présent les pièces requises.',
            'data' => $this->formaterDemande($demande, detaille: true),
        ], 201);
    }

    /**
     * Dépose une pièce pour une demande existante. Un même code_document
     * peut recevoir plusieurs fichiers jusqu'à `nombre_requis` (ex. 2
     * photos d'identité) ; renvoyer un fichier sur un code déjà rejeté
     * remplace la pièce rejetée plutôt que d'en accumuler une nouvelle.
     */
    public function uploadDocument(Request $request, int $demandeId)
    {
        $demande = $request->user()->demandes()->find($demandeId);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande introuvable',
            ], 404);
        }

        if (in_array($demande->statut, ['retire', 'rejete'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande est close, aucune pièce ne peut plus y être ajoutée.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'code_document' => 'required|string',
            'fichier' => 'required|file|max:20480', // 20 Mo, plafond dur ; la vraie limite vient de taille_max_ko ci-dessous
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $typeRequis = DocumentTypeRequis::actif()
            ->where('type_demande', $demande->type)
            ->where('code_document', $request->input('code_document'))
            ->first();

        if (!$typeRequis) {
            return response()->json([
                'success' => false,
                'message' => "Cette pièce n'est pas attendue pour ce type de demande.",
            ], 422);
        }

        $fichier = $request->file('fichier');
        $formatsAcceptes = $typeRequis->formats_acceptes_liste;
        $extension = strtolower($fichier->getClientOriginalExtension());

        if ($formatsAcceptes && !in_array($extension, $formatsAcceptes)) {
            return response()->json([
                'success' => false,
                'message' => "Format non accepté pour « {$typeRequis->label} ». Formats acceptés : " . implode(', ', $formatsAcceptes),
            ], 422);
        }

        if ($fichier->getSize() > $typeRequis->taille_max_ko * 1024) {
            return response()->json([
                'success' => false,
                'message' => "« {$typeRequis->label} » dépasse la taille maximale autorisée ({$typeRequis->taille_max_ko} Ko).",
            ], 422);
        }

        // Remplace en priorité un exemplaire déjà rejeté de ce même code, pour
        // ne pas faire cumuler des pièces invalides indéfiniment.
        $existant = $demande->documents()
            ->where('code_document', $typeRequis->code_document)
            ->where('statut', 'rejete')
            ->first();

        if (!$existant) {
            $nombreActuel = $demande->documents()
                ->where('code_document', $typeRequis->code_document)
                ->whereIn('statut', ['en_attente', 'valide'])
                ->count();

            if ($nombreActuel >= $typeRequis->nombre_requis) {
                return response()->json([
                    'success' => false,
                    'message' => "Le nombre maximum de fichiers pour « {$typeRequis->label} » est déjà atteint.",
                ], 422);
            }
        }

        $chemin = $fichier->store(
            "demandes/{$demande->entity_id}/{$demande->id}",
            'local'
        );

        if ($existant) {
            // L'ancien fichier physique n'est plus référencé par personne :
            // on le supprime pour ne pas accumuler de pièces orphelines sur
            // le disque privé.
            Storage::disk('local')->delete($existant->fichier);

            $existant->update([
                'fichier' => $chemin,
                'nom_original' => $fichier->getClientOriginalName(),
                'mime' => $fichier->getClientMimeType(),
                'taille' => $fichier->getSize(),
                'statut' => 'en_attente',
                'motif_rejet' => null,
                'verifie_par' => null,
                'verifie_at' => null,
            ]);
            $document = $existant;
        } else {
            $document = DemandeDocument::create([
                'demande_id' => $demande->id,
                'code_document' => $typeRequis->code_document,
                'label' => $typeRequis->label,
                'fichier' => $chemin,
                'nom_original' => $fichier->getClientOriginalName(),
                'mime' => $fichier->getClientMimeType(),
                'taille' => $fichier->getSize(),
                'statut' => 'en_attente',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pièce envoyée, en attente de vérification.',
            'data' => $this->formaterDocument($document),
        ], 201);
    }

    /**
     * Retire une pièce déposée par erreur, tant qu'elle n'a pas encore été
     * vérifiée — une fois validée ou rejetée, elle reste dans l'historique
     * du dossier.
     */
    public function supprimerDocument(Request $request, int $demandeId, int $documentId)
    {
        $demande = $request->user()->demandes()->find($demandeId);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande introuvable',
            ], 404);
        }

        $document = $demande->documents()->where('statut', 'en_attente')->find($documentId);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Pièce introuvable ou déjà vérifiée',
            ], 404);
        }

        Storage::disk('local')->delete($document->fichier);
        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pièce retirée',
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
            // Pièces refusées en attente d'un redépôt (le redépôt remet la pièce
            // en_attente, voir uploadDocument) : alimente l'alerte « action requise ».
            'pieces_a_corriger' => $demande->documents->where('statut', 'rejete')->count(),
            'date_depot' => $demande->date_depot?->format('d/m/Y H:i'),
            'date_disponibilite_prevue' => $demande->date_disponibilite_prevue?->format('d/m/Y'),
            'date_pret' => $demande->date_pret?->format('d/m/Y H:i'),
            'date_retrait' => $demande->date_retrait?->format('d/m/Y H:i'),
        ];

        if ($detaille) {
            $donnees['donnees_specifiques'] = $demande->donnees_specifiques;
            $donnees['documents'] = $demande->documents->map(fn (DemandeDocument $d) => $this->formaterDocument($d));
        }

        return $donnees;
    }

    private function formaterDocument(DemandeDocument $document): array
    {
        return [
            'id' => $document->id,
            'code_document' => $document->code_document,
            'label' => $document->label,
            'nom_original' => $document->nom_original,
            'statut' => $document->statut,
            'statut_label' => DemandeDocument::STATUTS[$document->statut] ?? $document->statut,
            'motif_rejet' => $document->motif_rejet,
            'created_at' => $document->created_at->format('d/m/Y H:i'),
        ];
    }
}
