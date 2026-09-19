<?php

namespace App\Models\Concerns;

use App\Models\Entity;
use App\Support\CurrentEntity;
use Illuminate\Database\Eloquent\Builder;

/**
 * Porte le multi-tenant pour un modèle : toute lecture est automatiquement
 * filtrée sur l'entité courante, et toute création reçoit son entity_id
 * sans que le contrôleur ait à y penser.
 *
 * Utilisation : `use BelongsToEntity;` sur le modèle, colonne `entity_id`
 * requise en base. C'est un scope global, donc :
 * - un ->find($id) sur l'ID d'une autre entité renvoie null, pas l'enregistrement ;
 * - pour du travail explicitement multi-entités (rare, ex. un export
 *   plateforme pour un super_admin), utiliser ::withoutGlobalScope('entity')
 *   ou le scope ->toutesEntites() ci-dessous.
 */
trait BelongsToEntity
{
    public static function bootBelongsToEntity(): void
    {
        static::addGlobalScope('entity', function (Builder $builder) {
            $builder->where($builder->getModel()->getTable() . '.entity_id', CurrentEntity::id());
        });

        static::creating(function ($model) {
            if (!$model->entity_id) {
                $model->entity_id = CurrentEntity::id();
            }
        });
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Échappe volontairement au scope entité — réservé aux vues
     * transverses (super_admin, statistiques plateforme).
     */
    public function scopeToutesEntites(Builder $query): Builder
    {
        return $query->withoutGlobalScope('entity');
    }
}
