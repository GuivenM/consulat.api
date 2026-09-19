<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ressortissant;

/**
 * Carte interactive — vue publique (vitrine) : uniquement une agrégation
 * par ville, jamais de point individuel ni de donnée personnelle. Seuls
 * les ressortissants actifs comptent, pour refléter la communauté
 * réellement présente plutôt que des comptes suspendus/inactifs.
 *
 * Le géocodage des villes (communes du Bénin) est une donnée géographique
 * publique statique : elle est fournie par le frontend, pas ici.
 */
class CartePubliqueController extends Controller
{
    public function index()
    {
        $parVille = Ressortissant::where('statut', 'actif')
            ->whereNotNull('ville')
            ->selectRaw('ville, count(*) as total')
            ->groupBy('ville')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $parVille->sum('total'),
                'par_ville' => $parVille,
            ],
        ]);
    }
}
