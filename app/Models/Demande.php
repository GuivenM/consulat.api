<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une demande consulaire (carte consulaire, laissez-passer, et les types
 * qu'ajouteront d'autres entités). Le modèle générique absorbe les
 * variations entre types via `donnees_specifiques` (JSON) plutôt que
 * d'exiger une table par type.
 */
class Demande extends Model
{
    use HasFactory, SoftDeletes, BelongsToEntity;

    protected $table = 'demandes';

    protected $fillable = [
        'entity_id',
        'ressortissant_id',
        'numero_dossier',
        'type',
        'delai',
        'statut',
        'motif_rejet',
        'montant',
        'devise',
        'paiement_statut',
        'date_depot',
        'date_disponibilite_prevue',
        'date_pret',
        'date_retrait',
        'traite_par',
        'donnees_specifiques',
        'note_interne',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_depot' => 'datetime',
        'date_disponibilite_prevue' => 'datetime',
        'date_pret' => 'datetime',
        'date_retrait' => 'datetime',
        'donnees_specifiques' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['statut_label', 'delai_label', 'documents_complets'];

    public const STATUTS = [
        'recu' => 'Reçu',
        'en_traitement' => 'En traitement',
        'pret' => 'Prêt',
        'retire' => 'Retiré',
        'rejete' => 'Rejeté',
    ];

    public const PAIEMENT_STATUTS = [
        'en_attente' => 'En attente',
        'partiel' => 'Partiel',
        'paye' => 'Payé',
    ];

    /**
     * Ordre de progression normal d'une demande. `rejete` est atteignable
     * depuis n'importe quel statut, il n'apparaît pas dans cette liste
     * séquentielle (voir peutTransitionnerVers()).
     */
    public const PROGRESSION = ['recu', 'en_traitement', 'pret', 'retire'];

    /**
     * Crée une demande en résolvant le tarif applicable et en générant le
     * numéro de dossier. Centralise cette logique pour que le contrôleur
     * n'ait jamais à calculer un montant lui-même.
     */
    public static function creerPour(
        Ressortissant $ressortissant,
        string $type,
        string $delai,
        array $donneesSpecifiques = []
    ): self {
        $tarif = Tarif::montantPour($ressortissant->entity_id, $type, $delai);

        $dateDisponibilite = $tarif->delai_heures
            ? now()->addHours($tarif->delai_heures)
            : null;

        return self::create([
            'entity_id' => $ressortissant->entity_id,
            'ressortissant_id' => $ressortissant->id,
            'numero_dossier' => self::genererNumeroDossier($ressortissant->entity),
            'type' => $type,
            'delai' => $delai,
            'statut' => 'recu',
            'montant' => $tarif->montant,
            'devise' => $tarif->devise,
            'paiement_statut' => 'en_attente',
            'date_depot' => now(),
            'date_disponibilite_prevue' => $dateDisponibilite,
            'donnees_specifiques' => $donneesSpecifiques ?: null,
        ]);
    }

    /**
     * Format {PREFIXE}-{année}-{séquence sur 6 chiffres, par entité et par
     * année}, ex. CB-2026-000123. Le préfixe est dérivé du slug de
     * l'entité (2 premières lettres de chaque segment) plutôt que codé en
     * dur, pour qu'une nouvelle entité obtienne le sien automatiquement.
     */
    private static function genererNumeroDossier(Entity $entity): string
    {
        $prefixe = collect(explode('-', $entity->slug))
            ->map(fn ($segment) => strtoupper(substr($segment, 0, 2)))
            ->implode('');

        $annee = now()->format('Y');

        $dernier = self::withoutGlobalScope('entity')
            ->where('entity_id', $entity->id)
            ->where('numero_dossier', 'like', "{$prefixe}-{$annee}-%")
            ->orderByDesc('numero_dossier')
            ->value('numero_dossier');

        $sequence = $dernier ? ((int) substr($dernier, -6) + 1) : 1;

        return sprintf('%s-%s-%06d', $prefixe, $annee, $sequence);
    }

    /**
     * Relations
     */
    public function ressortissant()
    {
        return $this->belongsTo(Ressortissant::class);
    }

    public function documents()
    {
        return $this->hasMany(DemandeDocument::class);
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    /**
     * Fait progresser la demande d'un cran, ou la rejette. Ne fait aucune
     * vérification métier (documents complets, paiement) : c'est au
     * contrôleur de les faire avant d'appeler cette méthode, pour garder
     * les messages d'erreur explicites côté API plutôt que des exceptions
     * de modèle.
     */
    public function changerStatut(string $nouveauStatut, ?string $motifRejet = null): void
    {
        $this->statut = $nouveauStatut;

        if ($nouveauStatut === 'rejete') {
            $this->motif_rejet = $motifRejet;
        }
        if ($nouveauStatut === 'pret') {
            $this->date_pret = now();
        }
        if ($nouveauStatut === 'retire') {
            $this->date_retrait = now();
        }

        $this->save();
    }

    /**
     * Accesseurs
     */
    public function getStatutLabelAttribute()
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function getDelaiLabelAttribute()
    {
        return Tarif::DELAIS[$this->delai] ?? $this->delai;
    }

    /**
     * Toutes les pièces obligatoires de ce type de demande ont-elles été
     * déposées et validées ? Comparaison par code, jamais par nom de
     * fichier — c'est ce qui rend le formulaire dynamique fiable.
     */
    public function getDocumentsCompletsAttribute(): bool
    {
        $requis = DocumentTypeRequis::where('entity_id', $this->entity_id)
            ->pourType($this->type)
            ->actif()
            ->where('obligatoire', true)
            ->pluck('code_document');

        if ($requis->isEmpty()) {
            return true;
        }

        $documents = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();
        $valides = $documents->where('statut', 'valide')->pluck('code_document')->unique();

        return $requis->diff($valides)->isEmpty();
    }

    /**
     * Scopes
     */
    public function scopeStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
    }

    public function scopeEnCours($query)
    {
        return $query->whereIn('statut', ['recu', 'en_traitement']);
    }
}
