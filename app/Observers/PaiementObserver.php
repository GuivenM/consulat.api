<?php

namespace App\Observers;

use App\Models\Demande;
use App\Models\Paiement;

/**
 * `paiements` est la source de vérité des encaissements ; ce que voit
 * l'admin sur une demande (`paiement_statut`) n'en est qu'un miroir.
 * Recalculé ici, jamais mis à jour à la main dans un contrôleur.
 *
 * Un paiement en_ligne passe par en_attente (webhook FedaPay pas encore
 * reçu) — la demande reste alors 'en_attente' jusqu'à confirmation.
 */
class PaiementObserver
{
    public function created(Paiement $paiement): void
    {
        $this->recalculer($paiement);
    }

    public function updated(Paiement $paiement): void
    {
        if ($paiement->wasChanged('statut')) {
            $this->recalculer($paiement);
        }
    }

    public function deleted(Paiement $paiement): void
    {
        $this->recalculer($paiement);
    }

    private function recalculer(Paiement $paiement): void
    {
        if (!$paiement->demande_id) {
            return;
        }

        $demande = Demande::withoutGlobalScope('entity')->find($paiement->demande_id);
        if (!$demande) {
            return;
        }

        $totalReussi = Paiement::withoutGlobalScope('entity')
            ->where('demande_id', $demande->id)
            ->where('statut', 'reussi')
            ->sum('montant');

        $statut = 'en_attente';
        if ($totalReussi >= $demande->montant) {
            $statut = 'paye';
        } elseif ($totalReussi > 0) {
            $statut = 'partiel';
        }

        if ($demande->paiement_statut !== $statut) {
            $demande->paiement_statut = $statut;
            $demande->saveQuietly(); // évite de redéclencher un éventuel observer Demande
        }
    }
}
