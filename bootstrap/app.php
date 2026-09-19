<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // app/Http/Kernel.php n'est plus utilisé depuis Laravel 11 (structure
        // basée sur bootstrap/app.php) : les alias 'admin' et 'role' qui y
        // étaient déclarés n'étaient donc jamais réellement enregistrés, et
        // aucune route ne les utilisait. On les déclare ici pour de vrai.
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'membre' => \App\Http\Middleware\EnsureIsMembre::class,
        ]);

        // Résout l'entité (consulat/ambassade) courante en tout début de
        // requête, avant que le moindre modèle scopé par BelongsToEntity
        // ne soit consulté — vitrine publique comme routes authentifiées.
        $middleware->api(append: [
            \App\Http\Middleware\SetCurrentEntity::class,
        ]);

        // ATTENTION : ce projet est une API pure (aucune route web/vue de
        // login n'existe — voir routes/web.php). Par défaut, Laravel
        // enregistre automatiquement un resolver de redirection "invité" qui
        // appelle route('login') dès qu'une requête n'annonce pas
        // explicitement 'Accept: application/json'. Comme aucune route
        // nommée 'login' n'existe ici, ce resolver plante lui-même avec une
        // RouteNotFoundException AVANT même que l'AuthenticationException ne
        // soit levée — impossible à intercepter proprement via
        // withExceptions() puisque le crash a lieu plus tôt dans le
        // pipeline. On désactive donc complètement ce comportement de
        // redirection : aucun invité n'est jamais redirigé, l'API renvoie
        // toujours un 401 JSON.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ATTENTION : ce projet est une API pure (aucune route web/vue de
        // login n'existe — voir routes/web.php). Avant ce correctif, une
        // requête vers une route protégée par 'auth:sanctum' SANS l'en-tête
        // 'Accept: application/json' (n'importe quel client qui l'oublie :
        // Postman par défaut, un simple curl, une app mobile mal configurée)
        // faisait planter Laravel avec une erreur 500 "Route [login] not
        // defined" au lieu de renvoyer proprement un 401 JSON.
        //
        // Pourquoi shouldRenderJsonWhen() seul ne suffit pas : le crash a
        // lieu AVANT que l'exception ne soit rendue. Le middleware Authenticate
        // essaie de construire une redirection vers route('login') dès que
        // la requête n'annonce pas explicitement attendre du JSON — cette
        // tentative de résolution de route jette elle-même une
        // RouteNotFoundException, qui court-circuite tout rendu JSON
        // configuré au niveau du handler d'exceptions.
        //
        // On enregistre donc un renderer dédié à AuthenticationException, qui
        // est prioritaire sur la logique de redirection par défaut et
        // renvoie toujours du JSON, quel que soit l'en-tête Accept envoyé.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié. Veuillez vous connecter.'
            ], 401);
        });

        // Filet de sécurité pour toutes les autres exceptions non gérées
        // explicitement : cette API n'ayant aucune vue HTML, on préfère
        // toujours une réponse JSON à une page d'erreur Blade.
        $exceptions->shouldRenderJsonWhen(function () {
            return true;
        });
    })->create();
