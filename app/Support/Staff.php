<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Les routes publiques (site vitrine) n'ont pas de middleware d'auth, donc
 * `$request->user()` y est toujours null. Pour qu'elles puissent montrer
 * davantage à un membre du personnel connecté (brouillons, éléments
 * inactifs) sans le dupliquer en routes admin, on interroge explicitement
 * le guard sanctum : il lit le Bearer token s'il y en a un.
 *
 * `instanceof User` compte : Sanctum sert aussi les tokens des
 * ressortissants (autre modèle), qui ne sont pas du personnel.
 */
class Staff
{
    public static function est(Request $request): bool
    {
        $user = $request->user('sanctum');

        return $user instanceof User && (bool) $user->est_actif;
    }
}
