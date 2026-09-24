<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuideDocument extends Model
{
    use HasFactory;

    protected $table = 'guide_documents';

    protected $fillable = [
        'sous_section_id',
        'titre',
        'description',
        'fichier',
        'type_fichier',
        'taille',
        'telechargements',
        'statut'
    ];

    protected $casts = [
        'telechargements' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relations
     */
    /**
     * Manquait : GuideController::index appelait ->publie() sur la relation
     * des documents, ce qui levait BadMethodCallException (donc une 500 sur
     * la route publique du guide) dès qu'on n'était pas en mode ?all=1.
     */
    public function scopePublie($query)
    {
        return $query->where('statut', 'publie');
    }

    public function sousSection()
    {
        return $this->belongsTo(GuideSousSection::class, 'sous_section_id');
    }

    protected $appends = ['fichier_url', 'taille_formatee'];

    /**
     * Accesseurs
     */
    public function getFichierUrlAttribute()
    {
        return $this->fichier ? Storage::disk('public')->url($this->fichier) : null;
    }

    public function getTailleFormateeAttribute()
    {
        $bytes = $this->taille;
        $units = ['octets', 'Ko', 'Mo', 'Go'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}