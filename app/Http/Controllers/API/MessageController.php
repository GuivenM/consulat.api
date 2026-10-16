<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Partenaire;
use App\Mail\ConfirmationMessage;
use App\Mail\ReponseMessage;
use App\Mail\NotificationNouveauMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    /**
     * Afficher tous les messages
     */
    public function index()
    {
        try {
            $messages = Message::orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $messages,
                'message' => 'Messages récupérés avec succès'
            ], 200);
            
        } catch (\Exception $e) {
            report($e); // journalisé côté serveur, jamais renvoyé au client
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des messages'
            ], 500);
        }
    }

    /**
     * Afficher un message spécifique
     */
    public function show($id)
    {
        try {
            $message = Message::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $message,
                'message' => 'Message récupéré avec succès'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Message non trouvé'
            ], 404);
        }
    }

    /**
     * Affiche la pièce d'identité jointe à une demande d'audience (disque privé).
     *
     * GET /v1/messages/{id}/piece
     */
    public function piece($id)
    {
        $message = Message::find($id);

        if (!$message || !$message->piece_fichier
            || !Storage::disk('local')->exists($message->piece_fichier)) {
            return response()->json(['success' => false, 'message' => 'Pièce introuvable'], 404);
        }

        $nom = $message->piece_fichier_nom ?: basename($message->piece_fichier);

        return Storage::disk('local')->response(
            $message->piece_fichier,
            $nom,
            ['Content-Disposition' => 'inline; filename="' . addslashes($nom) . '"']
        );
    }

    /**
     * Créer un nouveau message
     */
    public function store(Request $request)
    {
        try {
            // SOS : le téléphone suffit (une personne en détresse n'a pas
            // forcément d'e-mail), mais le type d'aide et la localisation
            // sont indispensables pour intervenir.
            $estSos = $request->input('objet') === 'sos';
            $estAudience = $request->input('objet') === 'audience';

            $validator = Validator::make($request->all(), [
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'email' => ($estSos ? 'nullable' : 'required') . '|email|max:255',
                'telephone' => 'required|string|max:20',
                'objet' => 'required|in:question,partenariat,service_consulaire,urgence,audience,sos,autre',
                'message' => 'required|string',
                'date_souhaitee' => 'nullable|date|after_or_equal:today',
                'type_aide' => [Rule::requiredIf($estSos), 'nullable', Rule::in(array_keys(Message::TYPES_AIDE))],
                'localisation' => [Rule::requiredIf($estSos), 'nullable', 'string', 'max:255'],
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                // Audience : pièce d'identité (type + fichier) obligatoire.
                'piece_type' => [Rule::requiredIf($estAudience), 'nullable', Rule::in(Message::TYPES_PIECE_AUDIENCE)],
                'piece_numero' => 'nullable|string|max:50',
                'piece_fichier' => [Rule::requiredIf($estAudience), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
                'organisation' => 'required_if:objet,partenariat|nullable|string|max:255',
                'type_organisation' => 'nullable|in:institution,ong,entreprise,media,universite,association',
                'secteur_activite' => 'nullable|string|max:255',
                'pays' => 'nullable|string|max:255',
                'ville' => 'nullable|string|max:255',
                'site_web' => 'nullable|string|max:255',
            ], [
                'piece_type.required' => "Choisissez le type de pièce d'identité.",
                'piece_fichier.required' => "Joignez la copie de votre pièce d'identité (PDF ou image).",
                'piece_fichier.mimes' => 'La pièce doit être un fichier PDF, JPG ou PNG.',
                'piece_fichier.max' => 'La pièce ne doit pas dépasser 5 Mo.',
                'piece_fichier.uploaded' => "Le fichier n'a pas pu être envoyé (5 Mo maximum).",
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // IMPORTANT : uniquement les champs validés. 'statut', 'reponse' et
            // 'traite_par' sont fillable sur le modèle mais ne doivent jamais être
            // fixables par un appelant public (route non authentifiée).
            $donnees = $validator->validated();

            // Les champs propres à l'audience et au SOS ne sont conservés que
            // pour leur objet : un autre formulaire ne doit pas les remplir.
            if ($donnees['objet'] !== 'audience') {
                unset($donnees['date_souhaitee']);
            }
            if (!$estSos) {
                unset($donnees['type_aide'], $donnees['localisation'], $donnees['latitude'], $donnees['longitude']);
            }
            unset($donnees['piece_fichier']);
            if (!$estAudience) {
                unset($donnees['piece_type'], $donnees['piece_numero']);
            }

            // Pièce d'identité de l'audience : disque privé, jamais public.
            $cheminPiece = null;
            if ($estAudience && $request->hasFile('piece_fichier')) {
                $fichier = $request->file('piece_fichier');
                $cheminPiece = $fichier->store('messages/pieces', 'local');
                $donnees['piece_fichier'] = $cheminPiece;
                $donnees['piece_fichier_nom'] = mb_substr($fichier->getClientOriginalName(), 0, 255);
            }

            try {
                $message = Message::create($donnees);
            } catch (\Throwable $e) {
                if ($cheminPiece) {
                    Storage::disk('local')->delete($cheminPiece);
                }
                throw $e;
            }

            // Envoyer email de confirmation à l'utilisateur (s'il en a donné un)
            if ($message->email) {
                try {
                    Mail::to($message->email)->send(new ConfirmationMessage($message));
                } catch (\Exception $e) {
                    \Log::error('Erreur envoi email confirmation message: ' . $e->getMessage());
                }
            }

            // Envoyer notification à l'admin pour le nouveau message
            try {
                // Pas de repli en dur : une adresse d'une autre organisation
                // recevrait les messages des citoyens. Sans MAIL_ADMIN_ADDRESS,
                // on retombe sur l'email de l'entité, sinon on journalise.
                $adminEmail = config('mail.admin_address') ?: ($message->entity->email ?? null);
                if ($adminEmail) {
                    Mail::to($adminEmail)->send(new NotificationNouveauMessage($message));
                } else {
                    // Un SOS sans destinataire est une alerte perdue : niveau critical.
                    \Log::log($estSos ? 'critical' : 'warning', 'Nouveau message de contact (' . $message->objet . ') : aucun destinataire admin (MAIL_ADMIN_ADDRESS ou email de l\'entité).');
                }
            } catch (\Exception $e) {
                \Log::log($estSos ? 'critical' : 'error', 'Erreur envoi email notification admin message (' . $message->objet . '): ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Message envoyé avec succès. Un email de confirmation vous a été envoyé.',
                'data' => $message,
                'redirect_url' => config('app.frontend_url') . '/admin/messages/' . $message->id
            ], 201);
            
        } catch (\Exception $e) {
            report($e); // journalisé côté serveur, jamais renvoyé au client
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du message'
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'un message
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'statut' => 'required|in:non_lu,lu,repondu'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $message = Message::findOrFail($id);
            $message->update(['statut' => $request->statut]);

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'data' => $message,
                'redirect_url' => config('app.frontend_url') . '/admin/messages/' . $id
            ], 200);
            
        } catch (\Exception $e) {
            report($e); // journalisé côté serveur, jamais renvoyé au client
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut'
            ], 500);
        }
    }

    /**
     * Répondre à un message
     */
    public function repondre(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reponse' => 'required|string',
                'objet' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $message = Message::findOrFail($id);
            
            // Envoyer la réponse par email
            try {
                Mail::to($message->email)->send(new ReponseMessage(
                    $message, 
                    $request->reponse,
                    $request->objet ?: 'Réponse à votre message — ' . ($message->entity->nom_court ?? $message->entity->nom)
                ));
                
                // Conserve le texte envoyé, la date et l'agent qui a répondu
                // (le front affiche selected.reponse).
                $message->repondre($request->reponse, $request->user()?->id);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Réponse envoyée avec succès',
                    'data' => $message,
                    'redirect_url' => config('app.frontend_url') . '/admin/messages/' . $id
                ], 200);
                
            } catch (\Exception $e) {
                report($e); // journalisé côté serveur, jamais renvoyé au client
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de l\'envoi de la réponse'
                ], 500);
            }
            
        } catch (\Exception $e) {
            report($e); // journalisé côté serveur, jamais renvoyé au client
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réponse'
            ], 500);
        }
    }

    /**
     * Supprimer un message
     */
    public function destroy($id)
    {
        try {
            $message = Message::findOrFail($id);
            if ($message->piece_fichier) {
                Storage::disk('local')->delete($message->piece_fichier);
            }
            $message->delete();

            return response()->json([
                'success' => true,
                'message' => 'Message supprimé avec succès',
                'redirect_url' => config('app.frontend_url') . '/admin/messages'
            ], 200);
            
        } catch (\Exception $e) {
            report($e); // journalisé côté serveur, jamais renvoyé au client
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du message'
            ], 500);
        }
    }

    /**
     * Créer la fiche Partenaire à partir des infos d'organisation fournies
     * dans le message (visible uniquement quand objet = 'partenariat').
     * Un clic, statut 'inactif' par défaut — à activer une fois le
     * partenariat confirmé. Idempotent : un message déjà converti renvoie
     * simplement la fiche existante plutôt que d'en créer une seconde.
     */
    public function creerPartenaire($id)
    {
        try {
            $message = Message::findOrFail($id);

            if ($message->partenaire_id) {
                return response()->json([
                    'success' => true,
                    'message' => 'Ce message a déjà été converti en partenaire',
                    'data' => $message->partenaire()->first()
                ], 200);
            }

            if (!$message->organisation) {
                return response()->json([
                    'success' => false,
                    'message' => "Ce message ne contient pas d'informations d'organisation à convertir"
                ], 422);
            }

            $partenaire = Partenaire::create([
                'nom' => $message->organisation,
                'type' => $message->type_organisation,
                'secteur_activite' => $message->secteur_activite,
                'pays' => $message->pays,
                'ville' => $message->ville,
                'site_web' => $message->site_web,
                'email' => $message->email,
                'telephone' => $message->telephone,
                'description' => $message->message,
                'statut' => 'inactif',
            ]);

            $message->update(['partenaire_id' => $partenaire->id]);

            return response()->json([
                'success' => true,
                'message' => 'Partenaire créé (statut inactif, à activer depuis la page Partenaires)',
                'data' => $partenaire
            ], 201);

        } catch (\Exception $e) {
            report($e); // journalisé côté serveur, jamais renvoyé au client
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du partenaire'
            ], 500);
        }
    }

    /**
     * Marquer un message comme lu
     */
    public function marquerCommeLu($id)
    {
        try {
            $message = Message::findOrFail($id);
            
            if ($message->statut === 'non_lu') {
                $message->update([
                    'statut' => 'lu',
                    'lu_le' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Message marqué comme lu',
                'data' => $message,
                'redirect_url' => config('app.frontend_url') . '/admin/messages/' . $id
            ], 200);
            
        } catch (\Exception $e) {
            report($e); // journalisé côté serveur, jamais renvoyé au client
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage du message'
            ], 500);
        }
    }

    /**
     * Statistiques des messages
     */
    public function statistiques()
    {
        \Log::info('Méthode statistiques appelée');
        \Log::info('User:', ['user' => auth()->user()]);
        
        try {
            $stats = [
                'total' => Message::count(),
                'non_lus' => Message::where('statut', 'non_lu')->count(),
                'lus' => Message::where('statut', 'lu')->count(),
                'repondus' => Message::where('statut', 'repondu')->count(),
                'par_objet' => [
                    'question' => Message::where('objet', 'question')->count(),
                    'partenariat' => Message::where('objet', 'partenariat')->count(),
                    'service_consulaire' => Message::where('objet', 'service_consulaire')->count(),
                    'urgence' => Message::where('objet', 'urgence')->count(),
                    'audience' => Message::where('objet', 'audience')->count(),
                    'sos' => Message::where('objet', 'sos')->count(),
                    'autre' => Message::where('objet', 'autre')->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques'
            ], 500);
        }
    }

    /**
     * Rechercher des messages
     */
    public function rechercher(Request $request)
    {
        try {
            $query = Message::query();
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nom', 'like', "%$search%")
                      ->orWhere('prenom', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%")
                      ->orWhere('message', 'like', "%$search%");
                });
            }
            
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }
            
            if ($request->has('objet')) {
                $query->where('objet', $request->objet);
            }
            
            if ($request->has('date_debut') && $request->has('date_fin')) {
                $query->whereBetween('created_at', [$request->date_debut, $request->date_fin]);
            }
            
            $messages = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));
            
            return response()->json([
                'success' => true,
                'data' => $messages,
                'message' => 'Recherche effectuée avec succès'
            ], 200);
            
        } catch (\Exception $e) {
            report($e); // journalisé côté serveur, jamais renvoyé au client
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques par jour
     */
    public function statistiquesParJour()
    {
        try {
            $stats = Message::selectRaw('DATE(created_at) as date, count(*) as total')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistiques journalières récupérées avec succès'
            ], 200);
            
        } catch (\Exception $e) {
            report($e); // journalisé côté serveur, jamais renvoyé au client
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques'
            ], 500);
        }
    }
}