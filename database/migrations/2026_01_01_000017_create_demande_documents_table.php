<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pièces jointes d'une demande. `code_document` reprend le code défini
 * dans `document_types_requis`, ce qui permet de vérifier la complétude
 * d'un dossier par simple comparaison des codes, sans logique en dur.
 *
 * L'agent peut rejeter une pièce individuellement (illisible, périmée)
 * et demander un nouvel envoi sans rejeter tout le dossier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demande_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('demande_id')->constrained('demandes')->cascadeOnDelete();
            $table->string('code_document');                // photo_identite, copie_passeport...
            $table->string('label')->nullable();            // libellé figé au moment du dépôt

            $table->string('fichier');                      // chemin sur le disque privé
            $table->string('nom_original')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedInteger('taille')->nullable();  // octets

            // en_attente | valide | rejete
            $table->string('statut')->default('en_attente');
            $table->text('motif_rejet')->nullable();
            $table->foreignId('verifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verifie_at')->nullable();

            $table->timestamps();

            $table->index(['demande_id', 'code_document']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demande_documents');
    }
};
