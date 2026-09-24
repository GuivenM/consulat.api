<?php

namespace App\Observers;

use App\Mail\PieceRejetee;
use App\Models\Demande;
use App\Models\DemandeDocument;
use App\Models\Ressortissant;
use App\Support\MailRessortissant;

/**
 * Prévient le ressortissant quand une pièce est refusée, avec le motif
 * saisi par l'agent. Une pièce qui repasse en 'en_attente' (redépôt) ou
 * en 'valide' ne déclenche rien.
 */
class DemandeDocumentObserver
{
    public function updated(DemandeDocument $document): void
    {
        if (!$document->wasChanged('statut') || $document->statut !== 'rejete') {
            return;
        }

        $demande = Demande::withoutGlobalScope('entity')->find($document->demande_id);

        if (!$demande) {
            return;
        }

        $ressortissant = Ressortissant::withoutGlobalScope('entity')
            ->with('entity')
            ->find($demande->ressortissant_id);

        if (!$ressortissant) {
            return;
        }

        MailRessortissant::envoyer(
            $ressortissant,
            new PieceRejetee($document, $demande, $ressortissant),
            "pièce {$document->code_document} du dossier {$demande->numero_dossier}"
        );
    }
}
