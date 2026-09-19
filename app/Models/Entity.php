<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Un poste consulaire/diplomatique (consulat honoraire, consulat général,
 * ambassade) d'un pays donné dans un pays d'accueil donné. Racine du
 * multi-tenant : toute donnée métier (ressortissants, demandes, contenus
 * vitrine...) est rattachée à une Entity via le trait BelongsToEntity.
 *
 * Ce modèle lui-même n'a pas de scope entité, pour des raisons évidentes.
 */
class Entity extends Model
{
    use HasFactory;

    protected $table = 'entities';

    protected $fillable = [
        'slug',
        'nom',
        'nom_court',
        'type',
        'pays_represente',
        'code_pays_represente',
        'pays_accueil',
        'code_pays_accueil',
        'ville_siege',
        'logo',
        'email',
        'telephone',
        'adresse',
        'latitude',
        'longitude',
        'devise',
        'locale',
        'fuseau_horaire',
        'est_active',
        'partage_reseau',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'est_active' => 'boolean',
        'partage_reseau' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['logo_url', 'type_label'];

    public const TYPES = [
        'consulat_honoraire' => 'Consulat Honoraire',
        'consulat_general' => 'Consulat Général',
        'ambassade' => 'Ambassade',
    ];

    /**
     * Relations
     */
    public function ressortissants()
    {
        return $this->hasMany(Ressortissant::class);
    }

    public function demandes()
    {
        return $this->hasMany(Demande::class);
    }

    public function tarifs()
    {
        return $this->hasMany(Tarif::class);
    }

    public function documentTypesRequis()
    {
        return $this->hasMany(DocumentTypeRequis::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('est_active', true);
    }

    /**
     * Accesseurs
     */
    public function getLogoUrlAttribute()
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    public function getTypeLabelAttribute()
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
