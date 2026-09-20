<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Supprime la table `evenements`, héritée d'AJDCB. En V1, l'Agenda affiche
 * les Actualités de type "evenement" (colonnes date_evenement/
 * lieu_evenement déjà présentes sur `actualites`) — cf. document du
 * projet : "Agenda — liste des événements liée aux Actualités au début ;
 * calendrier complet avec inscription en V2".
 *
 * Cette table portait la billetterie/inscription (capacite_max, prix,
 * lien_billet, nombre_inscrits) prévue pour ce calendrier V2, mais
 * n'était alimentée par aucun flux d'inscription fonctionnel — son
 * contrôleur admin (EvenementController) et sa page front (AdminEvenements)
 * sont retirés dans le même mouvement. Si le calendrier V2 avec
 * inscription se construit un jour, il vaudra mieux repartir d'un schéma
 * neuf pensé pour l'inscription (participants, places restantes en temps
 * réel) plutôt que de ressusciter celui-ci tel quel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('evenements');
    }

    /**
     * Rollback volontairement non implémenté : recréer une table vide sans
     * les données perdues n'aurait aucun intérêt. Pour revenir en arrière,
     * repartir de 2026_01_01_000010_create_evenements_table.php.
     */
    public function down(): void
    {
        // no-op
    }
};
