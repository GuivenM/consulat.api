<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DocumentTypeRequis;
use App\Models\Service;
use App\Models\Tarif;

/**
 * Vue publique (vitrine) des services consulaires : pour chaque
 * type_demande, une fourchette de prix (min/max sur les délais actifs)
 * et la liste des délais proposés — jamais le montant exact par délai,
 * qui reste réservé à l'espace admin et à l'espace membre connecté
 * (voir TarifController, dont la route reste volontairement privée).
 *
 * Les pièces à fournir n'ont pas cette restriction : elles sont
 * renvoyées en détail pour permettre au visiteur de préparer son
 * dossier avant de créer un compte.
 */
class ServicesPubliqueController extends Controller
{
    public function index()
    {
        $tarifsParType = Tarif::actif()
            ->get()
            ->sortBy(fn (Tarif $t) => Tarif::rang($t->delai))
            ->groupBy('type_demande')
            ->map(function ($tarifs) {
                return [
                    'devise' => $tarifs->first()->devise,
                    'montant_min' => (float) $tarifs->min('montant'),
                    'montant_max' => (float) $tarifs->max('montant'),
                    'delais' => $tarifs
                        ->map(fn (Tarif $t) => [
                            'delai' => $t->delai,
                            'label' => Tarif::DELAIS[$t->delai] ?? $t->delai,
                        ])
                        ->unique('delai')
                        ->values(),
                ];
            });

        $piecesParType = DocumentTypeRequis::actif()
            ->orderBy('type_demande')
            ->orderBy('ordre')
            ->get()
            ->groupBy('type_demande')
            ->map(function ($pieces) {
                return $pieces->map(fn (DocumentTypeRequis $d) => [
                    'label' => $d->label,
                    'aide' => $d->aide,
                    'obligatoire' => $d->obligatoire,
                    'nombre_requis' => $d->nombre_requis,
                    'formats_acceptes' => $d->formats_acceptes_liste,
                ])->values();
            });

        // La liste des services (et leur ordre) vient de l'espace admin :
        // seuls les services actifs sont publiés.
        $services = Service::actif()
            ->orderBy('ordre')
            ->orderBy('id')
            ->get()
            ->map(fn (Service $s) => [
                'type_demande' => $s->code,
                'nom' => $s->nom,
                'description' => $s->description,
                'tarif' => $tarifsParType->get($s->code),
                'pieces_requises' => $piecesParType->get($s->code, collect()),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }
}
