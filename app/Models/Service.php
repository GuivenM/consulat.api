<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Service consulaire proposé par l'entité (carte consulaire, laissez-passer,
 * ou tout service ajouté depuis l'espace admin). `code` relie le service à
 * ses tarifs et pièces requises (`type_demande`) et aux demandes (`type`).
 */
class Service extends Model
{
    use HasFactory, BelongsToEntity;

    protected $table = 'services';

    protected $fillable = [
        'entity_id',
        'code',
        'nom',
        'description',
        'ordre',
        'est_actif',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'est_actif' => 'boolean',
    ];

    public function scopeActif($query)
    {
        return $query->where('est_actif', true);
    }
}
