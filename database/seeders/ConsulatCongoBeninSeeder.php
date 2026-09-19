<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Données de départ de l'entité République du Congo — Bénin :
 * grille tarifaire et pièces à fournir.
 *
 * Idempotent (updateOrInsert) : relançable après chaque ajustement validé
 * avec le consulat, sans créer de doublons.
 *
 * php artisan db:seed --class=ConsulatCongoBeninSeeder
 */
class ConsulatCongoBeninSeeder extends Seeder
{
    private const ENTITY_ID = 1;

    public function run(): void
    {
        $this->tarifs();
        $this->documentsRequis();
    }

    /**
     * Tarifs communiqués par le consulat, identiques pour la carte
     * consulaire et le laissez-passer. À confirmer pour la carte
     * consulaire : les montants ci-dessous sont ceux du laissez-passer.
     */
    private function tarifs(): void
    {
        $grille = [
            ['delai' => '3_jours',   'montant' => 30000, 'delai_heures' => 72],
            ['delai' => '24h',       'montant' => 35000, 'delai_heures' => 24],
            ['delai' => 'meme_jour', 'montant' => 40000, 'delai_heures' => 8],
        ];

        foreach (['laissez_passer', 'carte_consulaire'] as $type) {
            foreach ($grille as $ligne) {
                DB::table('tarifs')->updateOrInsert(
                    [
                        'entity_id' => self::ENTITY_ID,
                        'type_demande' => $type,
                        'delai' => $ligne['delai'],
                    ],
                    [
                        'montant' => $ligne['montant'],
                        'devise' => 'XOF',
                        'delai_heures' => $ligne['delai_heures'],
                        'est_actif' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * Pièces à fournir, identiques pour les deux types de demande.
     * Modifiables ensuite depuis l'espace admin, sans redéploiement.
     */
    private function documentsRequis(): void
    {
        $pieces = [
            [
                'code_document' => 'photo_identite',
                'label' => "Photo d'identité",
                'aide' => "Deux photos récentes, fond uni, format 4x4.",
                'nombre_requis' => 2,
                'formats_acceptes' => 'jpg,jpeg,png',
                'ordre' => 1,
            ],
            [
                'code_document' => 'piece_identite',
                'label' => "Copie du passeport ou de la carte nationale d'identité",
                'aide' => "Page d'identité du passeport, ou recto-verso de la CNI, en un seul fichier.",
                'nombre_requis' => 1,
                'formats_acceptes' => 'jpg,jpeg,png,pdf',
                'ordre' => 2,
            ],
            [
                'code_document' => 'fiche_remplie',
                'label' => 'Fiche de demande remplie et signée',
                'aide' => "Téléchargez la fiche depuis la page Services consulaires, remplissez-la, signez-la puis scannez-la.",
                'nombre_requis' => 1,
                'formats_acceptes' => 'pdf,jpg,jpeg,png',
                'ordre' => 3,
            ],
        ];

        foreach (['laissez_passer', 'carte_consulaire'] as $type) {
            foreach ($pieces as $piece) {
                DB::table('document_types_requis')->updateOrInsert(
                    [
                        'entity_id' => self::ENTITY_ID,
                        'type_demande' => $type,
                        'code_document' => $piece['code_document'],
                    ],
                    [
                        'label' => $piece['label'],
                        'aide' => $piece['aide'],
                        'ordre' => $piece['ordre'],
                        'nombre_requis' => $piece['nombre_requis'],
                        'formats_acceptes' => $piece['formats_acceptes'],
                        'taille_max_ko' => 5120,
                        'obligatoire' => true,
                        'est_actif' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
