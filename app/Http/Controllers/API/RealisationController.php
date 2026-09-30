<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Realisation;
use App\Models\RealisationPhoto;
use App\Services\ImageCompressionService;
use App\Support\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Réalisations de la communauté et culture & patrimoine. Lecture publique
 * (entrées publiées uniquement, sauf pour le personnel connecté) ;
 * création/modification réservées à admin/super_admin, suppression au
 * super_admin (voir routes/api.php).
 */
class RealisationController extends Controller
{
    private function regles(bool $creation): array
    {
        $presence = $creation ? 'required' : 'sometimes';

        return [
            'rubrique' => [$presence, 'in:' . implode(',', array_keys(Realisation::RUBRIQUES))],
            'titre' => [$presence, 'string', 'max:255'],
            'description' => 'nullable|string|max:5000',
            'contenu' => 'nullable|string|max:20000',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp|max:10240',
            'photos_supprimees' => 'nullable|array',
            'photos_supprimees.*' => 'integer',
            'date_realisation' => 'nullable|date',
            'publie' => 'sometimes|boolean',
            'ordre' => 'sometimes|integer|min:0',
        ];
    }

    public function index(Request $request)
    {
        $query = Realisation::with('photos');

        if ($request->filled('rubrique')) {
            $query->where('rubrique', $request->string('rubrique'));
        }

        // Le personnel voit aussi les brouillons (écran admin) ; le public
        // ne voit jamais que les entrées publiées.
        if (!Staff::est($request)) {
            $query->publie();
        }

        $realisations = $query
            ->orderBy('ordre')
            ->orderByDesc('date_realisation')
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $realisations]);
    }

    /**
     * Détail d'une réalisation (page publique). Un brouillon n'est visible
     * que du personnel.
     */
    public function show(Request $request, int $id)
    {
        $realisation = Realisation::with('photos')->find($id);

        if (!$realisation || (!$realisation->publie && !Staff::est($request))) {
            return response()->json(['success' => false, 'message' => 'Réalisation introuvable'], 404);
        }

        return response()->json(['success' => true, 'data' => $realisation]);
    }

    public function store(Request $request, ImageCompressionService $compressor)
    {
        $validator = Validator::make($request->all(), $this->regles(true));

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        unset($data['photo'], $data['photos'], $data['photos_supprimees']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('realisations', 'public');
        }

        $realisation = Realisation::create($data);
        $this->ajouterPhotos($request, $realisation, $compressor, 0);
        $realisation->load('photos');

        return response()->json([
            'success' => true,
            'message' => 'Réalisation créée avec succès',
            'data' => $realisation,
        ], 201);
    }

    public function update(Request $request, int $id, ImageCompressionService $compressor)
    {
        $realisation = Realisation::find($id);

        if (!$realisation) {
            return response()->json(['success' => false, 'message' => 'Réalisation introuvable'], 404);
        }

        $validator = Validator::make($request->all(), $this->regles(false));

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        unset($data['photo'], $data['photos'], $data['photos_supprimees']);

        if ($request->hasFile('photo')) {
            if ($realisation->photo) {
                Storage::disk('public')->delete($realisation->photo);
            }
            $data['photo'] = $request->file('photo')->store('realisations', 'public');
        }

        $realisation->update($data);

        // Photos retirées de la galerie par l'admin.
        if ($request->filled('photos_supprimees')) {
            $realisation->photos()->whereIn('id', $request->input('photos_supprimees'))->get()
                ->each(function (RealisationPhoto $photo) {
                    Storage::disk('public')->delete($photo->chemin);
                    $photo->delete();
                });
        }

        $this->ajouterPhotos($request, $realisation, $compressor, ($realisation->photos()->max('ordre') ?? -1) + 1);

        return response()->json([
            'success' => true,
            'message' => 'Réalisation mise à jour avec succès',
            'data' => $realisation->fresh('photos'),
        ]);
    }

    private function ajouterPhotos(Request $request, Realisation $realisation, ImageCompressionService $compressor, int $ordreDepart): void
    {
        if (!$request->hasFile('photos')) {
            return;
        }

        foreach ($request->file('photos') as $i => $fichier) {
            RealisationPhoto::create([
                'realisation_id' => $realisation->id,
                'chemin' => $compressor->store($fichier, 'realisations'),
                'ordre' => $ordreDepart + $i,
            ]);
        }
    }

    public function destroy(int $id)
    {
        $realisation = Realisation::with('photos')->find($id);

        if (!$realisation) {
            return response()->json(['success' => false, 'message' => 'Réalisation introuvable'], 404);
        }

        if ($realisation->photo) {
            Storage::disk('public')->delete($realisation->photo);
        }
        foreach ($realisation->photos as $photo) {
            Storage::disk('public')->delete($photo->chemin);
        }

        $realisation->delete();

        return response()->json(['success' => true, 'message' => 'Réalisation supprimée avec succès']);
    }
}
