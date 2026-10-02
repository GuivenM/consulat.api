<?php

namespace App\Http\Middleware;

use App\Models\Ressortissant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Sanctum authentifie deux types de modèles avec le même mécanisme de
 * token : User (admin/agent) et Ressortissant (espace membre). `auth:sanctum`
 * seul accepte donc les deux. Ce middleware réserve une route à un type précis :
 *
 *   ->middleware(['auth:sanctum', 'actor:staff'])          // User uniquement
 *   ->middleware(['auth:sanctum', 'actor:ressortissant'])  // Ressortissant uniquement
 *
 * À placer APRÈS auth:sanctum.
 */
class EnsureActorType
{
    public function handle(Request $request, Closure $next, string $type)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié. Veuillez vous connecter.',
            ], 401);
        }

        $autorise = match ($type) {
            'staff' => $user instanceof User,
            'ressortissant' => $user instanceof Ressortissant,
            default => false, // type inconnu = refus (échec fermé)
        };

        if (!$autorise) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        return $next($request);
    }
}
