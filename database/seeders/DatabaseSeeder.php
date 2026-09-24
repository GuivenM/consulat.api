<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Données de référence uniquement (tarifs, pièces requises) : aucun compte
 * n'est créé ici, donc ce seeder est sans danger en production.
 *
 * Le premier super_admin se crée à la main (voir le README), les suivants
 * depuis l'écran Utilisateurs de l'admin. Pour un compte de test local :
 * TestAdminSeeder.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ConsulatCongoBeninSeeder::class);
    }
}
