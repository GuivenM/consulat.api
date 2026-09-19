<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actualités & Médiathèque (page unique fusionnée de la vitrine).
 * `image` est nullable : la création passe désormais exclusivement par la
 * galerie `actualite_photos` (voir ActualiteController::store).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actualites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();

            $table->string('titre');
            $table->string('slug')->unique();
            $table->text('description');
            $table->longText('contenu');
            $table->string('image')->nullable();

            $table->enum('type', ['actualite', 'evenement', 'education', 'culture']);
            $table->string('categorie')->nullable();
            $table->datetime('date_publicacion')->nullable();
            $table->datetime('date_evenement')->nullable();
            $table->string('lieu_evenement')->nullable();

            $table->string('auteur')->default('Consulat');
            $table->string('source')->nullable();
            $table->integer('vues')->default(0);
            $table->json('tags')->nullable();
            $table->boolean('est_a_la_une')->default(false);

            $table->enum('statut', ['publie', 'brouillon'])->default('publie');
            // Lien du post une fois publié sur la Page Facebook — sert aussi de
            // marqueur "déjà partagé" pour éviter les doublons depuis l'admin.
            $table->string('facebook_post_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actualites');
    }
};
