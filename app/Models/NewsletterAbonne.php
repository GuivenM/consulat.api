<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterAbonne extends Model
{
    use HasFactory, BelongsToEntity;

    protected $table = 'newsletter_abonnes';

    protected $fillable = [
        'entity_id',
        'email',
        'statut',
        'source',
        'desinscrit_le',
    ];

    protected $casts = [
        'desinscrit_le' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scopes
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }
}
