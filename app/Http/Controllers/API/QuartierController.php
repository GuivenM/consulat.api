<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\Quartiers;
use Illuminate\Http\Request;

class QuartierController extends Controller
{
    /**
     * Quartiers d'une commune, pour le formulaire d'inscription et la fiche
     * admin. Public et statique. Liste vide = pas de liste pour cette commune.
     *
     * GET /v1/quartiers?ville=Cotonou
     */
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => Quartiers::pour($request->query('ville')),
        ]);
    }
}
