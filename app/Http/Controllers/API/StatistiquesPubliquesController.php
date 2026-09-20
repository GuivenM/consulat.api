<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ressortissant;
use App\Models\Partenaire;

class StatistiquesPubliquesController extends Controller
{
    /**
     * Chiffres clés réels affichés sur la page d'accueil (section "stats").
     * Volontairement minimal : uniquement ce qui est réellement mesurable
     * depuis les données existantes.
     *
     * Remplace l'ancien compteur "membres_actifs" (AJDCB, Membre::actif())
     * par le registre consulaire — la clé JSON change donc de nom, à
     * répercuter côté front (section stats de la page d'accueil).
     */
    public function index()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'ressortissants_inscrits' => Ressortissant::actif()->inscrits()->count(),
                    'partenaires_actifs' => Partenaire::actif()->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques publiques',
            ], 500);
        }
    }
}
