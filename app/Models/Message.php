<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Formulaire de contact de la page Contact. `objet` a été adapté au
 * contexte consulaire : 'adhesion' (AJDCB) devient 'service_consulaire'
 * (question sur une démarche — sans dépôt de dossier possible depuis ce
 * canal, cf. le widget "Besoin d'aide ?" qui renvoie ici ou vers la FAQ).
 */
class Message extends Model
{
    use HasFactory, BelongsToEntity;

    protected $table = 'messages';

    /**
     * Types d'aide proposés par le formulaire SOS (code => libellé).
     */
    public const TYPES_AIDE = [
        'perte_documents' => "Perte ou vol de documents (passeport, pièce d'identité)",
        'arrestation' => 'Arrestation ou détention',
        'sante' => 'Accident ou problème de santé',
        'agression' => 'Agression ou situation de danger',
        'ressources' => 'Difficulté financière ou de logement',
        'autre' => 'Autre situation urgente',
    ];

    /**
     * Pièces d'identité acceptées pour une demande d'audience (sous-ensemble
     * de Ressortissant::TYPES_PIECE).
     */
    public const TYPES_PIECE_AUDIENCE = ['passeport', 'cni', 'cip_etranger'];

    protected $fillable = [
        'entity_id',
        'nom',
        'prenom',
        'email',
        'telephone',
        'objet',
        'message',
        'date_souhaitee',
        'type_aide',
        'localisation',
        'latitude',
        'longitude',
        'piece_type',
        'piece_numero',
        'piece_fichier',
        'piece_fichier_nom',
        'organisation',
        'type_organisation',
        'secteur_activite',
        'pays',
        'ville',
        'site_web',
        'reponse',
        'date_reponse',
        'statut',
        'lu_le',
        'traite_par',
        'partenaire_id'
    ];

    protected $casts = [
        'lu_le' => 'datetime',
        'date_reponse' => 'datetime',
        'date_souhaitee' => 'date:Y-m-d',
        'latitude' => 'float',
        'longitude' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relations
     */
    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function partenaire()
    {
        return $this->belongsTo(Partenaire::class);
    }

    /**
     * Scopes
     */
    public function scopeNonLu($query)
    {
        return $query->where('statut', 'non_lu');
    }

    public function scopeLu($query)
    {
        return $query->where('statut', 'lu');
    }

    public function scopeRepondu($query)
    {
        return $query->where('statut', 'repondu');
    }

    public function scopeByObjet($query, $objet)
    {
        return $query->where('objet', $objet);
    }

    // Chemin du fichier sur le disque privé : jamais exposé dans le JSON, le
    // fichier ne se télécharge que via la route admin dédiée.
    protected $hidden = ['piece_fichier'];

    protected $appends = ['nom_complet', 'objet_label', 'statut_label', 'statut_color', 'date_envoi', 'date_envoi_fr', 'extrait', 'type_aide_label', 'piece_type_label', 'piece_fichier_disponible'];

    /**
     * Accesseurs
     */
    public function getNomCompletAttribute()
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getObjetLabelAttribute()
    {
        $objets = [
            'question' => 'Question',
            'service_consulaire' => 'Question sur une démarche',
            'partenariat' => 'Demande de partenariat',
            'urgence' => 'Urgence communautaire',
            'audience' => "Demande d'audience",
            'sos' => 'SOS - demande d\'aide',
            'autre' => 'Autre'
        ];

        return $objets[$this->objet] ?? $this->objet;
    }

    public function getTypeAideLabelAttribute()
    {
        return self::TYPES_AIDE[$this->type_aide] ?? $this->type_aide;
    }

    public function getPieceTypeLabelAttribute()
    {
        return Ressortissant::TYPES_PIECE[$this->piece_type] ?? $this->piece_type;
    }

    public function getPieceFichierDisponibleAttribute()
    {
        return !empty($this->attributes['piece_fichier'] ?? null);
    }

    public function getStatutLabelAttribute()
    {
        $labels = [
            'non_lu' => 'Non lu',
            'lu' => 'Lu',
            'repondu' => 'Répondu'
        ];

        return $labels[$this->statut] ?? $this->statut;
    }

    public function getStatutColorAttribute()
    {
        $colors = [
            'non_lu' => 'danger',
            'lu' => 'warning',
            'repondu' => 'success'
        ];

        return $colors[$this->statut] ?? 'secondary';
    }

    public function getDateEnvoiAttribute()
    {
        return $this->created_at->format('d/m/Y à H:i');
    }

    public function getDateEnvoiFrAttribute()
    {
        return $this->created_at->locale('fr')->isoFormat('LLL');
    }

    public function getExtraitAttribute($longueur = 100)
    {
        return substr($this->message, 0, $longueur) . '...';
    }

    /**
     * Marquer comme lu
     */
    public function marquerCommeLu()
    {
        if ($this->statut === 'non_lu') {
            $this->update([
                'statut' => 'lu',
                'lu_le' => now()
            ]);
        }
    }

    /**
     * Répondre au message
     */
    public function repondre($reponse, $userId = null)
    {
        $this->update([
            'reponse' => $reponse,
            'date_reponse' => now(),
            'statut' => 'repondu',
            'traite_par' => $userId
        ]);
    }
}
