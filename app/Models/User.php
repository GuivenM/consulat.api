<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Compte admin/agent. `entity_id` est nullable : un super_admin (celui qui
 * crée les entités et leurs comptes admin/agent) n'appartient à aucune
 * entité en particulier ; un admin ou un agent, lui, est toujours
 * rattaché à une. Pas de BelongsToEntity ici volontairement — un
 * super_admin doit pouvoir lister les users de toutes les entités, un
 * scope global gênerait plus qu'il n'aiderait pour ce seul modèle.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'entity_id',
        'nom',
        'prenom',
        'email',
        'password',
        'photo',
        'role',
        'telephone',
        'est_actif',
        'email_verified_at',
        'derniere_connexion',
        'activation_token',
        'activation_token_expire_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'activation_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'derniere_connexion' => 'datetime',
        'activation_token_expire_at' => 'datetime',
        'est_actif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = ['nom_complet', 'photo_url', 'initiales', 'role_label', 'en_attente_activation'];

    public const ROLES = [
        'super_admin' => 'Super Administrateur',
        'admin' => 'Administrateur',
        'agent' => 'Agent',
    ];

    /**
     * Accesseurs
     */
    public function getNomCompletAttribute()
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getPhotoUrlAttribute()
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    public function getInitialesAttribute()
    {
        return strtoupper(substr($this->prenom, 0, 1) . substr($this->nom, 0, 1));
    }

    public function getRoleLabelAttribute()
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function getEnAttenteActivationAttribute()
    {
        return !is_null($this->activation_token);
    }

    /**
     * Vérifications de rôle
     */
    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin()
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    public function isAgent()
    {
        return in_array($this->role, ['super_admin', 'admin', 'agent']);
    }

    /**
     * Relations
     */
    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function demandesTraitees()
    {
        return $this->hasMany(Demande::class, 'traite_par');
    }
}
