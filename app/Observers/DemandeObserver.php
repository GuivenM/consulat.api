<?php

namespace App\Observers;

use App\Mail\DemandeStatutChange;
use App\Models\Demande;
use App\Models\Ressortissant;
use App\Support\MailRessortissant;

/**
 * Notifie le ressortissant quand son dossier change d'étape. Placé sur le
 * modèle plutôt que dans DemandeAdminController pour que toute future voie
 * de changement de statut (import, tâche planifiée, autre écran) prévienne
 * aussi, sans qu'on ait à y penser.
 *
 * 'recu' n'est pas notifié (c'est l'état initial, le ressortissant vient
 * de déposer lui-même) ni 'retire' (il est devant l'agent).
 */
class DemandeObserver
{
    private const STATUTS_NOTIFIES = ['en_traitement', 'pret', 'rejete'];

    public function updated(Demande $demande): void
    {
        if (!$demande->wasChanged('statut') || !in_array($demande->statut, self::STATUTS_NOTIFIES, true)) {
            return;
        }

        // Sans le scope d'entité : l'agent qui agit peut, dans le cas d'un
        // super_admin, se trouver dans le contexte d'une autre entité que
        // celle du dossier, et la relation renverrait alors null.
        $ressortissant = Ressortissant::withoutGlobalScope('entity')
            ->with('entity')
            ->find($demande->ressortissant_id);

        if (!$ressortissant) {
            return;
        }

        MailRessortissant::envoyer(
            $ressortissant,
            fn () => new DemandeStatutChange($demande, $ressortissant),
            "demande {$demande->numero_dossier} → {$demande->statut}"
        );
    }
}
