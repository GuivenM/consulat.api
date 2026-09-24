<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Connexion de l'utilisateur
     */
    /**
 * Connexion de l'utilisateur
 */
public function login(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:6',
                'remember' => 'boolean'
            ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Vérifier si l'utilisateur existe
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun compte trouvé avec cet email'
            ], 401);
        }

        // Vérifier si le compte est actif
        if (!$user->est_actif) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.'
            ], 403);
        }

        // Vérifier le mot de passe
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mot de passe incorrect'
            ], 401);
        }

        // Mettre à jour la dernière connexion - CORRECTION ICI
        $user->update(['derniere_connexion' => now()]);

        // Créer le token
        $token = $user->createToken('auth_token', ['*'], $request->remember ? now()->addDays(7) : now()->addDay())->plainTextToken;

        // Charger les permissions en fonction du rôle
        $permissions = $this->getPermissionsByRole($user->role);

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'nom' => $user->nom,
                    'prenom' => $user->prenom,
                    'nom_complet' => $user->nom_complet,
                    'email' => $user->email,
                    'role' => $user->role,
                    'role_label' => $user->role_label,
                    'photo' => $user->photo_url,
                    'initiales' => $user->initiales,
                    'telephone' => $user->telephone,
                    'derniere_connexion' => $user->derniere_connexion?->format('d/m/Y H:i')
                ],
                'token' => $token,
                'permissions' => $permissions
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la connexion',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Activation d'un accès admin/agent créé par un super_admin
     * (voir UtilisateurController::store) : l'intéressé choisit son
     * mot de passe via le lien reçu par email, puis est connecté directement.
     */
    public function activerCompteAdmin(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'password' => 'required|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('activation_token', $request->token)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lien d\'activation invalide'
                ], 404);
            }

            if (!$user->activation_token_expire_at || $user->activation_token_expire_at->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce lien d\'activation a expiré. Contactez un super administrateur pour en recevoir un nouveau.'
                ], 410);
            }

            $user->update([
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
                'activation_token' => null,
                'activation_token_expire_at' => null,
                'derniere_connexion' => now(),
            ]);

            $token = $user->createToken('auth_token', ['*'], now()->addDay())->plainTextToken;
            $permissions = $this->getPermissionsByRole($user->role);

            return response()->json([
                'success' => true,
                'message' => 'Accès activé avec succès',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'nom' => $user->nom,
                        'prenom' => $user->prenom,
                        'nom_complet' => $user->nom_complet,
                        'email' => $user->email,
                        'role' => $user->role,
                        'role_label' => $user->role_label,
                        'photo' => $user->photo_url,
                        'initiales' => $user->initiales,
                        'telephone' => $user->telephone,
                        'derniere_connexion' => $user->derniere_connexion?->format('d/m/Y H:i')
                    ],
                    'token' => $token,
                    'permissions' => $permissions
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'activation du compte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        try {
            // Révoquer le token actuel
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion'
            ], 500);
        }
    }

    /**
     * Déconnexion de tous les appareils
     */
    public function logoutAll(Request $request)
    {
        try {
            // Révoquer tous les tokens de l'utilisateur
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion de tous les appareils réussie'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion'
            ], 500);
        }
    }

    /**
     * Rafraîchir le token
     */
    public function refresh(Request $request)
    {
        try {
            $user = $request->user();
            
            // Révoquer l'ancien token
            $user->currentAccessToken()->delete();
            
            // Créer un nouveau token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rafraîchissement du token'
            ], 500);
        }
    }

    /**
     * Récupérer l'utilisateur connecté
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();
            
            $permissions = $this->getPermissionsByRole($user->role);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'nom' => $user->nom,
                        'prenom' => $user->prenom,
                        'nom_complet' => $user->nom_complet,
                        'email' => $user->email,
                        'role' => $user->role,
                        'role_label' => $user->role_label,
                        'photo' => $user->photo_url,
                        'initiales' => $user->initiales,
                        'telephone' => $user->telephone,
                        'derniere_connexion' => $user->derniere_connexion?->format('d/m/Y H:i')
                    ],
                    'permissions' => $permissions
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations'
            ], 500);
        }
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string|min:6',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Vérifier l'ancien mot de passe
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le mot de passe actuel est incorrect'
                ], 401);
            }

            // Mettre à jour le mot de passe
            $user->password = Hash::make($request->new_password);
            $user->save();

            // Optionnel: révoquer tous les tokens sauf le courant
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe'
            ], 500);
        }
    }

    /**
     * Ce que chaque rôle peut faire dans l'espace d'administration, par
     * module : `voir` (lecture) et `modifier` (création, édition,
     * traitement).
     *
     * Reflète les middlewares `role:` de routes/api.php et le menu du
     * frontend (AdminLayout). La vraie barrière reste le middleware côté
     * serveur : cette carte ne sert qu'à informer le frontend. À tenir
     * alignée si un groupe de routes change de rôles.
     */
    private function getPermissionsByRole($role)
    {
        $tous = ['super_admin', 'admin', 'agent'];
        $gestionnaires = ['super_admin', 'admin'];

        // module => [rôles qui voient, rôles qui modifient]
        $acces = [
            'dashboard' => [$tous, []],
            'demandes' => [$tous, $tous],
            'registre' => [$tous, []],
            'messages' => [$tous, $gestionnaires],
            'actualites' => [$tous, $gestionnaires],
            'guide' => [$tous, $gestionnaires],
            'partenaires' => [$tous, $gestionnaires],
            'configuration' => [$gestionnaires, $gestionnaires],
            'utilisateurs' => [['super_admin'], ['super_admin']],
            'journal' => [['super_admin'], []],
        ];

        return array_map(
            fn (array $roles) => [
                'voir' => in_array($role, $roles[0], true),
                'modifier' => in_array($role, $roles[1], true),
            ],
            $acces
        );
    }

    /**
     * Mot de passe oublié (espace admin) : envoie un lien de réinitialisation
     * si l'email correspond à un compte actif. Réponse volontairement
     * identique dans tous les cas (email inconnu, compte désactivé, ou
     * envoi réussi) pour ne pas révéler quels emails ont un compte.
     */
    public function motDePasseOublie(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $reponse = [
            'success' => true,
            'message' => "Si un compte existe avec cet email, un lien de réinitialisation vient d'être envoyé.",
        ];

        try {
            $user = User::where('email', $request->email)->where('est_actif', true)->first();

            if ($user) {
                $user->update([
                    'activation_token' => Str::random(64),
                    'activation_token_expire_at' => now()->addDays(7),
                ]);

                \Illuminate\Support\Facades\Mail::to($user->email)
                    ->send(new \App\Mail\ActivationCompteAdmin($user, reinitialisation: true));
            }
        } catch (\Exception $e) {
            \Log::error('Erreur envoi email réinitialisation admin: ' . $e->getMessage());
        }

        return response()->json($reponse);
    }
}