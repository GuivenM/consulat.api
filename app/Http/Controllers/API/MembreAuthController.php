<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Membre;
use App\Mail\ActivationCompteMembre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MembreAuthController extends Controller
{
    /**
     * Activation du compte : le membre définit son mot de passe via le
     * token reçu par email lors de la création de son accès.
     */
    public function activerCompte(Request $request)
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

            $membre = Membre::where('activation_token', $request->token)->first();

            if (!$membre) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lien d\'activation invalide'
                ], 404);
            }

            if (!$membre->activation_token_expire_at || $membre->activation_token_expire_at->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce lien d\'activation a expiré. Contactez l\'administration pour en recevoir un nouveau.'
                ], 410);
            }

            $membre->update([
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
                'activation_token' => null,
                'activation_token_expire_at' => null,
            ]);

            $token = $membre->createToken('membre_auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Compte activé avec succès',
                'data' => [
                    'membre' => $this->formaterMembre($membre),
                    'token' => $token,
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
     * Connexion du membre. Un membre "en_attente_paiement" peut se
     * connecter normalement — le frontend affiche un bandeau l'invitant
     * à régler sa cotisation, mais l'accès à l'espace membre n'est pas
     * bloqué.
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

            $membre = Membre::where('email', $request->email)->first();

            if (!$membre) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun compte trouvé avec cet email'
                ], 401);
            }

            if (!$membre->password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte n\'est pas encore activé. Consultez l\'email reçu lors de votre adhésion.'
                ], 403);
            }

            if ($membre->statut === 'inactif') {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administration.'
                ], 403);
            }

            if (!Hash::check($request->password, $membre->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe incorrect'
                ], 401);
            }

            $membre->update(['derniere_connexion' => now()]);

            $token = $membre->createToken(
                'membre_auth_token',
                ['*'],
                $request->remember ? now()->addDays(30) : now()->addDay()
            )->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => [
                    'membre' => $this->formaterMembre($membre),
                    'token' => $token,
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
     * Mot de passe oublié (espace membre) : envoie un lien de réinitialisation
     * si l'email correspond à un compte membre actif et déjà activé (sinon
     * c'est le lien d'activation initial qu'il faut utiliser). Réponse
     * volontairement identique dans tous les cas pour ne pas révéler quels
     * emails ont un compte.
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
            $membre = Membre::where('email', $request->email)
                ->where('statut', '!=', 'inactif')
                ->whereNotNull('password')
                ->first();

            if ($membre) {
                $membre->update([
                    'activation_token' => Str::random(64),
                    'activation_token_expire_at' => now()->addDays(7),
                ]);

                Mail::to($membre->email)->send(new ActivationCompteMembre($membre, reinitialisation: true));
            }
        } catch (\Exception $e) {
            \Log::error('Erreur envoi email réinitialisation membre: ' . $e->getMessage());
        }

        return response()->json($reponse);
    }

    public function logout(Request $request)
    {
        try {
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

    public function me(Request $request)
    {
        try {
            $membre = $request->user();

            return response()->json([
                'success' => true,
                'data' => [
                    'membre' => $this->formaterMembre($membre),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations'
            ], 500);
        }
    }

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

            $membre = $request->user();

            if (!Hash::check($request->current_password, $membre->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le mot de passe actuel est incorrect'
                ], 401);
            }

            $membre->password = Hash::make($request->new_password);
            $membre->save();

            $membre->tokens()->where('id', '!=', $membre->currentAccessToken()->id)->delete();

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
     * Champs exposés au frontend membre — volontairement plus restreint
     * que l'espace admin (pas de champs internes de gestion).
     */
    private function formaterMembre(Membre $membre): array
    {
        return [
            'id' => $membre->id,
            'nom' => $membre->nom,
            'prenom' => $membre->prenom,
            'nom_complet' => $membre->nom_complet,
            'email' => $membre->email,
            'photo' => $membre->photo_url,
            'poste' => $membre->poste,
            'commission' => $membre->commission,
            'role' => $membre->role,
            'statut' => $membre->statut,
            'en_attente_paiement' => $membre->statut === 'en_attente_paiement',
            'derniere_connexion' => $membre->derniere_connexion?->format('d/m/Y H:i'),
        ];
    }
}
