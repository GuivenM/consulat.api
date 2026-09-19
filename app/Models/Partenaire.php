<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Support\Facades\Storage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Sert la page Congo–Bénin & Diplomatie économique (partenaires
 * institutionnels, entreprises, secteurs porteurs).
 *
 * Les relations ->projets() et ->evenements() du modèle AJDCB pointaient
 * vers une classe Projet inexistante et une table partenaires_evenements
 * supprimée avec le nettoyage des tables associatives : retirées plutôt
 * que laissées à planter au premier appel.
 */
class Partenaire extends Model
{
    use HasFactory, BelongsToEntity;

    protected $table = 'partenaires';

    protected $fillable = [
        'entity_id',
        'nom',
        'description',
        'logo',
        'site_web',
        'type',
        'secteur_activite',
        'pays',
        'ville',
        'adresse',
        'email',
        'telephone',
        'date_debut_partenariat',
        'date_fin_partenariat',
        'niveau_partenariat',
        'domaines_intervention',
        'statut'
    ];

    protected $casts = [
        'domaines_intervention' => 'array',
        'date_debut_partenariat' => 'date',
        'date_fin_partenariat' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relations
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Scopes
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByNiveau($query, $niveau)
    {
        return $query->where('niveau_partenariat', $niveau);
    }

    protected $appends = ['logo_url', 'niveau_label', 'type_label'];

    /**
     * Accesseurs
     */
    public function getLogoUrlAttribute()
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    public function getNiveauLabelAttribute()
    {
        $niveaux = [
            'or' => 'Partenariat Or',
            'argent' => 'Partenariat Argent',
            'bronze' => 'Partenariat Bronze',
            'institutionnel' => 'Partenariat Institutionnel',
            'technique' => 'Partenariat Technique'
        ];

        return $niveaux[$this->niveau_partenariat] ?? $this->niveau_partenariat;
    }

    public function getTypeLabelAttribute()
    {
        $types = [
            'institution' => 'Institution',
            'ong' => 'ONG',
            'entreprise' => 'Entreprise',
            'media' => 'Média',
            'universite' => 'Université/École',
            'association' => 'Association'
        ];

        return $types[$this->type] ?? $this->type;
    }
}
