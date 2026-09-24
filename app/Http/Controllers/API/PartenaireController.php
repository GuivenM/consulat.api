<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Partenaire;
use App\Support\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PartenaireController extends Controller
{
    /**
     * Afficher tous les partenaires
     */
    public function index(Request $request)
    {
        try {
            $query = Partenaire::query();

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('niveau_partenariat')) {
                $query->where('niveau_partenariat', $request->niveau_partenariat);
            }

            // Le filtre ?statut= n'est honoré que pour le personnel connecté
            // (écran admin : actifs et inactifs). Le public ne voit jamais
            // que les partenaires actifs, même en forçant le paramètre.
            if ($request->has('statut') && Staff::est($request)) {
                $query->where('statut', $request->statut);
            } else {
                // Par défaut, ne montrer que les partenaires actifs sur les
                // routes publiques (aucune donnée de partenariat expiré ne
                // fuite sans le filtre explicite).
                $query->actif();
            }

            $partenaires = $query->orderBy('nom')->get();

            return response()->json([
                'success' => true,
                'data' => $partenaires
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des partenaires'
            ], 500);
        }
    }

    /**
     * Afficher un partenaire spécifique
     */
    public function show(Request $request, $id)
    {
        try {
            $query = Partenaire::query();

            if (!Staff::est($request)) {
                $query->actif();
            }

            $partenaire = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $partenaire
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Partenaire non trouvé'
            ], 404);
        }
    }

    /**
     * Créer un nouveau partenaire
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nom' => 'required|string|max:255',
                'description' => 'nullable|string',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:10240',
                'site_web' => 'nullable|url',
                'type' => 'nullable|in:institution,ong,entreprise,media,universite,association',
                'secteur_activite' => 'nullable|string|max:255',
                'pays' => 'nullable|string|max:100',
                'ville' => 'nullable|string|max:100',
                'adresse' => 'nullable|string|max:255',
                'email' => 'nullable|email',
                'telephone' => 'nullable|string|max:30',
                'date_debut_partenariat' => 'nullable|date',
                'date_fin_partenariat' => 'nullable|date|after_or_equal:date_debut_partenariat',
                'niveau_partenariat' => 'nullable|in:or,argent,bronze,institutionnel,technique',
                'domaines_intervention' => 'nullable|array',
                'statut' => 'sometimes|in:actif,inactif',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('partenaires', 'public');
                $data['logo'] = str_replace('public/', '', $path);
            }

            $partenaire = Partenaire::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Partenaire créé avec succès',
                'data' => $partenaire
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un partenaire
     */
    public function update(Request $request, $id)
    {
        try {
            $partenaire = Partenaire::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nom' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:10240',
                'site_web' => 'nullable|url',
                'type' => 'nullable|in:institution,ong,entreprise,media,universite,association',
                'secteur_activite' => 'nullable|string|max:255',
                'pays' => 'nullable|string|max:100',
                'ville' => 'nullable|string|max:100',
                'adresse' => 'nullable|string|max:255',
                'email' => 'nullable|email',
                'telephone' => 'nullable|string|max:30',
                'date_debut_partenariat' => 'nullable|date',
                'date_fin_partenariat' => 'nullable|date|after_or_equal:date_debut_partenariat',
                'niveau_partenariat' => 'nullable|in:or,argent,bronze,institutionnel,technique',
                'domaines_intervention' => 'nullable|array',
                'statut' => 'sometimes|in:actif,inactif',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            if ($request->hasFile('logo')) {
                if ($partenaire->logo) {
                    Storage::delete('public/' . $partenaire->logo);
                }
                $path = $request->file('logo')->store('partenaires', 'public');
                $data['logo'] = str_replace('public/', '', $path);
            }

            $partenaire->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Partenaire mis à jour avec succès',
                'data' => $partenaire
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
     * Supprimer un partenaire
     */
    public function destroy($id)
    {
        try {
            $partenaire = Partenaire::findOrFail($id);

            if ($partenaire->logo) {
                Storage::delete('public/' . $partenaire->logo);
            }

            $partenaire->delete();

            return response()->json([
                'success' => true,
                'message' => 'Partenaire supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Statistiques des partenaires
     */
    public function statistiques()
    {
        try {
            $stats = [
                'total' => Partenaire::count(),
                'actifs' => Partenaire::actif()->count(),
                'par_type' => Partenaire::selectRaw('type, count(*) as total')
                    ->groupBy('type')
                    ->get(),
                'par_niveau' => Partenaire::selectRaw('niveau_partenariat, count(*) as total')
                    ->groupBy('niveau_partenariat')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques'
            ], 500);
        }
    }
}
