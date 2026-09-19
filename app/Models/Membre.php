<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// Authenticatable (et non Model) : un Membre peut désormais posséder des
// tokens Sanctum et se connecter à l'espace membre, séparément des comptes
// `User` (admin/super_admin/moderateur) qui gardent leur propre espace.
class Membre extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $fillable = [
        'adhesion_id',
        'nom',
        'prenom',
        'photo',
        'facebook',
        'instagram',
        'linkedin',
        'twitter',
        'whatsapp',
        'ville',
        'poste',
        'commission',
        'statut',
        'motif_inactivation',
        'email',
        'password',
        'email_verified_at',
        'derniere_connexion',
        'activation_token',
        'activation_token_expire_at',
    ];

    protected $hidden = [
        'password',
        'activation_token',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'derniere_connexion' => 'datetime',
        'activation_token_expire_at' => 'datetime',
    ];

    protected $appends = ['nom_complet', 'photo_url', 'role', 'peut_avoir_acces_admin', 'a_compte_admin'];

    /**
     * Postes du bureau pouvant donner lieu à un accès admin, et rôle admin
     * associé (voir MembreController::creerAccesAdmin). Les autres postes
     * (Chargé des Projets, Chargé des Relations Extérieures, Conseiller) et
     * les simples membres de commission n'ont pas d'accès par défaut — un
     * compte peut toujours être créé à la main si besoin.
     */
    public const POSTES_ADMIN_ELIGIBLES = [
        'Président' => 'super_admin',
        'Vice-Président' => 'super_admin',
        'Secrétaire Général' => 'admin',
        'Secrétaire Général Adjoint' => 'admin',
        'Trésorier Général' => 'tresorier',
        'Trésorier Général Adjoint' => 'tresorier',
        'Chargé de Communication' => 'moderateur',
    ];

    /**
     * Les 77 communes du Bénin (source : Wikipédia / decentralisation.gouv.bj),
     * utilisées comme "ville" de résidence du membre — l'association ayant des
     * canaux distincts par ville (Cotonou, Ouidah, etc.).
     */
    public const VILLES_BENIN = [
        // Alibori
        'Banikoara', 'Gogounou', 'Kandi', 'Karimama', 'Malanville', 'Ségbana',
        // Atacora
        'Boukoumbé', 'Cobly', 'Kérou', 'Kouandé', 'Matéri', 'Natitingou', 'Péhunco', 'Tanguiéta', 'Toucountouna',
        // Atlantique
        'Abomey-Calavi', 'Allada', 'Kpomassè', 'Ouidah', 'Sô-Ava', 'Toffo', 'Tori-Bossito', 'Zè',
        // Borgou
        'Bembéréké', 'Kalalé', "N'Dali", 'Nikki', 'Parakou', 'Pèrèrè', 'Sinendé', 'Tchaourou',
        // Collines
        'Bantè', 'Dassa-Zoumè', 'Glazoué', 'Ouèssè', 'Savalou', 'Savè',
        // Couffo
        'Aplahoué', 'Djakotomey', 'Dogbo', 'Klouékanmè', 'Lalo', 'Toviklin',
        // Donga
        'Bassila', 'Copargo', 'Djougou', 'Ouaké',
        // Littoral
        'Cotonou',
        // Mono
        'Athiémé', 'Bopa', 'Comè', 'Grand-Popo', 'Houéyogbé', 'Lokossa',
        // Ouémé
        'Adjarra', 'Adjohoun', 'Aguégués', 'Akpro-Missérété', 'Avrankou', 'Bonou', 'Dangbo', 'Porto-Novo', 'Sèmè-Kpodji',
        // Plateau
        'Adja-Ouèrè', 'Ifangni', 'Kétou', 'Pobè', 'Sakété',
        // Zou
        'Abomey', 'Agbangnizoun', 'Bohicon', 'Covè', 'Djidja', 'Ouinhi', 'Za-Kpota', 'Zagnanado', 'Zogbodomey',
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

    public function getRoleAttribute()
    {
        if ($this->poste) {
            return $this->poste;
        }
        if ($this->commission) {
            return 'Commission ' . $this->commission;
        }
        return 'Membre actif';
    }

    public function getPeutAvoirAccesAdminAttribute()
    {
        return $this->poste && array_key_exists($this->poste, self::POSTES_ADMIN_ELIGIBLES);
    }

    public function getACompteAdminAttribute()
    {
        return $this->relationLoaded('compteAdmin')
            ? $this->compteAdmin !== null
            : $this->compteAdmin()->exists();
    }

    /**
     * Relations
     */
    public function compteAdmin()
    {
        return $this->hasOne(User::class);
    }

    public function evenements()
    {
        return $this->belongsToMany(Evenement::class, 'participations')
                    ->withPivot('statut', 'date_inscription', 'commentaire')
                    ->withTimestamps();
    }

    /**
     * Scopes
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeBureau($query)
    {
        return $query->whereNotNull('poste');
    }

    public function scopeCommission($query, $commission)
    {
        return $query->where('commission', $commission);
    }
}