<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration dynamique des pièces à fournir, par type de demande et par
 * entité. Ajouter ou retirer une pièce requise se fait depuis l'espace
 * admin, sans déploiement de code : le formulaire du front interroge cette
 * table au chargement et génère ses champs d'upload à la volée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types_requis', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('type_demande');                 // carte_consulaire | laissez_passer | ...
            $table->string('code_document');                // photo_identite | copie_passeport | fiche_remplie
            $table->string('label');                        // "Photo d'identité"
            $table->text('aide')->nullable();               // consigne affichée sous le champ
            $table->unsignedSmallInteger('ordre')->default(0);

            // Contraintes d'upload, appliquées côté front et revalidées côté API
            $table->unsignedSmallInteger('nombre_requis')->default(1); // ex. 2 photos d'identité
            $table->string('formats_acceptes')->default('jpg,jpeg,png,pdf');
            $table->unsignedInteger('taille_max_ko')->default(5120);

            $table->boolean('obligatoire')->default(true);
            $table->boolean('est_actif')->default(true);
            $table->timestamps();

            $table->unique(
                ['entity_id', 'type_demande', 'code_document'],
                'doc_requis_entite_type_code_unique'
            );
            $table->index(['entity_id', 'type_demande', 'ordre'], 'doc_requis_ordre_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types_requis');
    }
};
