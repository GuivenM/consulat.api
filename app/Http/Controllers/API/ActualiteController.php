<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Actualite;
use App\Models\ActualitePhoto;
use App\Services\FacebookPublisherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Services\ImageCompressionService;

class ActualiteController extends Controller
{
    /**
     * Afficher toutes les actualités
     */
    public function index()
    {
        try {
            $actualites = Actualite::with('photos')->orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $actualites,
                'message' => 'Actualités récupérées avec succès'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des actualités',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher les actualités par type
     */
    public function getByType($type)
    {
        try {
            $actualites = Actualite::with('photos')
                ->where('type', $type)
                ->where('statut', 'publie')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $actualites,
                'message' => 'Actualités récupérées avec succès'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des actualités',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher les 3 dernières actualités
     */
    public function dernieresActualites()
    {
        try {
            $actualites = Actualite::with('photos')
                ->where('statut', 'publie')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $actualites,
                'message' => 'Dernières actualités récupérées avec succès'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des actualités',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une actualité spécifique
     */
    public function show($id)
    {
        try {
            $actualite = Actualite::with('photos')->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $actualite,
                'message' => 'Actualité récupérée avec succès'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Actualité non trouvée'
            ], 404);
        }
    }

    /**
     * Créer une nouvelle actualité
     */
    public function store(Request $request, ImageCompressionService $compressor)
{
    try {
        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'contenu' => 'required|string',
            'type' => 'required|in:actualite,evenement,education,culture',
           'image' => 'nullable|image|mimes:jpeg,png,jpg|max:20480',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg|max:20480',
            'date_evenement' => 'nullable|date',
            'lieu_evenement' => 'nullable|string|max:255',
            'statut' => 'sometimes|in:publie,brouillon'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['image', 'photos']);
        
        // Valeurs par défaut
        $entite = \App\Support\CurrentEntity::resolve();
        $data['auteur'] = $entite->nom_court ?? $entite->nom;
        // Le slug sera généré automatiquement par le modèle

        if ($request->hasFile('image')) {
            $data['image'] = $compressor->store($request->file('image'), 'actualites');
        }

        $actualite = Actualite::create($data);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $ordre => $photo) {
                ActualitePhoto::create([
                    'actualite_id' => $actualite->id,
                    'chemin' => $compressor->store($photo, 'actualites'),
                    'ordre' => $ordre,
                ]);
            }
            $actualite->load('photos');
        }

        return response()->json([
            'success' => true,
            'message' => 'Actualité créée avec succès',
            'data' => $actualite
        ], 201);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la création de l\'actualité',
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Mettre à jour une actualité
     */
    public function update(Request $request, $id, ImageCompressionService $compressor)
    {
        try {
            $actualite = Actualite::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'titre' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'contenu' => 'sometimes|string',
                'type' => 'sometimes|in:actualite,evenement,education,culture',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:20480',
                'photos' => 'nullable|array|max:10',
                'photos.*' => 'image|mimes:jpeg,png,jpg|max:20480',
                'photos_supprimees' => 'nullable|array',
                'photos_supprimees.*' => 'integer|exists:actualite_photos,id',
                'date_evenement' => 'nullable|date',
                'lieu_evenement' => 'nullable|string|max:255',
                'statut' => 'sometimes|in:publie,brouillon'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->except(['image', 'photos', 'photos_supprimees']);

            if ($request->hasFile('image')) {
                // Supprimer l'ancienne image
                if ($actualite->image) {
                    Storage::delete('public/' . $actualite->image);
                }
                
                $data['image'] = $compressor->store($request->file('image'), 'actualites');
            }

            $actualite->update($data);

            // Retirer les photos que l'admin a explicitement décochées dans la galerie.
            if ($request->filled('photos_supprimees')) {
                $aSupprimer = $actualite->photos()->whereIn('id', $request->input('photos_supprimees'))->get();
                foreach ($aSupprimer as $photo) {
                    Storage::delete('public/' . $photo->chemin);
                    $photo->delete();
                }
            }

            // Ajouter les nouvelles photos à la suite de la galerie existante.
            if ($request->hasFile('photos')) {
                $prochainOrdre = ($actualite->photos()->max('ordre') ?? -1) + 1;
                foreach ($request->file('photos') as $i => $photo) {
                    ActualitePhoto::create([
                        'actualite_id' => $actualite->id,
                        'chemin' => $compressor->store($photo, 'actualites'),
                        'ordre' => $prochainOrdre + $i,
                    ]);
                }
            }

            $actualite->load('photos');

            return response()->json([
                'success' => true,
                'message' => 'Actualité mise à jour avec succès',
                'data' => $actualite
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'actualité',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une actualité
     */
    public function destroy($id)
    {
        try {
            $actualite = Actualite::with('photos')->findOrFail($id);
            
            // Supprimer l'image
            if ($actualite->image) {
                Storage::delete('public/' . $actualite->image);
            }

            // Supprimer les fichiers de la galerie (les lignes partent avec le
            // cascadeOnDelete de la migration, mais pas les fichiers sur disque).
            foreach ($actualite->photos as $photo) {
                Storage::delete('public/' . $photo->chemin);
            }
            
            $actualite->delete();

            return response()->json([
                'success' => true,
                'message' => 'Actualité supprimée avec succès'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'actualité',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Indique si l'auto-publication Facebook est configurée côté serveur
     * (Page + token présents) — le front s'en sert pour afficher soit le
     * bouton "Publier sur Facebook" (auto), soit un lien de partage manuel.
     *
     * GET /v1/actualites/partage-config
     */
    public function partageConfig(FacebookPublisherService $facebook)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'facebook_configure' => $facebook->isConfigured(),
            ],
        ]);
    }

    /**
     * Publie l'actualité sur la Page Facebook de l'association (photo +
     * légende si l'actualité a une image, sinon lien avec aperçu).
     * Idempotent en pratique : le lien du post est renvoyé au front qui
     * désactive le bouton une fois `facebook_post_url` renseigné.
     *
     * POST /v1/actualites/{id}/partager-facebook
     */
    public function partagerFacebook($id, FacebookPublisherService $facebook)
    {
        try {
            $actualite = Actualite::findOrFail($id);

            $url = $facebook->publier($actualite);
            $actualite->update(['facebook_post_url' => $url]);

            return response()->json([
                'success' => true,
                'message' => 'Actualité publiée sur Facebook',
                'data' => $actualite,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Actualité non trouvée',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Échec de la publication sur Facebook',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Rechercher des actualités
     */
    public function rechercher(Request $request)
    {
        try {
            $query = Actualite::query();
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('titre', 'like', "%$search%")
                      ->orWhere('description', 'like', "%$search%")
                      ->orWhere('contenu', 'like', "%$search%");
                });
            }
            
            if ($request->has('type') && $request->type !== 'tous') {
                $query->where('type', $request->type);
            }
            
            if ($request->has('statut') && $request->statut !== 'tous') {
                $query->where('statut', $request->statut);
            }
            
            if ($request->has('date_debut') && $request->has('date_fin')) {
                $query->whereBetween('created_at', [$request->date_debut, $request->date_fin]);
            }
            
            $actualites = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));
            
            return response()->json([
                'success' => true,
                'data' => $actualites,
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
     * Statistiques des actualités
     */
    public function statistiques()
    {
        try {
            $stats = [
                'total' => Actualite::count(),
                'publiees' => Actualite::where('statut', 'publie')->count(),
                'brouillons' => Actualite::where('statut', 'brouillon')->count(),
                'par_type' => Actualite::selectRaw('type, count(*) as total')
                    ->groupBy('type')
                    ->get(),
                'dernieres_publications' => Actualite::orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'titre', 'type', 'created_at'])
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistiques récupérées avec succès'
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