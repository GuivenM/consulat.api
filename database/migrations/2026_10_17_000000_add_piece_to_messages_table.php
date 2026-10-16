<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demande d'audience : pièce d'identité (passeport, CNI ou CIP Étranger)
 * jointe en fichier. Le fichier est stocké sur le disque privé et ne se
 * consulte que depuis l'espace admin (GET /v1/messages/{id}/piece).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('piece_type', 30)->nullable()->after('longitude');
            $table->string('piece_numero', 50)->nullable()->after('piece_type');
            $table->string('piece_fichier')->nullable()->after('piece_numero');
            $table->string('piece_fichier_nom')->nullable()->after('piece_fichier');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['piece_type', 'piece_numero', 'piece_fichier', 'piece_fichier_nom']);
        });
    }
};
