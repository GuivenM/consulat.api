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

    private static ?array $geo = null;

    /**
     * Coordonnées approximatives (centre du quartier / de la commune), pour
     * placer sur la carte admin les ressortissants sans position GPS.
     * Source : database/data/quartiers_coordonnees.json ; un quartier absent
     * du fichier retombe sur le centre de sa commune.
     */
    private static function geo(): array
    {
        if (self::$geo === null) {
            $chemin = database_path('data/quartiers_coordonnees.json');
            $donnees = is_file($chemin) ? json_decode(file_get_contents($chemin), true) : null;
            self::$geo = is_array($donnees) ? $donnees : [];
        }

        return self::$geo;
    }

    /** [latitude, longitude] du centre d'une commune, ou null. */
    public static function centreVille(?string $ville): ?array
    {
        if (!$ville) {
            return null;
        }

        foreach (self::geo()['villes'] ?? [] as $commune => $coords) {
            if (self::cle($commune) === self::cle($ville)) {
                return $coords;
            }
        }

        return null;
    }

    /** [latitude, longitude] d'un quartier connu, ou null. */
    public static function coordonnees(?string $ville, ?string $quartier): ?array
    {
        if (!$ville || !$quartier) {
            return null;
        }

        foreach (self::geo()['quartiers'] ?? [] as $commune => $liste) {
            if (self::cle($commune) !== self::cle($ville)) {
                continue;
            }
            foreach ($liste as $nom => $coords) {
                if (self::cle($nom) === self::cle($quartier)) {
                    return $coords;
                }
            }
        }

        return null;
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
