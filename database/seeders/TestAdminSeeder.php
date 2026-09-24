<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de TEST uniquement — crée un compte super_admin avec un mot de
 * passe connu pour tester rapidement la section admin en local.
 *
 * NE JAMAIS exécuter ce seeder en production (mot de passe faible et
 * prévisible). Utilisation :
 *   php artisan db:seed --class=TestAdminSeeder
 */
class TestAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Garde-fou : ce compte a un mot de passe connu de tous.
        if (app()->isProduction()) {
            $this->command?->error('TestAdminSeeder refusé en production.');
            return;
        }

        $email = 'test@ajdcb.org';
        $password = 'Test1234!';

        User::updateOrCreate(
            ['email' => $email],
            [
                'nom' => 'Test',
                'prenom' => 'Admin',
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'telephone' => '+22900000000',
                'est_actif' => true,
            ]
        );

        $this->command?->warn("Compte de test créé : {$email} / {$password}");
    }
}
