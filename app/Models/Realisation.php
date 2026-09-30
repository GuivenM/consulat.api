<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Réalisation de la communauté ou élément de culture & patrimoine.
 * La rubrique décide de la page publique où l'entrée apparaît.
 */
class Realisation extends Model
{
    use BelongsToEntity;

    protected $table = 'realisations';

    public const RUBRIQUES = [
        'communaute' => 'Communauté',
        'culture_patrimoine' => 'Culture & Patrimoine',
    ];

    protected $fillable = [
        'entity_id',
        'rubrique',
        'titre',
        'description',
        'photo',
        'date_realisation',
        'publie',
        'ordre',
    ];

    protected $casts = [
        'date_realisation' => 'date:Y-m-d',
        'publie' => 'boolean',
        'ordre' => 'integer',
    ];

    protected $appends = ['photo_url', 'rubrique_label'];

    public function getPhotoUrlAttribute()
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    public function getRubriqueLabelAttribute()
    {
        return self::RUBRIQUES[$this->rubrique] ?? $this->rubrique;
    }

    public function scopePublie($query)
    {
        return $query->where('publie', true);
    }
}
