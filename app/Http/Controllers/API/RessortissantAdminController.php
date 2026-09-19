<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ressortissant;
use Illuminate\Http\Request;

/**
 * Espace admin/agent : consultation du registre consulaire. L'inscription
 * elle-même reste en self-service (voir RessortissantAuthController) — cet
 * espace ne fait que lister/consulter, jamais créer un ressortissant à sa
 * place. Le scope entité (BelongsToEntity) s'applique automatiquement :
 * un admin ne voit jamais le registre d'une autre entité.
 */
class RessortissantAdminController extends Controller
{
    /**
     * Liste filtrable, pour le tableau de bord admin (onglet Registre).
     */
    public function index(Request $request)
    {
        $query = Ressortissant::query()->orderByDesc('created_at');

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }
        if ($request->filled('ville')) {
            $query->where('ville', $request->string('ville'));
        }
        if ($request->filled('quartier')) {
            $query->where('quartier', $request->string('quartier'));
        }
        if ($request->filled('recherche')) {
            $terme = $request->string('recherche');
            $query->where(function ($q) use ($terme) {
                $q->where('nom', 'like', "%{$terme}%")
                    ->orWhere('prenom', 'like', "%{$terme}%")
                    ->orWhere('numero_registre', 'like', "%{$terme}%")
                    ->orWhere('email', 'like', "%{$terme}%");
            });
        }

        $ressortissants = $query->paginate($request->integer('par_page', 20));

        return response()->json([
            'success' => true,
            'data' => $ressortissants->through(fn (Ressortissant $r) => $this->formater($r))->items(),
            'meta' => [
                'total' => $ressortissants->total(),
                'page' => $ressortissants->currentPage(),
                'dernier_page' => $ressortissants->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $ressortissant = Ressortissant::with(['demandes' => fn ($q) => $q->orderByDesc('date_depot')])
            ->find($id);

        if (!$ressortissant) {
            return response()->json([
                'success' => false,
                'message' => 'Ressortissant introuvable',
            ], 404);
        }

        $donnees = $this->formater($ressortissant, detaille: true);
        $donnees['demandes'] = $ressortissant->demandes->map(fn ($d) => [
            'id' => $d->id,
            'numero_dossier' => $d->numero_dossier,
            'type' => $d->type,
            'statut' => $d->statut,
            'statut_label' => $d->statut_label,
            'date_depot' => $d->date_depot?->format('d/m/Y H:i'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $donnees,
        ]);
    }

    /**
     * Chiffres clés pour le tableau de bord admin. Le détail par ville sert
     * aussi de socle pour la future carte interactive (agrégation par ville
     * côté vitrine publique).
     */
    public function statistiques()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total' => Ressortissant::count(),
                'par_statut' => Ressortissant::selectRaw('statut, count(*) as total')
                    ->groupBy('statut')
                    ->pluck('total', 'statut'),
                'par_ville' => Ressortissant::whereNotNull('ville')
                    ->selectRaw('ville, count(*) as total')
                    ->groupBy('ville')
                    ->orderByDesc('total')
                    ->pluck('total', 'ville'),
            ],
        ]);
    }

    /**
     * Export CSV du registre (espace admin), filtrable par statut.
     *
     * GET /v1/admin/ressortissants/export?statut=actif|inactif|suspendu
     */
    public function export(Request $request)
    {
        $query = Ressortissant::orderBy('nom');

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        $ressortissants = $query->get();

        return response()->streamDownload(function () use ($ressortissants) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'N° registre', 'Nom', 'Prénom', 'Sexe', 'Date de naissance', 'Nationalité',
                'Téléphone', 'WhatsApp', 'Email', 'Ville', 'Quartier', 'Statut', 'Inscrit le',
            ], ';');

            foreach ($ressortissants as $r) {
                fputcsv($handle, [
                    $r->numero_registre ?? '',
                    $r->nom,
                    $r->prenom,
                    $r->sexe ?? '',
                    $r->date_naissance?->format('d/m/Y') ?? '',
                    $r->nationalite ?? '',
                    $r->telephone ?? '',
                    $r->whatsapp ?? '',
                    $r->email ?? '',
                    $r->ville ?? '',
                    $r->quartier ?? '',
                    $r->statut,
                    $r->created_at->format('d/m/Y'),
                ], ';');
            }

            fclose($handle);
        }, 'registre-consulaire.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function formater(Ressortissant $r, bool $detaille = false): array
    {
        $donnees = [
            'id' => $r->id,
            'numero_registre' => $r->numero_registre,
            'nom' => $r->nom,
            'prenom' => $r->prenom,
            'nom_complet' => $r->nom_complet,
            'sexe' => $r->sexe,
            'photo_url' => $r->photo_url,
            'telephone' => $r->telephone,
            'whatsapp' => $r->whatsapp,
            'email' => $r->email,
            'ville' => $r->ville,
            'quartier' => $r->quartier,
            'statut' => $r->statut,
            'inscription_verifiee' => $r->inscription_verifiee,
            'created_at' => $r->created_at->format('d/m/Y H:i'),
        ];

        if ($detaille) {
            $donnees += [
                'date_naissance' => $r->date_naissance?->format('d/m/Y'),
                'lieu_naissance' => $r->lieu_naissance,
                'nationalite' => $r->nationalite,
                'profession' => $r->profession,
                'situation_matrimoniale' => $r->situation_matrimoniale,
                'type_piece' => $r->type_piece,
                'numero_piece' => $r->numero_piece,
                'date_expiration_piece' => $r->date_expiration_piece?->format('d/m/Y'),
                'adresse' => $r->adresse,
                'commune' => $r->commune,
                'latitude' => $r->latitude,
                'longitude' => $r->longitude,
                'date_arrivee' => $r->date_arrivee?->format('d/m/Y'),
                'contact_urgence_nom' => $r->contact_urgence_nom,
                'contact_urgence_telephone' => $r->contact_urgence_telephone,
                'motif_inactivation' => $r->motif_inactivation,
                'derniere_connexion' => $r->derniere_connexion?->format('d/m/Y H:i'),
            ];
        }

        return $donnees;
    }
}
