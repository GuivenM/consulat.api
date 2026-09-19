<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formulaire de contact de la page Contact. `objet` a été adapté au
 * contexte consulaire : 'adhesion' (AJDCB) est remplacé par
 * 'service_consulaire' (question sur une démarche, hors dépôt de dossier
 * qui passe par l'espace membre — cf. le widget "Besoin d'aide ?").
 *
 * organisation/type_organisation/... ne sont renseignés que pour un
 * message d'objet 'partenariat' ; partenaire_id trace la conversion en
 * fiche Partenaire pour ne pas la dupliquer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();

            $table->string('nom');
            $table->string('prenom');
            $table->string('email');
            $table->string('telephone');
            $table->enum('objet', ['question', 'service_consulaire', 'partenariat', 'urgence', 'autre']);
            $table->text('message');

            // Renseignés uniquement quand objet = 'partenariat'
            $table->string('organisation')->nullable();
            $table->enum('type_organisation', ['institution', 'ong', 'entreprise', 'media', 'universite', 'association'])
                ->nullable();
            $table->string('secteur_activite')->nullable();
            $table->string('pays')->nullable();
            $table->string('ville')->nullable();
            $table->string('site_web')->nullable();

            $table->enum('statut', ['non_lu', 'lu', 'repondu'])->default('non_lu');
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('partenaire_id')->nullable()->constrained('partenaires')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
