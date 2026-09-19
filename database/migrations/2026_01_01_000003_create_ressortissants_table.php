<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre consulaire. Repris du modèle `membres` d'AJDCB pour sa mécanique
 * authentifiable déjà éprouvée (email, password, email_verified_at,
 * derniere_connexion, activation_token pour la vérification d'email),
 * étendu avec l'état civil et la localisation nécessaires à un consulat.
 *
 * Un ressortissant s'inscrit lui-même (self-service) ; le compte est actif
 * dès la vérification de son email — pas de validation admin préalable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ressortissants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            // Numéro d'immatriculation au registre, généré à l'inscription
            // (ex. COG-BEN-2026-00042).
            $table->string('numero_registre')->nullable()->unique();

            // --- Identité ---
            $table->string('nom');
            $table->string('prenom');
            $table->string('photo')->nullable();
            $table->enum('sexe', ['M', 'F'])->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('nationalite')->default('Congolaise');
            $table->string('profession')->nullable();
            $table->enum('situation_matrimoniale', ['celibataire', 'marie', 'divorce', 'veuf'])->nullable();

            // --- Pièce d'identité présentée à l'inscription ---
            $table->enum('type_piece', ['passeport', 'cni', 'carte_consulaire', 'autre'])->nullable();
            $table->string('numero_piece')->nullable();
            $table->date('date_expiration_piece')->nullable();

            // --- Contact et réseaux ---
            $table->string('whatsapp')->nullable();
            $table->string('telephone')->nullable();

            // --- Localisation (jusqu'au quartier, cf. carte interactive) ---
            $table->string('ville')->nullable();
            $table->string('commune')->nullable();
            $table->string('quartier')->nullable();
            $table->text('adresse')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('date_arrivee')->nullable(); // arrivée dans le pays d'accueil

            // --- Contact d'urgence ---
            $table->string('contact_urgence_nom')->nullable();
            $table->string('contact_urgence_telephone')->nullable();

            // --- Authentification (espace membre) ---
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('derniere_connexion')->nullable();
            $table->string('activation_token')->nullable()->unique(); // vérification d'email
            $table->timestamp('activation_token_expire_at')->nullable();

            $table->enum('statut', ['actif', 'inactif', 'suspendu'])->default('actif');
            $table->text('motif_inactivation')->nullable();

            $table->timestamps();

            $table->index(['entity_id', 'ville']);
            $table->index(['entity_id', 'quartier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ressortissants');
    }
};
