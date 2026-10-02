<?php

namespace App\Providers;

use App\Models\Demande;
use App\Models\DemandeDocument;
use App\Models\Paiement;
use App\Observers\DemandeDocumentObserver;
use App\Observers\DemandeObserver;
use App\Observers\PaiementObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        $this->configureRateLimiting();
    }

    /**
     * Limiteurs de débit. Appliqués via ->middleware('throttle:<nom>') dans
     * routes/api.php (le limiteur 'api' l'est globalement via throttleApi()
     * dans bootstrap/app.php).
     *
     * Les clés sont basées sur l'IP : si l'API tourne derrière un proxy ou
     * Cloudflare, configurer les proxies de confiance (trustProxies) sinon
     * tous les visiteurs partageront la même IP — donc le même quota.
     */
    protected function configureRateLimiting(): void
    {
        // Réponse 429 JSON homogène avec le reste de l'API.
        $trop = fn (Request $request, array $headers) => response()->json([
            'success' => false,
            'message' => 'Trop de tentatives. Veuillez patienter quelques instants avant de réessayer.',
        ], 429, $headers);

        // Filet global : large, il ne gêne pas l'usage normal (un guichet
        // derrière une même IP reste confortable).
        RateLimiter::for('api', fn (Request $request) =>
            Limit::perMinute(240)->by('api:' . $request->ip())->response($trop)
        );

        // Connexions (staff et ressortissants) : freine le brute-force de
        // mot de passe, par couple IP+email puis par IP seule.
        RateLimiter::for('login', function (Request $request) use ($trop) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('login:' . $request->ip() . '|' . $email)->response($trop),
                Limit::perMinute(20)->by('login-ip:' . $request->ip())->response($trop),
            ];
        });

        // Liens à jeton (vérification d'email, activation de compte admin,
        // réinitialisation) : empêche de deviner des jetons.
        RateLimiter::for('token-link', fn (Request $request) =>
            Limit::perMinute(10)->by('token:' . $request->ip())->response($trop)
        );

        // Routes qui ENVOIENT un email à une adresse saisie par l'appelant
        // (mot de passe oublié, renvoi de vérification) : évite de servir
        // de relais pour spammer un tiers.
        RateLimiter::for('email-send', function (Request $request) use ($trop) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(3)->by('mail:' . $request->ip() . '|' . $email)->response($trop),
                Limit::perHour(10)->by('mail-ip:' . $request->ip())->response($trop),
            ];
        });

        // Formulaires publics (contact, newsletter, inscription) : anti-spam
        // et anti-bourrage de la base.
        RateLimiter::for('public-form', fn (Request $request) => [
            Limit::perMinute(5)->by('form:' . $request->ip())->response($trop),
            Limit::perHour(30)->by('form-hour:' . $request->ip())->response($trop),
        ]);
    }
}
