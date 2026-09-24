<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le modèle Message (fillable, casts, marquerCommeLu(), repondre()) et le
 * front attendent `reponse`, `date_reponse` et `lu_le`, mais la migration
 * de création de `messages` ne les déclarait pas : répondre à un message
 * ou le marquer comme lu échouait avec « Unknown column ». Migration
 * séparée (et gardée par hasColumn) plutôt qu'une retouche de la
 * migration d'origine, car la table existe déjà en production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'lu_le')) {
                $table->timestamp('lu_le')->nullable()->after('statut');
            }
            if (!Schema::hasColumn('messages', 'reponse')) {
                $table->text('reponse')->nullable()->after('message');
            }
            if (!Schema::hasColumn('messages', 'date_reponse')) {
                $table->timestamp('date_reponse')->nullable()->after('reponse');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            foreach (['date_reponse', 'reponse', 'lu_le'] as $colonne) {
                if (Schema::hasColumn('messages', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
