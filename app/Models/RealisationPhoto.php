<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RealisationPhoto extends Model
{
    protected $fillable = ['realisation_id', 'chemin', 'ordre'];

    protected $appends = ['url'];

    public function realisation()
    {
        return $this->belongsTo(Realisation::class);
    }

    public function getUrlAttribute()
    {
        return $this->chemin ? Storage::disk('public')->url($this->chemin) : null;
    }
}
