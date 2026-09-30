<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inscription au registre : fichier de la pièce d'identité (PDF ou image,
 * disque privé), déclaration de possession de la carte consulaire, et
 * nouveau type de pièce « CIP Étranger ».
 */
return new class extends Migration
{
    private const AVEC_CIP = "'passeport','cni','cip_etranger','carte_consulaire','autre'";
    private const SANS_CIP = "'passeport','cni','carte_consulaire','autre'";

    public function up(): void
    {
        Schema::table('ressortissants', function (Blueprint $table) {
            $table->string('piece_fichier')->nullable()->after('date_expiration_piece');
            $table->string('piece_fichier_nom')->nullable()->after('piece_fichier');
            $table->boolean('possede_carte_consulaire')->nullable()->after('piece_fichier_nom');
            $table->string('numero_carte_consulaire')->nullable()->after('possede_carte_consulaire');
        });

        if ($this->estMysql()) {
            DB::statement('ALTER TABLE ressortissants MODIFY type_piece ENUM(' . self::AVEC_CIP . ') NULL');
        }
    }

    public function down(): void
    {
        if ($this->estMysql()) {
            DB::table('ressortissants')->where('type_piece', 'cip_etranger')->update(['type_piece' => 'autre']);
            DB::statement('ALTER TABLE ressortissants MODIFY type_piece ENUM(' . self::SANS_CIP . ') NULL');
        }

        Schema::table('ressortissants', function (Blueprint $table) {
            $table->dropColumn(['piece_fichier', 'piece_fichier_nom', 'possede_carte_consulaire', 'numero_carte_consulaire']);
        });
    }

    private function estMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
