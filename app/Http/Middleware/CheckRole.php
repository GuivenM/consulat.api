<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], 401);
        }

        // Défense en profondeur : seuls les comptes staff (User) ont un rôle.
        // Un token de Ressortissant ne doit jamais atteindre ce point avec
        // un rôle exploitable, même si un attribut `role` apparaissait un jour.
        if (!$user instanceof \App\Models\User || !in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé pour votre rôle'
            ], 403);
        }

        return $next($request);
    }
}