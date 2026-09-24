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
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des messages',
                'error' => $e->getMessage()
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
     * Créer un nouveau message
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'telephone' => 'required|string|max:20',
                'objet' => 'required|in:question,partenariat,service_consulaire,urgence,autre',
                'message' => 'required|string',
                'organisation' => 'required_if:objet,partenariat|nullable|string|max:255',
                'type_organisation' => 'nullable|in:institution,ong,entreprise,media,universite,association',
                'secteur_activite' => 'nullable|string|max:255',
                'pays' => 'nullable|string|max:255',
                'ville' => 'nullable|string|max:255',
                'site_web' => 'nullable|string|max:255',
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
            $message = Message::create($validator->validated());

            // Envoyer email de confirmation à l'utilisateur
            try {
                Mail::to($message->email)->send(new ConfirmationMessage($message));
            } catch (\Exception $e) {
                \Log::error('Erreur envoi email confirmation message: ' . $e->getMessage());
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
                    \Log::warning('Nouveau message de contact : aucun destinataire admin (MAIL_ADMIN_ADDRESS ou email de l\'entité).');
                }
            } catch (\Exception $e) {
                \Log::error('Erreur envoi email notification admin message: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Message envoyé avec succès. Un email de confirmation vous a été envoyé.',
                'data' => $message,
                'redirect_url' => config('app.frontend_url') . '/admin/messages/' . $message->id
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du message',
                'error' => $e->getMessage()
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
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
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
                
                // Mettre à jour le statut du message
                $message->update([
                    'statut' => 'repondu',
                    'date_reponse' => now()
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Réponse envoyée avec succès',
                    'data' => $message,
                    'redirect_url' => config('app.frontend_url') . '/admin/messages/' . $id
                ], 200);
                
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de l\'envoi de la réponse',
                    'error' => $e->getMessage()
                ], 500);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réponse',
                'error' => $e->getMessage()
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
            $message->delete();

            return response()->json([
                'success' => true,
                'message' => 'Message supprimé avec succès',
                'redirect_url' => config('app.frontend_url') . '/admin/messages'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du message',
                'error' => $e->getMessage()
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
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du partenaire',
                'error' => $e->getMessage()
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
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage du message',
                'error' => $e->getMessage()
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
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
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
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}