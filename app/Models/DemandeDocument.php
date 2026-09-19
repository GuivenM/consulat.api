<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Une pièce jointe d'une demande. Pas de BelongsToEntity ici : elle
 * appartient à une Demande qui, elle, est déjà scopée par entité — la
 * dupliquer ferait une double contrainte sans intérêt.
 */
class DemandeDocument extends Model
{
    use HasFactory;

    protected $table = 'demande_documents';

    protected $fillable = [
        'demande_id',
        'code_document',
        'label',
        'fichier',
        'nom_original',
        'mime',
        'taille',
        'statut',
        'motif_rejet',
        'verifie_par',
        'verifie_at',
    ];

    protected $casts = [
        'taille' => 'integer',
        'verifie_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['fichier_url'];

    public const STATUTS = [
        'en_attente' => 'En attente',
        'valide' => 'Validé',
        'rejete' => 'Rejeté',
    ];

    /**
     * Relations
     */
    public function demande()
    {
        return $this->belongsTo(Demande::class);
    }

    public function verifiePar()
    {
        return $this->belongsTo(User::class, 'verifie_par');
    }

    /**
     * Accesseurs
     */
    public function getFichierUrlAttribute()
    {
        // Disque privé : les pièces d'identité ne sont jamais servies
        // publiquement. L'URL n'est valable que via une route contrôlée
        // par le backend (vérification d'appartenance au dossier).
        return $this->fichier ? Storage::disk('local')->path($this->fichier) : null;
    }

    /**
     * Valide ou rejette une pièce individuellement, sans toucher au reste
     * du dossier — un agent peut redemander une seule photo sans rejeter
     * toute la demande.
     */
    public function verifier(string $statut, ?int $userId = null, ?string $motifRejet = null): void
    {
        $this->statut = $statut;
        $this->verifie_par = $userId;
        $this->verifie_at = now();

        if ($statut === 'rejete') {
            $this->motif_rejet = $motifRejet;
        }

        $this->save();
    }
}
