<?php

namespace App\Mail;

use App\Models\Demande;
use App\Models\Entity;
use App\Models\Ressortissant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Prévient le ressortissant qu'un de ses dossiers a changé d'étape
 * (prise en charge, prêt au retrait, rejet). Envoyé par DemandeObserver.
 */
class DemandeStatutChange extends Mailable
{
    use Queueable, SerializesModels;

    public const TYPES = [
        'carte_consulaire' => 'carte consulaire',
        'laissez_passer' => 'laissez-passer',
    ];

    public Entity $entite;
    public string $typeLabel;
    public string $lien;

    public function __construct(
        public Demande $demande,
        public Ressortissant $ressortissant,
    ) {
        $this->entite = $ressortissant->entity;
        $this->typeLabel = self::TYPES[$demande->type] ?? str_replace('_', ' ', $demande->type);
        $this->lien = rtrim(config('app.frontend_url'), '/') . '/espace-consulaire/demandes/' . $demande->id;
    }

    public function envelope(): Envelope
    {
        $sujet = match ($this->demande->statut) {
            'pret' => "Votre dossier {$this->demande->numero_dossier} est prêt",
            'rejete' => "Votre dossier {$this->demande->numero_dossier} n'a pas pu être accepté",
            default => "Votre dossier {$this->demande->numero_dossier} est en cours de traitement",
        };

        return new Envelope(subject: $sujet . ' — ' . ($this->entite->nom_court ?? $this->entite->nom));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.demande-statut');
    }
}
