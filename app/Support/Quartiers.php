<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Quartiers proposés à l'inscription, par commune (clé = nom de commune
 * de Ressortissant::VILLES_BENIN). Source : database/data/quartiers.json,
 * à compléter commune par commune. Une commune absente du fichier n'a pas
 * de liste : le formulaire retombe sur une saisie libre.
 */
class Quartiers
{
    private static ?array $cache = null;

    private static function toutes(): array
    {
        if (self::$cache === null) {
            $chemin = database_path('data/quartiers.json');
            $donnees = is_file($chemin) ? json_decode(file_get_contents($chemin), true) : null;
            self::$cache = is_array($donnees) ? $donnees : [];
        }

        return self::$cache;
    }

    private static function cle(string $texte): string
    {
        return Str::lower(Str::ascii(trim($texte)));
    }

    /** Liste des quartiers connus d'une commune (vide si aucune liste). */
    public static function pour(?string $ville): array
    {
        if (!$ville) {
            return [];
        }

        foreach (self::toutes() as $commune => $quartiers) {
            if (self::cle($commune) === self::cle($ville)) {
                return array_values($quartiers);
            }
        }

        return [];
    }

    /**
     * Ramène un quartier saisi à son orthographe officielle quand il figure
     * dans la liste (sans tenir compte de la casse ni des accents), afin que
     * la carte regroupe bien les ressortissants d'un même quartier. Un quartier
     * absent de la liste est conservé tel que saisi.
     */
    public static function normaliser(?string $ville, ?string $quartier): ?string
    {
        if ($quartier === null || trim($quartier) === '') {
            return $quartier;
        }

        foreach (self::pour($ville) as $connu) {
            if (self::cle($connu) === self::cle($quartier)) {
                return $connu;
            }
        }

        return trim($quartier);
    }
}
