<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repris tel quel d'AJDCB pour l'Agenda. La billetterie payante
 * (prix/lien_billet) et les inscriptions (participants) restent en place
 * au niveau du champ mais la table pivot `participations` n'est pas
 * reconstruite : en V1 l'Agenda est une simple liste d'événements liée aux
 * Actualités, sans inscription (cf. document du projet, V2 pour le
 * calendrier complet avec inscription).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();

            $table->string('titre');
            $table->text('description')->nullable();
            $table->text('contenu')->nullable();
            $table->string('image')->nullable();

            $table->date('date_debut');
            $table->date('date_fin');
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();

            $table->string('lieu')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();

            $table->string('type')->nullable();
            $table->string('categorie')->nullable();

            $table->unsignedInteger('capacite_max')->nullable();
            $table->unsignedInteger('nombre_inscrits')->default(0);

            $table->decimal('prix', 10, 2)->nullable();
            $table->string('devise')->default('XOF');
            $table->string('lien_billet')->nullable();

            $table->string('organisateur')->nullable();
            $table->string('contact_organisateur')->nullable();
            $table->string('email_contact')->nullable();
            $table->string('telephone_contact')->nullable();

            $table->json('documents')->nullable();
            $table->enum('statut', ['publie', 'brouillon', 'annule'])->default('brouillon');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements');
    }
};
