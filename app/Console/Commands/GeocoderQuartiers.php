<?php

namespace App\Console\Commands;

use App\Support\Quartiers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Complète database/data/quartiers_coordonnees.json avec les quartiers de
 * quartiers.json qui n'ont pas encore de coordonnées, via OpenStreetMap
 * (Nominatim). Les résultats sont approximatifs : ils servent uniquement à
 * placer les ressortissants sans GPS sur la carte admin.
 *
 *   php artisan quartiers:geocoder            # simulation, affiche le résultat
 *   php artisan quartiers:geocoder --ecrire   # enregistre dans le fichier
 *
 * Ne remplace jamais une coordonnée déjà présente. Un résultat éloigné de
 * plus de 15 km du centre de la commune est rejeté (mauvaise homonymie).
 */
class GeocoderQuartiers extends Command
{
    protected $signature = 'quartiers:geocoder {--ecrire : Enregistrer les coordonnées trouvées}';

    protected $description = 'Géocode (OpenStreetMap) les quartiers sans coordonnées';

    public function handle(): int
    {
        $chemin = database_path('data/quartiers_coordonnees.json');
        $geo = is_file($chemin) ? json_decode(file_get_contents($chemin), true) : [];
        $geo = is_array($geo) ? $geo : [];
        $liste = json_decode(file_get_contents(database_path('data/quartiers.json')), true) ?: [];

        $trouves = 0;

        foreach ($liste as $ville => $quartiers) {
            $centre = Quartiers::centreVille($ville);
            if (!$centre) {
                $this->warn("$ville : pas de centre de commune dans quartiers_coordonnees.json, ignorée.");
                continue;
            }

            foreach ($quartiers as $quartier) {
                if (Quartiers::coordonnees($ville, $quartier)) {
                    continue;
                }

                sleep(1); // politique d'usage de Nominatim : 1 requête par seconde

                $reponse = Http::withHeaders(['User-Agent' => config('app.name', 'consulat') . ' (quartiers:geocoder)'])
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'format' => 'jsonv2',
                        'limit' => 1,
                        'countrycodes' => 'bj',
                        'q' => "$quartier, $ville, Bénin",
                    ]);

                $resultat = $reponse->json(0);
                if (!$resultat) {
                    $this->line("  ✗ $quartier : aucun résultat");
                    continue;
                }

                $lat = (float) $resultat['lat'];
                $lng = (float) $resultat['lon'];
                $distance = $this->distanceKm($centre[0], $centre[1], $lat, $lng);

                if ($distance > 15) {
                    $this->line(sprintf('  ✗ %s : rejeté (%.0f km du centre de %s)', $quartier, $distance, $ville));
                    continue;
                }

                $geo['quartiers'][$ville][$quartier] = [round($lat, 6), round($lng, 6)];
                $trouves++;
                $this->info(sprintf('  ✓ %s : %.6f, %.6f', $quartier, $lat, $lng));
            }
        }

        if ($this->option('ecrire') && $trouves > 0) {
            file_put_contents($chemin, json_encode($geo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            $this->info("$trouves quartier(s) enregistré(s) dans quartiers_coordonnees.json. Vérifiez-les avant de commiter.");
        } else {
            $this->comment("$trouves quartier(s) trouvé(s). Relancez avec --ecrire pour les enregistrer.");
        }

        return self::SUCCESS;
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(sqrt($a));
    }
}
