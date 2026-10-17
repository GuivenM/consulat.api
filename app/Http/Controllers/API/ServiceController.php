<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\DocumentTypeRequis;
use App\Models\JournalActivite;
use App\Models\Service;
use App\Models\Tarif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Services consulaires, gérés depuis l'espace admin (admin/super_admin).
 * Le `code` (clé des tarifs, pièces requises et demandes) est généré à la
 * création à partir du nom et n'est plus modifiable ensuite.
 */
class ServiceController extends Controller
{
    public function index()
    {
        $tarifs = Tarif::selectRaw('type_demande, count(*) as total')->groupBy('type_demande')->pluck('total', 'type_demande');
        $pieces = DocumentTypeRequis::selectRaw('type_demande, count(*) as total')->groupBy('type_demande')->pluck('total', 'type_demande');

        $services = Service::orderBy('ordre')->orderBy('id')->get()
            ->map(fn (Service $s) => array_merge($s->toArray(), [
                'nb_tarifs' => (int) ($tarifs[$s->code] ?? 0),
                'nb_pieces' => (int) ($pieces[$s->code] ?? 0),
            ]))
            ->values();

        return response()->json(['success' => true, 'data' => $services]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'ordre' => 'nullable|integer|min:0',
            'est_actif' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['code'] = $this->genererCode($data['nom']);
        $data['ordre'] = $data['ordre'] ?? ((int) Service::max('ordre') + 1);
        $data['est_actif'] = $data['est_actif'] ?? true;

        $service = Service::create($data);

        JournalActivite::enregistrer('service.creer', "Service créé : {$service->nom} ({$service->code})", $service);

        return response()->json(['success' => true, 'data' => $service], 201);
    }

    public function update(Request $request, int $id)
    {
        $service = Service::find($id);

        if (!$service) {
            return response()->json(['success' => false, 'message' => 'Service introuvable'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|required|string|max:150',
            'description' => 'sometimes|nullable|string|max:2000',
            'ordre' => 'sometimes|integer|min:0',
            'est_actif' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $service->update($validator->validated());

        JournalActivite::enregistrer('service.modifier', "Service modifié : {$service->nom} ({$service->code})", $service);

        return response()->json(['success' => true, 'data' => $service->fresh()]);
    }

    /**
     * Supprime un service avec ses tarifs et ses pièces requises. Refusé si
     * des demandes existent déjà pour ce service : il faut alors le
     * désactiver pour le retirer du site sans perdre l'historique.
     */
    public function destroy(int $id)
    {
        $service = Service::find($id);

        if (!$service) {
            return response()->json(['success' => false, 'message' => 'Service introuvable'], 404);
        }

        if (Demande::where('type', $service->code)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Des demandes existent pour ce service : désactivez-le plutôt que de le supprimer.',
            ], 409);
        }

        DB::transaction(function () use ($service) {
            Tarif::where('type_demande', $service->code)->delete();
            DocumentTypeRequis::where('type_demande', $service->code)->delete();
            $service->delete();
        });

        JournalActivite::enregistrer('service.supprimer', "Service supprimé : {$service->nom} ({$service->code})");

        return response()->json(['success' => true, 'message' => 'Service supprimé']);
    }

    private function genererCode(string $nom): string
    {
        $base = mb_substr(Str::slug($nom, '_'), 0, 40) ?: 'service';
        $code = $base;
        $i = 2;

        while (Service::where('code', $code)->exists()) {
            $code = $base . '_' . $i++;
        }

        return $code;
    }
}
