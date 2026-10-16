<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Demande d'audience (page du Consul Honoraire) et SOS (demande d'aide
 * urgente) passent par le circuit de messages existant : même boîte de
 * réception admin, mêmes notifications e-mail.
 *
 * - `objet` passe d'enum à string pour accueillir 'audience' et 'sos'
 *   (la liste des valeurs autorisées reste validée par MessageController).
 * - `email` devient nullable : une personne en détresse peut n'avoir que
 *   son téléphone. L'e-mail reste obligatoire pour tous les autres objets
 *   (voir MessageController::store).
 * - Colonnes propres à l'audience (date souhaitée) et au SOS (type d'aide,
 *   localisation, position GPS facultative).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('objet', 30)->change();
            $table->string('email')->nullable()->change();

            $table->date('date_souhaitee')->nullable()->after('message');
            $table->string('type_aide', 30)->nullable()->after('date_souhaitee');
            $table->string('localisation')->nullable()->after('type_aide');
            $table->decimal('latitude', 10, 7)->nullable()->after('localisation');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        // Les lignes audience/sos et les e-mails vides ne rentreraient pas
        // dans l'ancien schéma : on les ramène à des valeurs valides.
        DB::table('messages')->whereIn('objet', ['audience', 'sos'])->update(['objet' => 'autre']);
        DB::table('messages')->whereNull('email')->update(['email' => '']);

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['date_souhaitee', 'type_aide', 'localisation', 'latitude', 'longitude']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->enum('objet', ['question', 'service_consulaire', 'partenariat', 'urgence', 'autre'])->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
