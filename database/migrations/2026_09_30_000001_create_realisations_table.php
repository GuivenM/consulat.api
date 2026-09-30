<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réalisations de la communauté et éléments de culture & patrimoine
 * (ex. le club des sapeurs), gérés depuis le dashboard admin et affichés
 * sur les pages publiques Communauté et Culture & Patrimoine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realisations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();

            $table->enum('rubrique', ['communaute', 'culture_patrimoine']);
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('photo')->nullable();
            $table->date('date_realisation')->nullable();
            $table->boolean('publie')->default(true);
            $table->unsignedInteger('ordre')->default(0);

            $table->timestamps();

            $table->index(['entity_id', 'rubrique', 'publie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realisations');
    }
};
