<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class JournalActivite extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'sujet_type',
        'sujet_id',
        'donnees',
        'created_at',
    ];

    protected $casts = [
        'donnees' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Enregistre une entrée de journal. À appeler juste après l'action
     * elle-même a réussi (jamais avant, jamais si elle a échoué).
     *
     * @param string $action Identifiant court, ex. 'membre.supprimer'
     * @param string $description Résumé lisible, déjà formaté
     * @param Model|null $sujet Le modèle concerné (Ressortissant, User, Demande...)
     * @param array $donnees Contexte structuré additionnel, optionnel
     */
    public static function enregistrer(string $action, string $description, ?Model $sujet = null, array $donnees = []): void
    {
        try {
            self::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'description' => $description,
                'sujet_type' => $sujet ? class_basename($sujet) : null,
                'sujet_id' => $sujet->id ?? null,
                'donnees' => $donnees ?: null,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Le journal ne doit jamais faire planter l'action qu'il journalise.
            \Log::error('Erreur écriture journal activité: ' . $e->getMessage());
        }
    }
}
