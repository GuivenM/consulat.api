<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Socle multi-tenant. Une entité = un poste consulaire ou diplomatique
 * (consulat honoraire, consulat général, ambassade) d'un pays donné dans
 * un pays d'accueil donné.
 *
 * En V1 une seule entité réelle existe (République du Congo — Bénin),
 * mais toutes les tables métier portent déjà entity_id : aucune migration
 * lourde ne sera nécessaire pour accueillir la deuxième.
 *
 * L'entité par défaut est insérée ici (id = 1) pour que les migrations
 * suivantes puissent ajouter des colonnes entity_id NOT NULL sans casser
 * les données existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->id();

            $table->string('slug')->unique();               // congo-benin
            $table->string('nom');                          // Consulat Honoraire de la République du Congo au Bénin
            $table->string('nom_court')->nullable();        // Consulat du Congo au Bénin
            $table->string('type')->default('consulat_honoraire'); // consulat_honoraire | consulat_general | ambassade
            $table->string('pays_represente');              // République du Congo
            $table->string('code_pays_represente', 3)->nullable(); // COG
            $table->string('pays_accueil');                 // Bénin
            $table->string('code_pays_accueil', 3)->nullable();    // BEN
            $table->string('ville_siege')->nullable();      // Cotonou

            // Identité visuelle et contact, servis à la vitrine
            $table->string('logo')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->text('adresse')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('devise', 10)->default('XOF');
            $table->string('locale', 5)->default('fr');
            $table->string('fuseau_horaire')->default('Africa/Porto-Novo');

            $table->boolean('est_active')->default(true);
            // Visible par les autres entités du réseau (fonctionnalités V2)
            $table->boolean('partage_reseau')->default(false);

            $table->timestamps();
        });

        DB::table('entities')->insert([
            'id' => 1,
            'slug' => 'congo-benin',
            'nom' => 'Consulat Honoraire de la République du Congo au Bénin',
            'nom_court' => 'Consulat du Congo au Bénin',
            'type' => 'consulat_honoraire',
            'pays_represente' => 'République du Congo',
            'code_pays_represente' => 'COG',
            'pays_accueil' => 'Bénin',
            'code_pays_accueil' => 'BEN',
            'ville_siege' => 'Cotonou',
            'est_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
