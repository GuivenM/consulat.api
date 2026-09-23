<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\VerificationEmailRessortissant;
use App\Models\Ressortissant;
use App\Support\CurrentEntity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Auth de l'espace membre (ressortissants). Contrairement à AJDCB où le
 * compte était créé par l'admin après approbation d'une adhésion,
 * l'inscription ici est en self-service : le ressortissant choisit son
 * mot de passe dès le formulaire, le compte existe immédiatement mais
 * reste inutilisable (login refusé) tant que l'email n'est pas vérifié.
 *
 * `activation_token` est réutilisé pour deux usages jamais simultanés sur
 * un même compte : vérification d'email à l'inscription, et
 * réinitialisation de mot de passe (voir VerificationEmailRessortissant).
 */
class RessortissantAuthController extends Controller
{
    /**
     * Inscription au registre consulaire. Ne connecte pas automatiquement
     * (email non vérifié à ce stade) — voir verifierEmail().
     */
    public function inscrire(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'sexe' => 'nullable|in:M,F',
            'date_naissance' => 'nullable|date|before:today',
            'lieu_naissance' => 'nullable|string|max:150',
            'nationalite' => 'nullable|string|max:100',
            'profession' => 'nullable|string|max:150',
            'situation_matrimoniale' => 'nullable|in:celibataire,marie,divorce,veuf',

            'type_piece' => 'nullable|in:passeport,cni,carte_consulaire,autre',
            'numero_piece' => 'nullable|string|max:50',
            'date_expiration_piece' => 'nullable|date|after:today',

            'whatsapp' => 'nullable|string|max:30',
            'telephone' => 'nullable|string|max:30',

            'ville' => ['required', 'string', 'in:' . implode(',', Ressortissant::VILLES_BENIN)],
            'quartier' => 'required|string|max:150',
            'adresse' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'date_arrivee' => 'nullable|date|before_or_equal:today',

            'contact_urgence_nom' => 'nullable|string|max:150',
            'contact_urgence_telephone' => 'nullable|string|max:30',

            'email' => 'required|email|max:190|unique:ressortissants,email',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'ville.in' => "La ville doit correspondre à l'une des communes du Bénin.",
            'required' => 'Le champ :attribute est obligatoire.',
            'string' => 'Le champ :attribute doit être une chaîne de caractères.',
            'email' => "L'adresse email n'est pas valide.",
            'email.unique' => 'Un compte existe déjà avec cet email.',
            'max.string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
            'date' => 'Le champ :attribute doit être une date valide.',
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'date_expiration_piece.after' => "La date d'expiration de la pièce doit être postérieure à aujourd'hui.",
            'date_arrivee.before_or_equal' => "La date d'arrivée ne peut pas être dans le futur.",
            'numeric' => 'Le champ :attribute doit être un nombre.',
            'between' => 'Le champ :attribute doit être compris entre :min et :max.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ], [
            'nom' => 'nom',
            'prenom' => 'prénom',
            'date_naissance' => 'date de naissance',
            'lieu_naissance' => 'lieu de naissance',
            'type_piece' => 'type de pièce',
            'numero_piece' => 'numéro de pièce',
            'date_expiration_piece' => "date d'expiration de la pièce",
            'whatsapp' => 'numéro WhatsApp',
            'telephone' => 'téléphone',
            'quartier' => 'quartier',
            'adresse' => 'adresse',
            'date_arrivee' => "date d'arrivée",
            'contact_urgence_nom' => "nom du contact d'urgence",
            'contact_urgence_telephone' => "téléphone du contact d'urgence",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $entity = CurrentEntity::resolve();
        $donnees = $validator->validated();
        $donnees['nationalite'] = $donnees['nationalite'] ?? $entity->pays_represente;
        $donnees['password'] = Hash::make($donnees['password']);
        $donnees['statut'] = 'actif';
        $donnees['activation_token'] = Str::random(64);
        $donnees['activation_token_expire_at'] = now()->addDays(7);

        try {
            $ressortissant = Ressortissant::create($donnees);
        } catch (\Exception $e) {
            \Log::error('Erreur création ressortissant: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => "Erreur lors de l'inscription",
            ], 500);
        }

        // Envoi du mail isolé : le compte est déjà créé à ce stade, donc un
        // problème SMTP ne doit jamais se traduire par un "échec
        // d'inscription" côté ressortissant (il aurait alors un compte
        // fantôme, invisible pour lui, et ne pourrait plus réessayer car
        // son email serait déjà pris).
        $mailEnvoye = true;
        try {
            Mail::to($ressortissant->email)->send(new VerificationEmailRessortissant($ressortissant));
        } catch (\Exception $e) {
            $mailEnvoye = false;
            \Log::error('Erreur envoi email vérification inscription: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => $mailEnvoye
                ? 'Inscription enregistrée. Un email de vérification vous a été envoyé.'
                : "Inscription enregistrée, mais l'email de vérification n'a pas pu être envoyé. Utilisez \"Renvoyer l'email\" sur la page de connexion.",
        ], 201);
    }

    /**
     * Vérifie l'email via le token reçu, attribue le numéro de registre
     * et connecte immédiatement (évite un aller-retour login juste après).
     */
    public function verifierEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $ressortissant = Ressortissant::withoutGlobalScope('entity')
            ->where('activation_token', $request->token)
            ->first();

        if (!$ressortissant) {
            return response()->json([
                'success' => false,
                'message' => 'Lien de vérification invalide',
            ], 404);
        }

        if ($ressortissant->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Cet email a déjà été vérifié. Vous pouvez vous connecter.',
            ], 409);
        }

        if (!$ressortissant->activation_token_expire_at || $ressortissant->activation_token_expire_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce lien a expiré. Demandez un nouvel envoi.',
            ], 410);
        }

        $ressortissant->email_verified_at = now();
        $ressortissant->activation_token = null;
        $ressortissant->activation_token_expire_at = null;
        $ressortissant->save();

        $ressortissant->attribuerNumeroRegistre();

        $token = $ressortissant->createToken('ressortissant_auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Email vérifié avec succès',
            'data' => [
                'ressortissant' => $this->formaterRessortissant($ressortissant),
                'token' => $token,
            ],
        ]);
    }

    /**
     * Renvoie un email de vérification si le compte existe et n'est pas
     * déjà vérifié. Réponse volontairement identique dans tous les cas
     * pour ne pas révéler quels emails sont enregistrés.
     */
    public function renvoyerVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $reponse = [
            'success' => true,
            'message' => "Si un compte non vérifié existe avec cet email, un nouveau lien vient d'être envoyé.",
        ];

        try {
            $ressortissant = Ressortissant::where('email', $request->email)
                ->whereNull('email_verified_at')
                ->first();

            if ($ressortissant) {
                $ressortissant->update([
                    'activation_token' => Str::random(64),
                    'activation_token_expire_at' => now()->addDays(7),
                ]);

                Mail::to($ressortissant->email)->send(new VerificationEmailRessortissant($ressortissant));
            }
        } catch (\Exception $e) {
            \Log::error('Erreur renvoi vérification ressortissant: ' . $e->getMessage());
        }

        return response()->json($reponse);
    }

    /**
     * Connexion. Bloquée tant que l'email n'est pas vérifié — message
     * explicite plutôt qu'un simple 401, pour que le frontend puisse
     * proposer directement le renvoi de l'email de vérification.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $ressortissant = Ressortissant::where('email', $request->email)->first();

        if (!$ressortissant || !Hash::check($request->password, $ressortissant->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect',
            ], 401);
        }

        if (!$ressortissant->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Vérifiez votre email avant de vous connecter.',
                'email_non_verifie' => true,
            ], 403);
        }

        if ($ressortissant->statut === 'suspendu') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte a été suspendu. Veuillez contacter le consulat.',
            ], 403);
        }

        $ressortissant->update(['derniere_connexion' => now()]);

        $token = $ressortissant->createToken(
            'ressortissant_auth_token',
            ['*'],
            $request->boolean('remember') ? now()->addDays(30) : now()->addDay()
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'ressortissant' => $this->formaterRessortissant($ressortissant),
                'token' => $token,
            ],
        ]);
    }

    /**
     * Mot de passe oublié. Réponse volontairement identique dans tous les
     * cas pour ne pas révéler quels emails ont un compte.
     */
    public function motDePasseOublie(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $reponse = [
            'success' => true,
            'message' => "Si un compte vérifié existe avec cet email, un lien de réinitialisation vient d'être envoyé.",
        ];

        try {
            $ressortissant = Ressortissant::where('email', $request->email)
                ->whereNotNull('email_verified_at')
                ->where('statut', '!=', 'suspendu')
                ->first();

            if ($ressortissant) {
                $ressortissant->update([
                    'activation_token' => Str::random(64),
                    'activation_token_expire_at' => now()->addDays(7),
                ]);

                Mail::to($ressortissant->email)->send(new VerificationEmailRessortissant($ressortissant, reinitialisation: true));
            }
        } catch (\Exception $e) {
            \Log::error('Erreur envoi email réinitialisation ressortissant: ' . $e->getMessage());
        }

        return response()->json($reponse);
    }

    /**
     * Finalise une réinitialisation de mot de passe. Distincte de
     * verifierEmail() bien que les deux consomment le même champ token :
     * ici on ne touche jamais à email_verified_at.
     */
    public function reinitialiserMotDePasse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $ressortissant = Ressortissant::where('activation_token', $request->token)->first();

        if (!$ressortissant) {
            return response()->json([
                'success' => false,
                'message' => 'Lien de réinitialisation invalide',
            ], 404);
        }

        if (!$ressortissant->activation_token_expire_at || $ressortissant->activation_token_expire_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce lien a expiré. Refaites une demande de réinitialisation.',
            ], 410);
        }

        $ressortissant->update([
            'password' => Hash::make($request->password),
            'activation_token' => null,
            'activation_token_expire_at' => null,
        ]);

        $ressortissant->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé. Vous pouvez vous connecter.',
        ]);
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
            ], 500);
        }
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'ressortissant' => $this->formaterRessortissant($request->user()),
            ],
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $ressortissant = $request->user();

        if (!Hash::check($request->current_password, $ressortissant->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Le mot de passe actuel est incorrect',
            ], 401);
        }

        $ressortissant->password = Hash::make($request->new_password);
        $ressortissant->save();

        $ressortissant->tokens()->where('id', '!=', $ressortissant->currentAccessToken()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès',
        ]);
    }

    /**
     * Champs exposés au frontend membre — volontairement plus restreint
     * que l'espace admin (pas de champs internes de gestion).
     */
    private function formaterRessortissant(Ressortissant $ressortissant): array
    {
        return [
            'id' => $ressortissant->id,
            'numero_registre' => $ressortissant->numero_registre,
            'nom' => $ressortissant->nom,
            'prenom' => $ressortissant->prenom,
            'nom_complet' => $ressortissant->nom_complet,
            'email' => $ressortissant->email,
            'photo' => $ressortissant->photo_url,
            'ville' => $ressortissant->ville,
            'quartier' => $ressortissant->quartier,
            'statut' => $ressortissant->statut,
            'inscription_verifiee' => $ressortissant->inscription_verifiee,
            'derniere_connexion' => $ressortissant->derniere_connexion?->format('d/m/Y H:i'),
        ];
    }
}
