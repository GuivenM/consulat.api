<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Models\Tarif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Grille tarifaire (type_demande, délai) -> montant, gérée depuis l'espace
 * admin. Aucune route publique : le montant applicable à une demande est
 * résolu côté serveur (Tarif::montantPour) au moment du dépôt, jamais
 * envoyé en clair au front pour être renvoyé tel quel.
 */
class TarifController extends Controller
{
    public function index(Request $request)
    {
        $query = Tarif::query();

        if ($request->filled('type_demande')) {
            $query->where('type_demande', $request->string('type_demande'));
        }

        $ordreDelai = array_flip(array_keys(Tarif::DELAIS));

        $tarifs = $query->get()
            ->sortBy(fn (Tarif $t) => [$t->type_demande, $ordreDelai[$t->delai] ?? 99])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $tarifs,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type_demande' => 'required|string|max:50',
            'delai' => 'required|in:' . implode(',', array_keys(Tarif::DELAIS)),
            'montant' => 'required|numeric|min:0',
            'devise' => 'nullable|string|max:5',
            'delai_heures' => 'nullable|integer|min:1',
            'est_actif' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['devise'] = $data['devise'] ?? 'XOF';
        $data['est_actif'] = $data['est_actif'] ?? true;

        $tarif = Tarif::create($data);

        JournalActivite::enregistrer(
            'tarif.creer',
            "Tarif créé : {$tarif->type_demande} / {$tarif->delai} = {$tarif->montant} {$tarif->devise}",
            $tarif
        );

        return response()->json(['success' => true, 'data' => $tarif], 201);
    }

    public function update(Request $request, int $id)
    {
        $tarif = Tarif::find($id);

        if (!$tarif) {
            return response()->json(['success' => false, 'message' => 'Tarif introuvable'], 404);
        }

        $validator = Validator::make($request->all(), [
            'montant' => 'sometimes|required|numeric|min:0',
            'devise' => 'nullable|string|max:5',
            'delai_heures' => 'nullable|integer|min:1',
            'est_actif' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $tarif->update($validator->validated());

        JournalActivite::enregistrer(
            'tarif.modifier',
            "Tarif modifié : {$tarif->type_demande} / {$tarif->delai} = {$tarif->montant} {$tarif->devise}",
            $tarif
        );

        return response()->json(['success' => true, 'data' => $tarif->fresh()]);
    }

    public function destroy(int $id)
    {
        $tarif = Tarif::find($id);

        if (!$tarif) {
            return response()->json(['success' => false, 'message' => 'Tarif introuvable'], 404);
        }

        JournalActivite::enregistrer(
            'tarif.supprimer',
            "Tarif supprimé : {$tarif->type_demande} / {$tarif->delai}",
            $tarif
        );

        $tarif->delete();

        return response()->json(['success' => true, 'message' => 'Tarif supprimé']);
    }
}
