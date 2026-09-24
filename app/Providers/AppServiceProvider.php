<?php

namespace App\Providers;

use App\Models\Demande;
use App\Models\DemandeDocument;
use App\Models\Paiement;
use App\Observers\DemandeDocumentObserver;
use App\Observers\DemandeObserver;
use App\Observers\PaiementObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // demandes.paiement_statut est un miroir de paiements ; il n'est
        // recalculé que par cet observer, jamais à la main dans un
        // contrôleur (voir PaiementObserver).
        Paiement::observe(PaiementObserver::class);

        // Notifications email au ressortissant (changement d'étape du
        // dossier, pièce refusée).
        Demande::observe(DemandeObserver::class);
        DemandeDocument::observe(DemandeDocumentObserver::class);
    }
}
