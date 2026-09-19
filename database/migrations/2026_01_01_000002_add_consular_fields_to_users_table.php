<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complète `users` (créée par la migration Laravel de base, avec role,
 * telephone, est_actif déjà en place) avec ce qui est propre au consulat :
 *
 * - entity_id : nullable, un super_admin de la plateforme (celui qui crée
 *   les entités et leurs administrateurs) n'appartient à aucune entité ;
 *   un admin ou un agent, lui, est toujours rattaché à une.
 * - activation_token / activation_token_expire_at : lien d'activation
 *   envoyé par email à la création d'un compte admin/agent par un
 *   super_admin, avant que celui-ci choisisse son mot de passe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('entity_id')->nullable()->after('id')
                ->constrained('entities')->nullOnDelete();
            $table->string('activation_token')->nullable()->after('password');
            $table->timestamp('activation_token_expire_at')->nullable()->after('activation_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entity_id');
            $table->dropColumn(['activation_token', 'activation_token_expire_at']);
        });
    }
};
