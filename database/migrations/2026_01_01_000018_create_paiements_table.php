<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre unique des encaissements du consulat : `canal` distingue le
 * paiement en ligne (FedaPay) du paiement au guichet (espèces, mobile
 * money encaissé sur place par un agent — voir encaisse_par/numero_recu).
 *
 * C'est la source de vérité des paiements ; `demandes.paiement_statut`
 * n'en est qu'un miroir recalculé à chaque paiement réussi ou annulé
 * (observer du modèle Paiement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('demande_id')->constrained('demandes')->cascadeOnDelete();
            $table->foreignId('ressortissant_id')->nullable()->constrained('ressortissants')->nullOnDelete();

            // Coordonnées du payeur, utiles quand aucun ressortissant_id n'est
            // fourni côté guichet et pour contacter FedaPay.
            $table->string('nom_payeur')->nullable();
            $table->string('telephone_payeur')->nullable();
            $table->string('email_payeur')->nullable();

            $table->decimal('montant', 10, 2);
            $table->string('devise', 10)->default('XOF');

            // en_attente -> reussi | echoue | annule
            $table->string('statut')->default('en_attente');

            // en_ligne (FedaPay) | guichet
            $table->string('canal')->default('en_ligne');
            $table->string('mode')->nullable(); // especes | mobile_money | virement | carte

            // Renseignés uniquement pour un encaissement au guichet
            $table->foreignId('encaisse_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('numero_recu')->nullable()->unique();
            $table->timestamp('date_encaissement')->nullable();

            // Renseignés uniquement pour un paiement en ligne
            $table->string('fedapay_transaction_id')->nullable()->unique();
            $table->string('fedapay_reference')->nullable();
            $table->text('fedapay_derniere_reponse')->nullable();

            $table->timestamps();

            $table->index(['entity_id', 'statut']);
            $table->index(['demande_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
