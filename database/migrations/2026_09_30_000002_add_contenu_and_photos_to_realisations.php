<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page de détail d'une réalisation : texte complet + galerie de photos
 * (même principe que les actualités). `photo` reste l'image de couverture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realisations', function (Blueprint $table) {
            $table->longText('contenu')->nullable()->after('description');
        });

        Schema::create('realisation_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('realisation_id')->constrained('realisations')->cascadeOnDelete();
            $table->string('chemin');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realisation_photos');

        Schema::table('realisations', function (Blueprint $table) {
            $table->dropColumn('contenu');
        });
    }
};
