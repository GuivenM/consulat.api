<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;

/**
 * Un ressortissant inscrit au registre consulaire d'une entité. Reprend la
 * mécanique authentifiable de l'ancien modèle Membre (AJDCB) : peut
 * posséder des tokens Sanctum et se connecter à l'espace membre,
 * séparément des comptes `User` (admin/agent/super_admin).
 *
 * L'inscription est en self-service : le compte existe dès la création,
 * devient utilisable une fois email_verified_at renseigné (voir
 * AuthRessortissantController), et n'a plus besoin d'aucune validation
 * admin préalable.
 */
class Ressortissant extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable, BelongsToEntity;

    protected $fillable = [
        'entity_id',
        'numero_registre',
        'nom',
        'prenom',
        'photo',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'nationalite',
        'profession',
        'situation_matrimoniale',
        'type_piece',
        'numero_piece',
        'date_expiration_piece',
        'whatsapp',
        'telephone',
        'ville',
        'quartier',
        'adresse',
        'latitude',
        'longitude',
        'date_arrivee',
        'contact_urgence_nom',
        'contact_urgence_telephone',
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
        'date_naissance' => 'date',
        'date_expiration_piece' => 'date',
        'date_arrivee' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'email_verified_at' => 'datetime',
        'derniere_connexion' => 'datetime',
        'activation_token_expire_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['nom_complet', 'photo_url', 'inscription_verifiee'];

    /**
     * Les 77 communes du Bénin (source : Wikipédia / decentralisation.gouv.bj),
     * utilisées comme localisation principale (`ville`) du ressortissant —
     * la commune est l'unité administrative de base au Bénin ; `quartier`
     * affine ensuite, sans liste fermée.
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

    public function getInscriptionVerifieeAttribute()
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Relations
     */
    public function demandes()
    {
        return $this->hasMany(Demande::class);
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }

    /**
     * Génère et attribue le numéro de registre, au format
     * {CODE_PAYS_REPRESENTE}-{CODE_PAYS_ACCUEIL}-{année}-{séquence sur 5
     * chiffres, par entité et par année}. Idempotent : si le ressortissant
     * a déjà un numéro, il n'est pas régénéré.
     *
     * À appeler une fois l'inscription confirmée (email vérifié) —
     * jamais à la création, pour ne pas consommer un numéro sur une
     * inscription qui ne sera jamais finalisée.
     */
    public function attribuerNumeroRegistre(): string
    {
        if ($this->numero_registre) {
            return $this->numero_registre;
        }

        $entity = $this->entity ?? Entity::withoutGlobalScope('entity')->find($this->entity_id);
        $annee = now()->format('Y');

        $dernierNumero = self::withoutGlobalScope('entity')
            ->where('entity_id', $this->entity_id)
            ->where('numero_registre', 'like', "%-{$annee}-%")
            ->orderByDesc('numero_registre')
            ->value('numero_registre');

        $sequence = 1;
        if ($dernierNumero) {
            $sequence = (int) substr($dernierNumero, -5) + 1;
        }

        $this->numero_registre = sprintf(
            '%s-%s-%s-%05d',
            $entity->code_pays_represente ?: 'XXX',
            $entity->code_pays_accueil ?: 'XXX',
            $annee,
            $sequence
        );
        $this->save();

        return $this->numero_registre;
    }

    /**
     * Scopes
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeInscrits($query)
    {
        return $query->whereNotNull('email_verified_at');
    }
}
