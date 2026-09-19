<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Registre unique des encaissements : `canal` distingue le paiement en
 * ligne (FedaPay) du paiement au guichet (espèces, mobile money encaissé
 * sur place par un agent).
 *
 * Source de vérité des paiements. `demandes.paiement_statut` n'en est
 * qu'un miroir, recalculé par l'observer PaiementObserver à chaque
 * changement de statut ici — jamais mis à jour à la main ailleurs.
 */
class Paiement extends Model
{
    use HasFactory, BelongsToEntity;

    protected $fillable = [
        'entity_id',
        'demande_id',
        'ressortissant_id',
        'nom_payeur',
        'telephone_payeur',
        'email_payeur',
        'montant',
        'devise',
        'statut',
        'canal',
        'mode',
        'encaisse_par',
        'numero_recu',
        'date_encaissement',
        'fedapay_transaction_id',
        'fedapay_reference',
        'fedapay_derniere_reponse',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_encaissement' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const CANAUX = [
        'en_ligne' => 'En ligne (FedaPay)',
        'guichet' => 'Au guichet',
    ];

    public const MODES_GUICHET = [
        'especes' => 'Espèces',
        'mobile_money' => 'Mobile Money',
        'virement' => 'Virement',
        'carte' => 'Carte bancaire',
    ];

    /**
     * Relations
     */
    public function demande()
    {
        return $this->belongsTo(Demande::class);
    }

    public function ressortissant()
    {
        return $this->belongsTo(Ressortissant::class);
    }

    public function encaissePar()
    {
        return $this->belongsTo(User::class, 'encaisse_par');
    }

    /**
     * Enregistre un encaissement au guichet et marque immédiatement la
     * demande comme payée : contrairement à FedaPay, il n'y a pas de
     * webhook à attendre, l'agent a l'argent en main.
     */
    public static function encaisserAuGuichet(Demande $demande, array $donnees, int $encaissePar): self
    {
        $paiement = self::create([
            'entity_id' => $demande->entity_id,
            'demande_id' => $demande->id,
            'ressortissant_id' => $demande->ressortissant_id,
            'nom_payeur' => $donnees['nom_payeur'] ?? null,
            'telephone_payeur' => $donnees['telephone_payeur'] ?? null,
            'montant' => $demande->montant,
            'devise' => $demande->devise,
            'statut' => 'reussi',
            'canal' => 'guichet',
            'mode' => $donnees['mode'],
            'encaisse_par' => $encaissePar,
            'numero_recu' => $donnees['numero_recu'],
            'date_encaissement' => now(),
        ]);

        return $paiement;
    }

    /**
     * Scopes
     */
    public function scopeReussi($query)
    {
        return $query->where('statut', 'reussi');
    }

    public function scopeCanal($query, string $canal)
    {
        return $query->where('canal', $canal);
    }
}
