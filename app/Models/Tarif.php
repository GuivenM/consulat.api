<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Grille tarifaire d'une entité : (type_demande, delai) -> montant.
 * Gérée depuis l'espace admin, jamais en dur dans le code.
 */
class Tarif extends Model
{
    use HasFactory, BelongsToEntity;

    protected $table = 'tarifs';

    protected $fillable = [
        'entity_id',
        'type_demande',
        'delai',
        'montant',
        'devise',
        'delai_heures',
        'est_actif',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'est_actif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const DELAIS = [
        '3_jours' => '3 jours ouvrés',
        '24h' => '24 heures',
        'meme_jour' => 'Le jour même',
    ];

    /**
     * Résout le tarif applicable à une demande. Lève une exception si
     * aucun tarif actif n'est configuré : mieux vaut bloquer le dépôt que
     * facturer un montant arbitraire.
     */
    public static function montantPour(int $entityId, string $typeDemande, string $delai): self
    {
        return self::where('entity_id', $entityId)
            ->where('type_demande', $typeDemande)
            ->where('delai', $delai)
            ->where('est_actif', true)
            ->firstOrFail();
    }

    public function scopeActif($query)
    {
        return $query->where('est_actif', true);
    }

    public function scopePourType($query, string $type)
    {
        return $query->where('type_demande', $type);
    }
}
