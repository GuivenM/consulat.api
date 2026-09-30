<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Realisation;
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
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'date_realisation' => 'nullable|date',
            'publie' => 'sometimes|boolean',
            'ordre' => 'sometimes|integer|min:0',
        ];
    }

    public function index(Request $request)
    {
        $query = Realisation::query();

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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->regles(true));

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        unset($data['photo']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('realisations', 'public');
        }

        $realisation = Realisation::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Réalisation créée avec succès',
            'data' => $realisation,
        ], 201);
    }

    public function update(Request $request, int $id)
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
        unset($data['photo']);

        if ($request->hasFile('photo')) {
            if ($realisation->photo) {
                Storage::disk('public')->delete($realisation->photo);
            }
            $data['photo'] = $request->file('photo')->store('realisations', 'public');
        }

        $realisation->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Réalisation mise à jour avec succès',
            'data' => $realisation->fresh(),
        ]);
    }

    public function destroy(int $id)
    {
        $realisation = Realisation::find($id);

        if (!$realisation) {
            return response()->json(['success' => false, 'message' => 'Réalisation introuvable'], 404);
        }

        if ($realisation->photo) {
            Storage::disk('public')->delete($realisation->photo);
        }

        $realisation->delete();

        return response()->json(['success' => true, 'message' => 'Réalisation supprimée avec succès']);
    }
}
