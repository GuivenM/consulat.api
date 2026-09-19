<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Support\Facades\Storage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Actualite extends Model
{
    use HasFactory, BelongsToEntity;

    protected $table = 'actualites';

    protected $fillable = [
        'entity_id',
        'titre',
        'slug',
        'description',
        'contenu',
        'image',
        'type',
        'categorie',
        'date_publicacion',
        'date_evenement',
        'lieu_evenement',
        'auteur',
        'source',
        'vues',
        'tags',
        'est_a_la_une',
        'statut',
        'facebook_post_url',
    ];

    protected $casts = [
        'tags' => 'array',
        'est_a_la_une' => 'boolean',
        'date_publicacion' => 'datetime',
        'date_evenement' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Boot method pour générer le slug automatiquement
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($actualite) {
            if (empty($actualite->slug)) {
                $actualite->slug = Str::slug($actualite->titre) . '-' . uniqid();
            }
        });
    }

    /**
     * Scopes
     */
    public function scopePublie($query)
    {
        return $query->where('statut', 'publie');
    }

    public function scopeBrouillon($query)
    {
        return $query->where('statut', 'brouillon');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeALaUne($query)
    {
        return $query->where('est_a_la_une', true);
    }

    public function scopeRecents($query, $limit = 5)
    {
        return $query->publie()->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Galerie multi-photos. Le champ `image` legacy reste rempli pour les
     * actualités créées avant cette fonctionnalité — `image_url` bascule
     * automatiquement sur la 1ère photo de la galerie dès qu'il y en a
     * une, sans casser l'affichage des anciennes actualités.
     */
    public function photos()
    {
        return $this->hasMany(ActualitePhoto::class)->orderBy('ordre');
    }

    protected $appends = ['image_url', 'date', 'type_label', 'extrait', 'lien_public', 'photos_urls'];

    /**
     * Accesseurs
     */
    public function getImageUrlAttribute()
    {
        $premierePhoto = $this->relationLoaded('photos') ? $this->photos->first() : $this->photos()->first();
        if ($premierePhoto) {
            return $premierePhoto->url;
        }

        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    public function getPhotosUrlsAttribute()
    {
        $photos = $this->relationLoaded('photos') ? $this->photos : $this->photos()->get();

        if ($photos->isNotEmpty()) {
            return $photos->pluck('url')->values();
        }

        // Compat : anciennes actualités avec une seule image legacy.
        return $this->image ? collect([Storage::disk('public')->url($this->image)]) : collect();
    }

    public function getLienPublicAttribute()
    {
        $base = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
        return "{$base}/actualites/{$this->slug}";
    }

    public function getDateAttribute()
    {
        return $this->created_at->format('d/m/Y');
    }

    public function getTypeLabelAttribute()
    {
        $types = [
            'actualite' => 'Actualité',
            'evenement' => 'Événement',
            'education' => 'Éducation',
            'culture' => 'Culture'
        ];

        return $types[$this->type] ?? $this->type;
    }

    public function getExtraitAttribute($longueur = 150)
    {
        return substr(strip_tags($this->description), 0, $longueur) . '...';
    }
}
