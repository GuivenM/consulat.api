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
        'contenu',
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

    protected $appends = ['photo_url', 'rubrique_label', 'photos_urls'];

    public function photos()
    {
        return $this->hasMany(RealisationPhoto::class)->orderBy('ordre');
    }

    public function getPhotoUrlAttribute()
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    /**
     * Toutes les images de la réalisation (couverture d'abord, puis galerie),
     * pour la page de détail. Charger `photos` en amont pour éviter les N+1.
     */
    public function getPhotosUrlsAttribute()
    {
        $galerie = $this->photos->pluck('url')->filter()->values()->all();

        return $this->photo_url ? array_merge([$this->photo_url], $galerie) : $galerie;
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
