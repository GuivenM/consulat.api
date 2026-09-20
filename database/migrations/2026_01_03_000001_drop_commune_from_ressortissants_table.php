<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correction de schéma : `ville` et `commune` faisaient doublon.
 *
 * Au Bénin, la commune EST l'unité administrative de base (les 77 communes
 * listées dans Ressortissant::VILLES_BENIN) — il n'y a pas de "ville"
 * distincte au-dessus. La granularité demandée par le projet (registre
 * jusqu'au quartier) est : commune (colonne `ville`, valeur parmi
 * VILLES_BENIN) > quartier (`quartier`, texte libre, pas de liste fermée).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ressortissants', function (Blueprint $table) {
            $table->dropColumn('commune');
        });
    }

    public function down(): void
    {
        Schema::table('ressortissants', function (Blueprint $table) {
            $table->string('commune')->nullable()->after('ville');
        });
    }
};
