<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Models\Ressortissant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Support\Quartiers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Espace admin/agent : consultation du registre consulaire. L'inscription
 * elle-même reste en self-service (voir RessortissantAuthController) — cet
 * espace ne fait que lister/consulter, jamais créer un ressortissant à sa
 * place. Le scope entité (BelongsToEntity) s'applique automatiquement :
 * un admin ne voit jamais le registre d'une autre entité.
 */
class RessortissantAdminController extends Controller
{
    /**
     * Liste filtrable, pour le tableau de bord admin (onglet Registre).
     */
    public function index(Request $request)
    {
        $query = Ressortissant::query()->orderByDesc('created_at');

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }
        if ($request->filled('ville')) {
            $query->where('ville', $request->string('ville'));
        }
        if ($request->filled('quartier')) {
            $query->where('quartier', $request->string('quartier'));
        }
        if ($request->filled('recherche')) {
            $terme = $request->string('recherche');
            $query->where(function ($q) use ($terme) {
                $q->where('nom', 'like', "%{$terme}%")
                    ->orWhere('prenom', 'like', "%{$terme}%")
                    ->orWhere('numero_registre', 'like', "%{$terme}%")
                    ->orWhere('email', 'like', "%{$terme}%");
            });
        }

        $ressortissants = $query->paginate($request->integer('par_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $ressortissants->through(fn (Ressortissant $r) => $this->formater($r))->items(),
                'meta' => [
                    'total' => $ressortissants->total(),
                    'current_page' => $ressortissants->currentPage(),
                    'last_page' => $ressortissants->lastPage(),
                ],
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $ressortissant = Ressortissant::with(['demandes' => fn ($q) => $q->orderByDesc('date_depot')])
            ->find($id);

        if (!$ressortissant) {
            return response()->json([
                'success' => false,
                'message' => 'Ressortissant introuvable',
            ], 404);
        }

        $donnees = $this->formater($ressortissant, detaille: true);
        $donnees['demandes'] = $ressortissant->demandes->map(fn ($d) => [
            'id' => $d->id,
            'numero_dossier' => $d->numero_dossier,
            'type' => $d->type,
            'statut' => $d->statut,
            'statut_label' => $d->statut_label,
            'date_depot' => $d->date_depot?->format('d/m/Y H:i'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $donnees,
        ]);
    }

    /**
     * Corrige une fiche (état civil, coordonnées) et/ou change son statut.
     * Deux usages distincts, réunis pour éviter deux allers-retours quand
     * un agent corrige une fiche ET la réactive dans le même geste :
     * - correction : les champs fournis sont ceux d'un formulaire classique
     *   (le ressortissant reste seul maître de son inscription initiale,
     *   voir RessortissantAuthController ; ceci ne fait que corriger).
     * - statut : passer en `inactif`/`suspendu` exige un motif, tracé dans
     *   `motif_inactivation` et dans le journal. Revenir à `actif` l'efface.
     *
     * Ni le numéro de registre, ni l'email/mot de passe ne se changent ici
     * (voir RessortissantAuthController pour le compte du ressortissant).
     */
    public function update(Request $request, int $id)
    {
        $ressortissant = Ressortissant::find($id);

        if (!$ressortissant) {
            return response()->json([
                'success' => false,
                'message' => 'Ressortissant introuvable',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:100',
            'prenom' => 'sometimes|string|max:100',
            'sexe' => 'sometimes|nullable|in:M,F',
            'date_naissance' => 'sometimes|nullable|date',
            'lieu_naissance' => 'sometimes|nullable|string|max:150',
            'nationalite' => 'sometimes|nullable|string|max:100',
            'profession' => 'sometimes|nullable|string|max:150',
            'situation_matrimoniale' => 'sometimes|nullable|string|max:50',
            'type_piece' => ['sometimes', 'nullable', Rule::in(array_keys(Ressortissant::TYPES_PIECE))],
            'numero_piece' => 'sometimes|nullable|string|max:100',
            'possede_carte_consulaire' => 'sometimes|nullable|boolean',
            'numero_carte_consulaire' => 'sometimes|nullable|string|max:50',
            'date_expiration_piece' => 'sometimes|nullable|date',
            'telephone' => 'sometimes|nullable|string|max:30',
            'whatsapp' => 'sometimes|nullable|string|max:30',
            'ville' => 'sometimes|nullable|string|max:100',
            'quartier' => 'sometimes|nullable|string|max:150',
            'adresse' => 'sometimes|nullable|string|max:255',
            'contact_urgence_nom' => 'sometimes|nullable|string|max:150',
            'contact_urgence_telephone' => 'sometimes|nullable|string|max:30',
            'statut' => ['sometimes', Rule::in(['actif', 'inactif', 'suspendu'])],
            'motif_inactivation' => 'sometimes|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $donnees = $validator->validated();
        if (array_key_exists('quartier', $donnees)) {
            $donnees['quartier'] = Quartiers::normaliser($donnees['ville'] ?? $ressortissant->ville, $donnees['quartier']);
        }
        $changeStatut = array_key_exists('statut', $donnees) && $donnees['statut'] !== $ressortissant->statut;
        $ancienStatut = $ressortissant->statut;

        if ($changeStatut && $donnees['statut'] !== 'actif' && empty($donnees['motif_inactivation'])) {
            return response()->json([
                'success' => false,
                'errors' => ['motif_inactivation' => ['Un motif est requis pour passer ce compte en ' . $donnees['statut'] . '.']],
            ], 422);
        }

        // Revenir à actif efface le motif : sinon une réactivation garderait
        // affiché un motif de suspension qui ne s'applique plus.
        if ($changeStatut && $donnees['statut'] === 'actif') {
            $donnees['motif_inactivation'] = null;
        }

        $champsCorriges = array_diff(array_keys($donnees), ['statut', 'motif_inactivation']);

        $ressortissant->update($donnees);

        if ($changeStatut) {
            JournalActivite::enregistrer(
                'ressortissant.statut',
                "Statut de {$ressortissant->nom_complet} ({$ressortissant->numero_registre}) : {$ancienStatut} → {$donnees['statut']}"
                    . (!empty($donnees['motif_inactivation']) ? " — motif : {$donnees['motif_inactivation']}" : ''),
                $ressortissant
            );
        }

        if (!empty($champsCorriges)) {
            JournalActivite::enregistrer(
                'ressortissant.correction',
                "Fiche de {$ressortissant->nom_complet} ({$ressortissant->numero_registre}) corrigée : " . implode(', ', $champsCorriges),
                $ressortissant
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Fiche mise à jour.',
            'data' => $this->formater($ressortissant->fresh(), detaille: true),
        ]);
    }

    /**
     * Carte interactive — vue admin (tableau de bord interne) : agrégation
     * par quartier ET points individuels géolocalisés, contrairement à la
     * vue publique (voir CartePubliqueController) qui s'arrête à la ville
     * et n'expose aucune donnée personnelle.
     */
    public function carte()
    {
        $parQuartier = Ressortissant::whereNotNull('quartier')
            ->selectRaw('ville, quartier, count(*) as total')
            ->groupBy('ville', 'quartier')
            ->orderBy('ville')
            ->orderByDesc('total')
            ->get();

        $points = Ressortissant::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'nom', 'prenom', 'ville', 'quartier', 'statut', 'latitude', 'longitude'])
            ->map(fn (Ressortissant $r) => [
                'id' => $r->id,
                'nom_complet' => $r->nom_complet,
                'ville' => $r->ville,
                'quartier' => $r->quartier,
                'statut' => $r->statut,
                'latitude' => $r->latitude,
                'longitude' => $r->longitude,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'par_quartier' => $parQuartier,
                'points' => $points,
            ],
        ]);
    }

    /**
     * Chiffres clés pour le tableau de bord admin. Le détail par ville sert
     * aussi de socle pour la future carte interactive (agrégation par ville
     * côté vitrine publique).
     */
    public function statistiques()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total' => Ressortissant::count(),
                'par_statut' => Ressortissant::selectRaw('statut, count(*) as total')
                    ->groupBy('statut')
                    ->pluck('total', 'statut'),
                'par_ville' => Ressortissant::whereNotNull('ville')
                    ->selectRaw('ville, count(*) as total')
                    ->groupBy('ville')
                    ->orderByDesc('total')
                    ->pluck('total', 'ville'),
            ],
        ]);
    }

    /**
     * Affiche la pièce d'identité jointe à l'inscription (disque privé).
     *
     * GET /v1/admin/ressortissants/{id}/piece
     */
    public function piece(int $id)
    {
        $ressortissant = Ressortissant::find($id);

        if (!$ressortissant || !$ressortissant->piece_fichier
            || !Storage::disk('local')->exists($ressortissant->piece_fichier)) {
            return response()->json([
                'success' => false,
                'message' => 'Pièce introuvable',
            ], 404);
        }

        $nom = $ressortissant->piece_fichier_nom ?: basename($ressortissant->piece_fichier);

        return Storage::disk('local')->response(
            $ressortissant->piece_fichier,
            $nom,
            ['Content-Disposition' => 'inline; filename="' . addslashes($nom) . '"']
        );
    }

    /**
     * Export CSV du registre (espace admin), filtrable par statut.
     *
     * GET /v1/admin/ressortissants/export?statut=actif|inactif|suspendu
     */
    public function export(Request $request)
    {
        $query = Ressortissant::orderBy('nom');

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        $ressortissants = $query->get();

        return response()->streamDownload(function () use ($ressortissants) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'N° registre', 'Nom', 'Prénom', 'Sexe', 'Date de naissance', 'Nationalité',
                'Téléphone', 'WhatsApp', 'Email', 'Ville', 'Quartier', 'Type de pièce', 'N° pièce',
                'Carte consulaire', 'Statut', 'Inscrit le',
            ], ';');

            foreach ($ressortissants as $r) {
                fputcsv($handle, [
                    $r->numero_registre ?? '',
                    $r->nom,
                    $r->prenom,
                    $r->sexe ?? '',
                    $r->date_naissance?->format('d/m/Y') ?? '',
                    $r->nationalite ?? '',
                    $r->telephone ?? '',
                    $r->whatsapp ?? '',
                    $r->email ?? '',
                    $r->ville ?? '',
                    $r->quartier ?? '',
                    Ressortissant::TYPES_PIECE[$r->type_piece] ?? '',
                    $r->numero_piece ?? '',
                    is_null($r->possede_carte_consulaire) ? '' : ($r->possede_carte_consulaire ? 'Oui' : 'Non'),
                    $r->statut,
                    $r->created_at->format('d/m/Y'),
                ], ';');
            }

            fclose($handle);
        }, 'registre-consulaire.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function formater(Ressortissant $r, bool $detaille = false): array
    {
        $donnees = [
            'id' => $r->id,
            'numero_registre' => $r->numero_registre,
            'nom' => $r->nom,
            'prenom' => $r->prenom,
            'nom_complet' => $r->nom_complet,
            'sexe' => $r->sexe,
            'photo_url' => $r->photo_url,
            'telephone' => $r->telephone,
            'whatsapp' => $r->whatsapp,
            'email' => $r->email,
            'ville' => $r->ville,
            'quartier' => $r->quartier,
            'statut' => $r->statut,
            'inscription_verifiee' => $r->inscription_verifiee,
            'created_at' => $r->created_at->format('d/m/Y H:i'),
        ];

        if ($detaille) {
            $donnees += [
                'date_naissance' => $r->date_naissance?->format('d/m/Y'),
                'lieu_naissance' => $r->lieu_naissance,
                'nationalite' => $r->nationalite,
                'profession' => $r->profession,
                'situation_matrimoniale' => $r->situation_matrimoniale,
                'type_piece' => $r->type_piece,
                'numero_piece' => $r->numero_piece,
                'piece_fichier_disponible' => $r->piece_fichier_disponible,
                'piece_fichier_nom' => $r->piece_fichier_nom,
                'possede_carte_consulaire' => $r->possede_carte_consulaire,
                'numero_carte_consulaire' => $r->numero_carte_consulaire,
                'date_expiration_piece' => $r->date_expiration_piece?->format('d/m/Y'),
                'adresse' => $r->adresse,
                'latitude' => $r->latitude,
                'longitude' => $r->longitude,
                'date_arrivee' => $r->date_arrivee?->format('d/m/Y'),
                'contact_urgence_nom' => $r->contact_urgence_nom,
                'contact_urgence_telephone' => $r->contact_urgence_telephone,
                'motif_inactivation' => $r->motif_inactivation,
                'derniere_connexion' => $r->derniere_connexion?->format('d/m/Y H:i'),
            ];
        }

        return $donnees;
    }
}
