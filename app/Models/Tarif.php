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

    /**
     * Délais proposés, du plus rapide au plus long. L'ordre de ce tableau
     * est l'ordre d'affichage partout (site public, admin, dépôt de demande),
     * quel que soit l'ordre dans lequel les tarifs ont été créés.
     */
    public const DELAIS = [
        'meme_jour' => 'Le jour même',
        '24h' => '24 heures',
        '3_jours' => '3 jours ouvrés',
        '1_semaine' => '1 semaine',
        '2_semaines' => '2 semaines',
    ];

    /** Délai de traitement en heures par défaut, pour la date de disponibilité prévisionnelle. */
    public const HEURES_PAR_DEFAUT = [
        'meme_jour' => 8,
        '24h' => 24,
        '3_jours' => 72,
        '1_semaine' => 168,
        '2_semaines' => 336,
    ];

    /** Rang d'un délai dans DELAIS (les délais inconnus passent en dernier). */
    public static function rang(?string $delai): int
    {
        $position = array_search($delai, array_keys(self::DELAIS), true);

        return $position === false ? 99 : $position;
    }

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
