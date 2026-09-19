<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuration dynamique des pièces à fournir pour un type de demande.
 * Le front interroge cette table au chargement du formulaire de dépôt et
 * génère ses champs d'upload à la volée — aucun code à modifier pour
 * ajouter ou retirer une pièce requise.
 */
class DocumentTypeRequis extends Model
{
    use HasFactory, BelongsToEntity;

    protected $table = 'document_types_requis';

    protected $fillable = [
        'entity_id',
        'type_demande',
        'code_document',
        'label',
        'aide',
        'ordre',
        'nombre_requis',
        'formats_acceptes',
        'taille_max_ko',
        'obligatoire',
        'est_actif',
    ];

    protected $casts = [
        'nombre_requis' => 'integer',
        'taille_max_ko' => 'integer',
        'obligatoire' => 'boolean',
        'est_actif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['formats_acceptes_liste'];

    public function getFormatsAcceptesListeAttribute()
    {
        return array_filter(array_map('trim', explode(',', (string) $this->formats_acceptes)));
    }

    /**
     * Scopes
     */
    public function scopeActif($query)
    {
        return $query->where('est_actif', true);
    }

    public function scopePourType($query, string $type)
    {
        return $query->where('type_demande', $type)->orderBy('ordre');
    }
}
