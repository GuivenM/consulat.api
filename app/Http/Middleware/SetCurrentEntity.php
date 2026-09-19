<?php

namespace App\Http\Middleware;

use App\Support\CurrentEntity;
use Closure;
use Illuminate\Http\Request;

/**
 * Résout l'entité courante en tout début de requête, pour que le scope
 * global BelongsToEntity la trouve déjà prête au premier accès modèle.
 *
 * À enregistrer globalement (bootstrap/app.php ou Kernel selon la version
 * de Laravel) pour que toutes les routes API en bénéficient, y compris la
 * vitrine publique.
 */
class SetCurrentEntity
{
    public function handle(Request $request, Closure $next)
    {
        // Force la résolution maintenant : une éventuelle entité inactive
        // ou un slug ?entity= invalide sera signalé ici plutôt qu'au
        // milieu d'une requête métier.
        CurrentEntity::resolve();

        return $next($request);
    }
}
