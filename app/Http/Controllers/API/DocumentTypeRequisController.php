<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DocumentTypeRequis;
use App\Models\JournalActivite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Configuration dynamique des pièces à fournir par type de demande — c'est
 * cette table que le formulaire de dépôt (espace ressortissant) interroge
 * pour générer ses champs d'upload. Ajouter/retirer une pièce ici ne
 * nécessite aucun déploiement de code (voir DocumentTypeRequis).
 */
class DocumentTypeRequisController extends Controller
{
    public function index(Request $request)
    {
        $query = DocumentTypeRequis::query()->orderBy('type_demande')->orderBy('ordre');

        if ($request->filled('type_demande')) {
            $query->where('type_demande', $request->string('type_demande'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type_demande' => 'required|string|max:50',
            'code_document' => 'required|string|max:50',
            'label' => 'required|string|max:255',
            'aide' => 'nullable|string|max:1000',
            'ordre' => 'nullable|integer|min:0',
            'nombre_requis' => 'nullable|integer|min:1',
            'formats_acceptes' => 'nullable|string|max:100',
            'taille_max_ko' => 'nullable|integer|min:1',
            'obligatoire' => 'boolean',
            'est_actif' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['ordre'] = $data['ordre'] ?? (
            DocumentTypeRequis::where('type_demande', $data['type_demande'])->max('ordre') + 1
        );
        $data['nombre_requis'] = $data['nombre_requis'] ?? 1;
        $data['formats_acceptes'] = $data['formats_acceptes'] ?? 'jpg,jpeg,png,pdf';
        $data['taille_max_ko'] = $data['taille_max_ko'] ?? 5120;
        $data['obligatoire'] = $data['obligatoire'] ?? true;
        $data['est_actif'] = $data['est_actif'] ?? true;

        $document = DocumentTypeRequis::create($data);

        JournalActivite::enregistrer(
            'document_type_requis.creer',
            "Pièce requise ajoutée : « {$document->label} » ({$document->type_demande})",
            $document
        );

        return response()->json(['success' => true, 'data' => $document], 201);
    }

    public function update(Request $request, int $id)
    {
        $document = DocumentTypeRequis::find($id);

        if (!$document) {
            return response()->json(['success' => false, 'message' => 'Pièce requise introuvable'], 404);
        }

        $validator = Validator::make($request->all(), [
            'label' => 'sometimes|required|string|max:255',
            'aide' => 'nullable|string|max:1000',
            'ordre' => 'nullable|integer|min:0',
            'nombre_requis' => 'nullable|integer|min:1',
            'formats_acceptes' => 'nullable|string|max:100',
            'taille_max_ko' => 'nullable|integer|min:1',
            'obligatoire' => 'boolean',
            'est_actif' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $document->update($validator->validated());

        JournalActivite::enregistrer(
            'document_type_requis.modifier',
            "Pièce requise modifiée : « {$document->label} » ({$document->type_demande})",
            $document
        );

        return response()->json(['success' => true, 'data' => $document->fresh()]);
    }

    public function destroy(int $id)
    {
        $document = DocumentTypeRequis::find($id);

        if (!$document) {
            return response()->json(['success' => false, 'message' => 'Pièce requise introuvable'], 404);
        }

        JournalActivite::enregistrer(
            'document_type_requis.supprimer',
            "Pièce requise supprimée : « {$document->label} » ({$document->type_demande})",
            $document
        );

        $document->delete();

        return response()->json(['success' => true, 'message' => 'Pièce requise supprimée']);
    }
}
