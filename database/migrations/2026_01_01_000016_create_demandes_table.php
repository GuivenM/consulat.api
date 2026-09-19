<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table générique des demandes consulaires : le `type` distingue les
 * prestations (carte consulaire, laissez-passer, et les types qu'ajouteront
 * d'autres entités) sans nouvelle table ni nouvelle migration.
 *
 * `montant` est recopié depuis la grille `tarifs` au moment du dépôt pour
 * figer le prix appliqué, même si la grille évolue ensuite.
 *
 * `paiement_statut` est un miroir dénormalisé de la table `paiements`
 * (source de vérité des encaissements, en ligne comme au guichet). Il
 * existe uniquement pour filtrer et afficher sans jointure ; il est
 * recalculé par l'observer du modèle Paiement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('ressortissant_id')->constrained('ressortissants')->cascadeOnDelete();

            $table->string('numero_dossier')->unique();     // CB-2026-000123
            $table->string('type');                         // carte_consulaire | laissez_passer | ...
            $table->string('delai');                        // 3_jours | 24h | meme_jour

            // recu -> en_traitement -> pret -> retire, ou rejete à tout moment
            $table->string('statut')->default('recu');
            $table->text('motif_rejet')->nullable();

            $table->decimal('montant', 10, 2);
            $table->string('devise', 10)->default('XOF');
            // en_attente | partiel | paye
            $table->string('paiement_statut')->default('en_attente');

            // Suivi du traitement
            $table->timestamp('date_depot')->useCurrent();
            $table->timestamp('date_disponibilite_prevue')->nullable();
            $table->timestamp('date_pret')->nullable();
            $table->timestamp('date_retrait')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();

            // Champs propres à un type de demande (motif du voyage pour un
            // laissez-passer, destination, etc.) : évite une table par type.
            $table->json('donnees_specifiques')->nullable();

            $table->text('note_interne')->nullable();       // visible admin/agent uniquement

            $table->timestamps();
            $table->softDeletes();

            $table->index(['entity_id', 'statut']);
            $table->index(['entity_id', 'type', 'statut']);
            $table->index(['ressortissant_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};
