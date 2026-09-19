<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grille tarifaire configurable depuis l'espace admin : jamais de montant
 * en dur dans le code. Le montant d'une demande est résolu par la clé
 * (entity_id, type_demande, delai) au moment du dépôt, puis recopié dans
 * `demandes.montant` pour figer le prix appliqué ce jour-là.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('type_demande');                 // carte_consulaire | laissez_passer | ...
            $table->string('delai');                        // 3_jours | 24h | meme_jour
            $table->decimal('montant', 10, 2);
            $table->string('devise', 10)->default('XOF');

            // Délai de traitement en heures, pour calculer une date de
            // disponibilité prévisionnelle affichée au ressortissant.
            $table->unsignedSmallInteger('delai_heures')->nullable();

            $table->boolean('est_actif')->default(true);
            $table->timestamps();

            $table->unique(['entity_id', 'type_demande', 'delai'], 'tarifs_entite_type_delai_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifs');
    }
};
