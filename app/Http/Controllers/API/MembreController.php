<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Membre;
use App\Models\User;
use App\Mail\ActivationCompteAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\ImageCompressionService;

class MembreController extends Controller
{
    /**
     * Afficher tous les membres
     */
    public function index()
    {
        try {
            $membres = Membre::where('statut', 'actif')
                ->orderByRaw('CASE 
                    WHEN poste IS NOT NULL THEN 1 
                    WHEN commission IS NOT NULL THEN 2 
                    ELSE 3 
                END')
                ->orderBy('nom')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $membres
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des membres'
            ], 500);
        }
    }

    /**
     * Afficher les membres du bureau exécutif
     */
    public function bureau()
    {
        try {
            $bureau = Membre::whereNotNull('poste')
                ->where('statut', 'actif')
                ->orderByRaw("FIELD(poste, 
                    'Président', 
                    'Vice-Président', 
                    'Secrétaire Général', 
                    'Secrétaire Général Adjoint',
                    'Trésorier Général', 
                    'Trésorier Général Adjoint',
                    'Chargé de Communication',
                    'Chargé des Projets')")
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bureau
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du bureau'
            ], 500);
        }
    }

    /**
     * Afficher les membres d'une commission
     */
    public function commission($nom)
    {
        try {
            $membres = Membre::where('commission', $nom)
                ->where('statut', 'actif')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $membres
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des membres'
            ], 500);
        }
    }

    /**
     * Afficher TOUS les membres, actifs et inactifs (réservé à l'espace admin).
     * La route publique /membres ne renvoie que les membres actifs.
     */
    public function tous()
    {
        try {
            $membres = Membre::with('compteAdmin')
                ->when(request('ville'), fn($q, $ville) => $q->where('ville', $ville))
                ->orderByRaw('CASE
                    WHEN poste IS NOT NULL THEN 1
                    WHEN commission IS NOT NULL THEN 2
                    ELSE 3
                END')
                ->orderBy('nom')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $membres
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des membres'
            ], 500);
        }
    }

    /**
     * Export CSV de tous les membres (espace admin), filtrable par statut
     * pour rester cohérent avec les onglets affichés côté front.
     *
     * GET /v1/membres-admin/export?statut=tous|actif|en_attente_paiement|inactif
     */
    public function export(Request $request)
    {
        $statutFiltre = $request->query('statut', 'tous');

        $query = Membre::orderByRaw('CASE
                WHEN poste IS NOT NULL THEN 1
                WHEN commission IS NOT NULL THEN 2
                ELSE 3
            END')
            ->orderBy('nom');

        if (in_array($statutFiltre, ['actif', 'en_attente_paiement', 'inactif'])) {
            $query->where('statut', $statutFiltre);
        }

        $membres = $query->get();

        $statutLabels = [
            'actif' => 'Actif',
            'en_attente_paiement' => 'En attente de paiement',
            'inactif' => 'Inactif',
        ];

        return response()->streamDownload(function () use ($membres, $statutLabels) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Nom', 'Prénom', 'Poste', 'Commission', 'Statut', 'WhatsApp', 'Membre depuis'], ';');

            foreach ($membres as $m) {
                fputcsv($handle, [
                    $m->nom,
                    $m->prenom,
                    $m->poste ?? '',
                    $m->commission ?? '',
                    $statutLabels[$m->statut] ?? $m->statut,
                    $m->whatsapp ?? '',
                    $m->created_at->format('d/m/Y'),
                ], ';');
            }

            fclose($handle);
        }, 'membres.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Ajouter un nouveau membre
     */
    public function store(Request $request, ImageCompressionService $compressor)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'facebook' => 'nullable|url|max:255',
                'instagram' => 'nullable|url|max:255',
                'linkedin' => 'nullable|url|max:255',
                'twitter' => 'nullable|url|max:255',
                'whatsapp' => 'nullable|string|max:20',
                'ville' => 'nullable|in:' . implode(',', Membre::VILLES_BENIN),
                'poste' => 'nullable|string|max:255',
                'commission' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->except('photo');

            // Gérer l'upload de la photo (compressée + convertie en WebP)
            if ($request->hasFile('photo')) {
                $data['photo'] = $compressor->store($request->file('photo'), 'membres');
            }

            $membre = Membre::create($data);

            \App\Models\JournalActivite::enregistrer(
                'membre.ajouter',
                "Membre ajouté : {$membre->nom_complet}",
                $membre
            );

            return response()->json([
                'success' => true,
                'message' => 'Membre ajouté avec succès',
                'data' => $membre
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout du membre',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un membre spécifique
     */
    public function show($id)
    {
        try {
            $membre = Membre::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $membre
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Membre non trouvé'
            ], 404);
        }
    }

    /**
     * Mettre à jour un membre
     */
    public function update(Request $request, $id, ImageCompressionService $compressor)
    {
        try {
            $membre = Membre::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nom' => 'sometimes|string|max:255',
                'prenom' => 'sometimes|string|max:255',
                'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'facebook' => 'nullable|url|max:255',
                'instagram' => 'nullable|url|max:255',
                'linkedin' => 'nullable|url|max:255',
                'twitter' => 'nullable|url|max:255',
                'whatsapp' => 'nullable|string|max:20',
                'ville' => 'nullable|in:' . implode(',', Membre::VILLES_BENIN),
                'poste' => 'nullable|string|max:255',
                'commission' => 'nullable|string|max:255',
                'statut' => 'sometimes|in:actif,inactif'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->except('photo');

            // Gérer la nouvelle photo
            if ($request->hasFile('photo')) {
                // Supprimer l'ancienne photo
                if ($membre->photo) {
                    Storage::delete('public/' . $membre->photo);
                }
                
                $data['photo'] = $compressor->store($request->file('photo'), 'membres');
            }

            $membre->update($data);

            $champsModifies = array_keys($data);
            $statutAChange = array_key_exists('statut', $data);
            \App\Models\JournalActivite::enregistrer(
                'membre.modifier',
                $statutAChange
                    ? "Membre modifié : {$membre->nom_complet} (statut -> {$data['statut']})"
                    : "Membre modifié : {$membre->nom_complet}",
                $membre,
                ['champs' => $champsModifies]
            );

            return response()->json([
                'success' => true,
                'message' => 'Membre mis à jour avec succès',
                'data' => $membre
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un membre
     */
    public function destroy($id)
    {
        try {
            $membre = Membre::findOrFail($id);

            // Supprimer la photo
            if ($membre->photo) {
                Storage::delete('public/' . $membre->photo);
            }

            $nomComplet = $membre->nom_complet;
            $membreId = $membre->id;
            $membre->delete();

            \App\Models\JournalActivite::enregistrer(
                'membre.supprimer',
                "Membre supprimé : {$nomComplet}",
                null,
                ['membre_id' => $membreId]
            );

            return response()->json([
                'success' => true,
                'message' => 'Membre supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste des commissions disponibles
     */
    public function commissions()
    {
        $commissions = [
            'Solidarité et Intégration',
            'Éducation et Formation',
            'Culture et Identité',
            'Communication et Partenariats',
            'Projets et Développement',
            'Finances',
            'Organisation Événements'
        ];

        return response()->json([
            'success' => true,
            'data' => $commissions
        ]);
    }

    /**
     * Postes du bureau exécutif
     */
    public function postesBureau()
    {
        $postes = [
            'Président',
            'Vice-Président',
            'Secrétaire Général',
            'Secrétaire Général Adjoint',
            'Trésorier Général',
            'Trésorier Général Adjoint',
            'Chargé de Communication',
            'Chargé des Projets',
            'Chargé des Relations Extérieures',
            'Conseiller'
        ];

        return response()->json([
            'success' => true,
            'data' => $postes
        ]);
    }

    /**
     * Les 77 communes du Bénin, pour le select "Ville" du formulaire membre
     * (et le filtre de la page Membres).
     */
    public function villes()
    {
        return response()->json([
            'success' => true,
            'data' => Membre::VILLES_BENIN,
        ]);
    }

    /**
     * Crée un accès admin pour un membre du bureau, à partir de son poste
     * (voir Membre::POSTES_ADMIN_ELIGIBLES pour la correspondance poste -> rôle).
     * Envoie un email d'activation ; le mot de passe est choisi par
     * l'intéressé via ce lien, jamais généré/transmis ici.
     */
    public function creerAccesAdmin($id)
    {
        try {
            $membre = Membre::with('compteAdmin')->find($id);

            if (!$membre) {
                return response()->json([
                    'success' => false,
                    'message' => 'Membre introuvable'
                ], 404);
            }

            if (!$membre->peut_avoir_acces_admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce poste ne permet pas de créer un accès administrateur'
                ], 422);
            }

            if ($membre->compteAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce membre a déjà un accès administrateur'
                ], 422);
            }

            if (!$membre->email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce membre n\'a pas d\'adresse email renseignée'
                ], 422);
            }

            if (User::where('email', $membre->email)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Un compte admin existe déjà avec cet email'
                ], 422);
            }

            $role = Membre::POSTES_ADMIN_ELIGIBLES[$membre->poste];

            $user = User::create([
                'membre_id' => $membre->id,
                'nom' => $membre->nom,
                'prenom' => $membre->prenom,
                'email' => $membre->email,
                'telephone' => $membre->whatsapp,
                'role' => $role,
                // Mot de passe temporaire inutilisable : personne ne le connaît,
                // le compte n'est utilisable qu'après activation via le lien reçu par email.
                'password' => Hash::make(Str::random(40)),
                'est_actif' => true,
                'activation_token' => Str::random(64),
                'activation_token_expire_at' => now()->addDays(7),
            ]);

            try {
                Mail::to($user->email)->send(new ActivationCompteAdmin($user));
            } catch (\Exception $e) {
                \Log::error('Erreur envoi email activation admin: ' . $e->getMessage());
            }

            \App\Models\JournalActivite::enregistrer(
                'membre.creer_acces_admin',
                "Accès admin créé pour {$membre->nom_complet} (rôle : {$user->role_label})",
                $user
            );

            return response()->json([
                'success' => true,
                'message' => 'Accès créé, email d\'activation envoyé à ' . $user->email,
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                    'role_label' => $user->role_label,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'accès administrateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}