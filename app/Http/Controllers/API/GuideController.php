<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GuideSection;
use App\Models\GuideSousSection;
use App\Models\GuideDocument;
use App\Support\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class GuideController extends Controller
{
    /**
     * Arborescence complète du guide (sections > sous-sections > documents),
     * pour affichage direct côté site public.
     */
    public function index(Request $request)
    {
        try {
            // ?all=1 (brouillons inclus) est réservé au personnel connecté :
            // cette route est publique, le paramètre seul ne prouve rien.
            $onlyPublished = !($request->boolean('all') && Staff::est($request));

            $query = GuideSection::with(['sousSections' => function ($q) use ($onlyPublished) {
                if ($onlyPublished) {
                    $q->publie();
                }
                $q->with(['documents' => function ($q2) use ($onlyPublished) {
                    if ($onlyPublished) {
                        $q2->publie();
                    }
                }]);
            }]);

            if ($request->has('categorie')) {
                $query->byCategorie($request->categorie);
            }

            if ($onlyPublished) {
                // Par défaut, uniquement le contenu publié — la cascade
                // ci-dessus s'applique aussi aux sous-sections/documents pour
                // qu'aucun brouillon ne fuite côté site public. Les routes
                // admin protégées peuvent passer ?all=1 pour tout voir.
                $query->publie();
            }

            $sections = $query->orderBy('ordre')->get();

            return response()->json([
                'success' => true,
                'data' => $sections
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du guide'
            ], 500);
        }
    }

    /**
     * Afficher une section avec ses sous-sections et documents
     */
    public function showSection(Request $request, $id)
    {
        try {
            $onlyPublished = !($request->boolean('all') && Staff::est($request));

            $section = GuideSection::with(['sousSections' => function ($q) use ($onlyPublished) {
                if ($onlyPublished) {
                    $q->publie();
                }
                $q->with(['documents' => function ($q2) use ($onlyPublished) {
                    if ($onlyPublished) {
                        $q2->publie();
                    }
                }]);
            }])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $section
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Section non trouvée'
            ], 404);
        }
    }

    // ==================== SECTIONS ====================

    public function storeSection(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'titre' => 'required|string|max:255',
                'description' => 'nullable|string',
                'categorie' => 'nullable|string|max:100',
                'contenu' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:20480',
                'icone' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:5120',
                'ordre' => 'nullable|integer|min:0',
                'statut' => 'sometimes|in:publie,brouillon',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            if ($request->hasFile('image')) {
                $data['image'] = str_replace('public/', '', $request->file('image')->store('guide/sections', 'public'));
            }
            if ($request->hasFile('icone')) {
                $data['icone'] = str_replace('public/', '', $request->file('icone')->store('guide/icones', 'public'));
            }

            $section = GuideSection::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Section créée avec succès',
                'data' => $section
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateSection(Request $request, $id)
    {
        try {
            $section = GuideSection::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'titre' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'categorie' => 'nullable|string|max:100',
                'contenu' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:20480',
                'icone' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:5120',
                'ordre' => 'nullable|integer|min:0',
                'statut' => 'sometimes|in:publie,brouillon',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            if ($request->hasFile('image')) {
                if ($section->image) {
                    Storage::delete('public/' . $section->image);
                }
                $data['image'] = str_replace('public/', '', $request->file('image')->store('guide/sections', 'public'));
            }
            if ($request->hasFile('icone')) {
                if ($section->icone) {
                    Storage::delete('public/' . $section->icone);
                }
                $data['icone'] = str_replace('public/', '', $request->file('icone')->store('guide/icones', 'public'));
            }

            $section->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Section mise à jour avec succès',
                'data' => $section
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroySection($id)
    {
        try {
            $section = GuideSection::findOrFail($id);

            if ($section->image) {
                Storage::delete('public/' . $section->image);
            }
            if ($section->icone) {
                Storage::delete('public/' . $section->icone);
            }

            // Les sous-sections (et leurs documents) sont supprimées en
            // cascade au niveau base de données (contrainte FK ON DELETE
            // CASCADE définie dans la migration).
            $section->delete();

            return response()->json([
                'success' => true,
                'message' => 'Section supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    // ==================== SOUS-SECTIONS ====================

    public function storeSousSection(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'section_id' => 'required|exists:guide_sections,id',
                'titre' => 'required|string|max:255',
                'contenu' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:20480',
                'ordre' => 'nullable|integer|min:0',
                'statut' => 'sometimes|in:publie,brouillon',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            if ($request->hasFile('image')) {
                $data['image'] = str_replace('public/', '', $request->file('image')->store('guide/sous-sections', 'public'));
            }

            $sousSection = GuideSousSection::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Sous-section créée avec succès',
                'data' => $sousSection
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateSousSection(Request $request, $id)
    {
        try {
            $sousSection = GuideSousSection::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'section_id' => 'sometimes|exists:guide_sections,id',
                'titre' => 'sometimes|string|max:255',
                'contenu' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:20480',
                'ordre' => 'nullable|integer|min:0',
                'statut' => 'sometimes|in:publie,brouillon',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            if ($request->hasFile('image')) {
                if ($sousSection->image) {
                    Storage::delete('public/' . $sousSection->image);
                }
                $data['image'] = str_replace('public/', '', $request->file('image')->store('guide/sous-sections', 'public'));
            }

            $sousSection->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Sous-section mise à jour avec succès',
                'data' => $sousSection
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroySousSection($id)
    {
        try {
            $sousSection = GuideSousSection::findOrFail($id);

            if ($sousSection->image) {
                Storage::delete('public/' . $sousSection->image);
            }

            $sousSection->delete();

            return response()->json([
                'success' => true,
                'message' => 'Sous-section supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    // ==================== DOCUMENTS ====================

    public function storeDocument(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'sous_section_id' => 'required|exists:guide_sous_sections,id',
                'titre' => 'required|string|max:255',
                'description' => 'nullable|string',
                'fichier' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:20480',
                'statut' => 'sometimes|in:publie,brouillon',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            unset($data['fichier']);

            $file = $request->file('fichier');
            $path = $file->store('guide/documents', 'public');
            $data['fichier'] = str_replace('public/', '', $path);
            $data['type_fichier'] = $file->getClientOriginalExtension();
            $data['taille'] = $file->getSize();

            $document = GuideDocument::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Document ajouté avec succès',
                'data' => $document
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout du document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateDocument(Request $request, $id)
    {
        try {
            $document = GuideDocument::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'sous_section_id' => 'sometimes|exists:guide_sous_sections,id',
                'titre' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'fichier' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:20480',
                'statut' => 'sometimes|in:publie,brouillon',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            unset($data['fichier']);

            if ($request->hasFile('fichier')) {
                if ($document->fichier) {
                    Storage::delete('public/' . $document->fichier);
                }
                $file = $request->file('fichier');
                $path = $file->store('guide/documents', 'public');
                $data['fichier'] = str_replace('public/', '', $path);
                $data['type_fichier'] = $file->getClientOriginalExtension();
                $data['taille'] = $file->getSize();
            }

            $document->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Document mis à jour avec succès',
                'data' => $document
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroyDocument($id)
    {
        try {
            $document = GuideDocument::findOrFail($id);

            if ($document->fichier) {
                Storage::delete('public/' . $document->fichier);
            }

            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Incrémente le compteur de téléchargements et renvoie l'URL du fichier.
     * Le front peut appeler cette route puis rediriger vers `download_url`.
     */
    public function telechargerDocument(Request $request, $id)
    {
        try {
            $query = GuideDocument::query();

            // Un document non publié n'est pas téléchargeable par le public.
            if (!Staff::est($request)) {
                $query->publie();
            }

            $document = $query->findOrFail($id);
            $document->increment('telechargements');

            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => $document->fichier_url
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document non trouvé'
            ], 404);
        }
    }
}
