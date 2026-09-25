<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Message;
use App\Models\Paiement;
use App\Models\Ressortissant;

/**
 * Chiffres clés de l'espace admin, pour les trois rôles (super_admin,
 * admin, agent) : ce dont un agent a besoin en arrivant le matin. Un seul
 * appel plutôt que les sept requêtes que faisait l'ancien Dashboard.tsx
 * (liste complète des messages, actualités, guide, partenaires x2) pour
 * n'en tirer que des comptages.
 *
 * Le scope d'entité (BelongsToEntity) s'applique normalement : un agent ne
 * voit que les chiffres de son entité.
 *
 * SUM(CASE WHEN ... THEN 1 ELSE 0 END) plutôt que COUNT(*) FILTER (WHERE
 * ...) : la syntaxe FILTER n'existe pas en MySQL/MariaDB (testé sur
 * MariaDB 10.11), seulement en PostgreSQL/SQLite.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $demandes = Demande::selectRaw("
                SUM(CASE WHEN statut IN ('recu', 'en_traitement') THEN 1 ELSE 0 END) AS a_traiter,
                SUM(CASE WHEN statut = 'pret' THEN 1 ELSE 0 END) AS pretes,
                SUM(CASE
                    WHEN statut IN ('recu', 'en_traitement')
                    AND date_disponibilite_prevue IS NOT NULL
                    AND date_disponibilite_prevue < NOW()
                    THEN 1 ELSE 0
                END) AS en_retard
            ")
            ->first();

        $encaissements = Paiement::selectRaw("
                COALESCE(SUM(CASE WHEN DATE(date_encaissement) = CURDATE() THEN montant ELSE 0 END), 0) AS jour,
                COALESCE(SUM(CASE
                    WHEN YEAR(date_encaissement) = YEAR(CURDATE())
                    AND MONTH(date_encaissement) = MONTH(CURDATE())
                    THEN montant ELSE 0
                END), 0) AS mois
            ")
            ->where('statut', 'reussi')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'demandes' => [
                    'a_traiter' => (int) ($demandes->a_traiter ?? 0),
                    'pretes' => (int) ($demandes->pretes ?? 0),
                    'en_retard' => (int) ($demandes->en_retard ?? 0),
                ],
                'encaissements' => [
                    'jour' => (float) ($encaissements->jour ?? 0),
                    'mois' => (float) ($encaissements->mois ?? 0),
                    'devise' => 'XOF',
                ],
                'messages_non_lus' => Message::whereNull('lu_le')->count(),
                'ressortissants_inscrits' => Ressortissant::actif()->inscrits()->count(),
            ],
        ]);
    }
}
