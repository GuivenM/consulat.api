<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ActivationCompteAdmin;
use App\Models\JournalActivite;
use App\Models\User;
use App\Support\CurrentEntity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UtilisateurController extends Controller
{
    /**
     * Rôles assignables : ceux de User::ROLES (super_admin, admin, agent).
     */
    private static function rolesValides(): array
    {
        return array_keys(User::ROLES);
    }

    /**
     * Liste de tous les comptes admin (super_admin uniquement — voir routes/api.php).
     */
    public function index()
    {
        try {
            $utilisateurs = User::with('entity:id,nom,nom_court')
                ->orderByRaw("FIELD(role, 'super_admin', 'admin', 'agent')")
                ->orderBy('nom')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $utilisateurs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs',
            ], 500);
        }
    }

    /**
     * Crée un compte admin/agent et envoie le lien d'activation : la
     * personne choisit elle-même son mot de passe (voir
     * AuthController::activerCompteAdmin). Le mot de passe stocké d'ici là
     * est aléatoire et personne ne le connaît — le compte est donc
     * inutilisable tant que le lien n'a pas été suivi.
     *
     * entity_id : un super_admin n'appartient à aucune entité ; un admin
     * ou un agent est rattaché à l'entité passée, à défaut à l'entité
     * courante (une seule en V1).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'email' => 'required|email|max:191|unique:users,email',
            'telephone' => 'nullable|string|max:30',
            'role' => ['required', Rule::in(self::rolesValides())],
            'entity_id' => 'nullable|integer|exists:entities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $role = $request->input('role');
        $entityId = $role === 'super_admin'
            ? null
            : ($request->input('entity_id') ?? CurrentEntity::id());

        $utilisateur = User::create([
            'entity_id' => $entityId,
            'nom' => $request->input('nom'),
            'prenom' => $request->input('prenom'),
            'email' => $request->input('email'),
            'telephone' => $request->input('telephone'),
            'role' => $role,
            'est_actif' => true,
            'password' => Hash::make(Str::random(40)),
            'activation_token' => Str::random(64),
            'activation_token_expire_at' => now()->addDays(7),
        ]);

        JournalActivite::enregistrer(
            'utilisateur.creer',
            "Compte {$utilisateur->role_label} créé : {$utilisateur->nom_complet} ({$utilisateur->email})",
            $utilisateur
        );

        // Le compte existe déjà : un échec d'envoi ne doit pas le faire
        // disparaître, le super_admin peut renvoyer le lien depuis la liste.
        try {
            Mail::to($utilisateur->email)->send(new ActivationCompteAdmin($utilisateur));
            $message = 'Compte créé. Email d\'activation envoyé à ' . $utilisateur->email;
        } catch (\Exception $e) {
            Log::error('Envoi email activation admin échoué: ' . $e->getMessage());
            $message = 'Compte créé, mais l\'email d\'activation n\'a pas pu être envoyé. Utilisez « Renvoyer l\'activation ».';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $utilisateur->load('entity:id,nom,nom_court'),
        ], 201);
    }

    /**
     * Modifier le rôle et/ou le statut actif d'un compte. On bloque volontairement
     * qu'un super_admin se désactive ou se rétrograde lui-même par erreur —
     * il faut qu'un autre super_admin s'en charge.
     */
    public function update(Request $request, $id)
    {
        try {
            $utilisateur = User::find($id);

            if (!$utilisateur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'role' => ['sometimes', Rule::in(self::rolesValides())],
                'est_actif' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $estSoiMeme = $request->user()->id === $utilisateur->id;

            if ($estSoiMeme && $request->has('est_actif') && !$request->boolean('est_actif')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas désactiver votre propre compte. Demandez à un autre super administrateur.',
                ], 422);
            }

            if ($estSoiMeme && $request->has('role') && $request->role !== 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas changer votre propre rôle. Demandez à un autre super administrateur.',
                ], 422);
            }

            $ancienRole = $utilisateur->role;
            $ancienStatut = $utilisateur->est_actif;

            $utilisateur->update($request->only(['role', 'est_actif']));

            if ($request->has('role') && $request->role !== $ancienRole) {
                JournalActivite::enregistrer(
                    'utilisateur.modifier_role',
                    "Rôle de {$utilisateur->nom_complet} changé : {$ancienRole} -> {$utilisateur->role}",
                    $utilisateur
                );
            }
            if ($request->has('est_actif') && $request->boolean('est_actif') !== $ancienStatut) {
                JournalActivite::enregistrer(
                    'utilisateur.changer_statut',
                    "Compte {$utilisateur->nom_complet} " . ($utilisateur->est_actif ? 'réactivé' : 'désactivé'),
                    $utilisateur
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour',
                'data' => $utilisateur->fresh('entity:id,nom,nom_court'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'utilisateur',
            ], 500);
        }
    }

    /**
     * Supprimer un compte admin. On ne peut pas se supprimer soi-même.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $utilisateur = User::find($id);

            if (!$utilisateur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable',
                ], 404);
            }

            if ($request->user()->id === $utilisateur->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
                ], 422);
            }

            $utilisateur->delete();

            JournalActivite::enregistrer(
                'utilisateur.supprimer',
                "Compte admin supprimé : {$utilisateur->nom_complet} ({$utilisateur->email})",
                null,
                ['user_id' => $utilisateur->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'utilisateur',
            ], 500);
        }
    }

    /**
     * Régénère le lien d'activation et renvoie l'email, pour un compte qui ne
     * s'est encore jamais activé (utile si l'email initial s'est perdu, ou
     * si le lien de 7 jours a expiré).
     */
    public function renvoyerActivation($id)
    {
        try {
            $utilisateur = User::find($id);

            if (!$utilisateur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable',
                ], 404);
            }

            if (is_null($utilisateur->activation_token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce compte est déjà activé.',
                ], 422);
            }

            $utilisateur->update([
                'activation_token' => Str::random(64),
                'activation_token_expire_at' => now()->addDays(7),
            ]);

            Mail::to($utilisateur->email)->send(new ActivationCompteAdmin($utilisateur));

            return response()->json([
                'success' => true,
                'message' => 'Email d\'activation renvoyé à ' . $utilisateur->email,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du renvoi de l\'email d\'activation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
