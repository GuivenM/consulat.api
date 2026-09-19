<?php

namespace App\Support;

use App\Models\Entity;
use Illuminate\Support\Facades\Auth;

/**
 * Résout l'entité (consulat/ambassade) du contexte de la requête en cours.
 *
 * En V1 il n'existe qu'une seule entité réelle, mais toute la lecture/
 * écriture passe déjà par ce résolveur pour que brancher une deuxième
 * entité en V2 ne demande de toucher ni les modèles ni les contrôleurs.
 *
 * Ordre de résolution :
 * 1. Une valeur explicitement forcée (tests, commandes artisan, jobs) ;
 * 2. Le paramètre ?entity=<slug> de la requête, pour un front qui saurait
 *    déjà demander une entité précise (utile dès qu'il y en aura deux) ;
 * 3. L'entité du ressortissant ou de l'agent/admin authentifié ;
 * 4. L'entité par défaut de la plateforme (config('consulat.default_entity_slug')).
 *
 * Résolu une fois par requête et mis en cache en mémoire (voir
 * SetCurrentEntity, qui appelle resolve() au tout début du cycle de vie).
 */
class CurrentEntity
{
    private static ?Entity $forced = null;
    private static ?Entity $resolved = null;

    public static function resolve(): Entity
    {
        if (self::$forced) {
            return self::$forced;
        }

        if (self::$resolved) {
            return self::$resolved;
        }

        return self::$resolved = self::doResolve();
    }

    public static function id(): int
    {
        return self::resolve()->id;
    }

    /**
     * Force l'entité courante pour le reste du cycle de requête (ou du
     * process, en artisan/tests). À utiliser dans les commandes qui
     * doivent explicitement traiter une entité donnée.
     */
    public static function set(Entity $entity): void
    {
        self::$forced = $entity;
        self::$resolved = $entity;
    }

    /**
     * Réinitialise le cache — utile entre deux tests, ou entre deux
     * itérations d'une commande qui traite plusieurs entités à la suite.
     */
    public static function reset(): void
    {
        self::$forced = null;
        self::$resolved = null;
    }

    private static function doResolve(): Entity
    {
        $slug = request()?->query('entity');
        if ($slug) {
            $entity = Entity::where('slug', $slug)->where('est_active', true)->first();
            if ($entity) {
                return $entity;
            }
        }

        $user = Auth::guard('sanctum')->user();
        if ($user && isset($user->entity_id) && $user->entity_id) {
            $entity = Entity::find($user->entity_id);
            if ($entity) {
                return $entity;
            }
        }

        $defaultSlug = config('consulat.default_entity_slug', 'congo-benin');
        $entity = Entity::where('slug', $defaultSlug)->first();

        if (!$entity) {
            // Dernier recours : la première entité active. Ne devrait
            // jamais servir hors d'une base fraîchement installée sans
            // seeder joué.
            $entity = Entity::where('est_active', true)->firstOrFail();
        }

        return $entity;
    }
}
