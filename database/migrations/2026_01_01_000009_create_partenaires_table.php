<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repris tel quel d'AJDCB : sert la page Congo–Bénin & Diplomatie
 * économique (partenaires institutionnels, entreprises, secteurs porteurs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partenaires', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();

            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('site_web')->nullable();
            $table->enum('type', ['institution', 'ong', 'entreprise', 'media', 'universite', 'association'])->nullable();
            $table->string('secteur_activite')->nullable();
            $table->string('pays')->nullable();
            $table->string('ville')->nullable();
            $table->string('adresse')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->date('date_debut_partenariat')->nullable();
            $table->date('date_fin_partenariat')->nullable();
            $table->enum('niveau_partenariat', ['or', 'argent', 'bronze', 'institutionnel', 'technique'])->nullable();
            $table->json('domaines_intervention')->nullable();
            $table->enum('statut', ['actif', 'inactif'])->default('actif');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partenaires');
    }
};
