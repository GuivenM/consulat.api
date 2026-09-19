<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repris tel quel du "Guide" AJDCB : un mini-CMS en sections/sous-sections/
 * documents, réutilisé ici pour la page Services consulaires (visa,
 * légalisation, actes, pièces à fournir, tarifs, délais — en onglets) et,
 * si besoin, la FAQ. Aucune démarche ne se dépose depuis ces pages : elles
 * sont purement informatives, cf. document du projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_sections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();

            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('categorie')->nullable();
            $table->text('contenu')->nullable();
            $table->string('image')->nullable();
            $table->string('icone')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->enum('statut', ['publie', 'brouillon'])->default('brouillon');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_sections');
    }
};
