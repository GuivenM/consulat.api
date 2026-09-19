<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Support\Facades\Storage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mini-CMS en sections/sous-sections/documents, repris tel quel d'AJDCB et
 * réutilisé pour la page Services consulaires (visa, légalisation, actes,
 * pièces à fournir, tarifs, délais — en onglets) et, si besoin, la FAQ.
 * Purement informatif : aucune démarche ne se dépose depuis ces pages.
 */
class GuideSection extends Model
{
    use HasFactory, BelongsToEntity;

    protected $table = 'guide_sections';

    protected $fillable = [
        'entity_id',
        'titre',
        'description',
        'categorie',
        'contenu',
        'image',
        'icone',
        'ordre',
        'statut'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relations
     */
    public function sousSections()
    {
        return $this->hasMany(GuideSousSection::class, 'section_id')->orderBy('ordre');
    }

    /**
     * Scopes
     */
    public function scopePublie($query)
    {
        return $query->where('statut', 'publie');
    }

    public function scopeByCategorie($query, $categorie)
    {
        return $query->where('categorie', $categorie);
    }

    protected $appends = ['image_url', 'icone_url'];

    /**
     * Accesseurs
     */
    public function getImageUrlAttribute()
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    public function getIconeUrlAttribute()
    {
        return $this->icone ? Storage::disk('public')->url($this->icone) : null;
    }
}
