<?php

namespace App\Mail;

use App\Models\Demande;
use App\Models\DemandeDocument;
use App\Models\Entity;
use App\Models\Ressortissant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Une pièce du dossier a été refusée par un agent : le ressortissant doit
 * la redéposer. Le dossier lui-même n'est pas rejeté (voir
 * DemandeDocument::verifier). Envoyé par DemandeDocumentObserver.
 */
class PieceRejetee extends Mailable
{
    use Queueable, SerializesModels;

    public Entity $entite;
    public string $lien;

    public function __construct(
        public DemandeDocument $document,
        public Demande $demande,
        public Ressortissant $ressortissant,
    ) {
        $this->entite = $ressortissant->entity;
        $this->lien = rtrim(config('app.frontend_url'), '/') . '/espace-consulaire/demandes/' . $demande->id;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Une pièce de votre dossier {$this->demande->numero_dossier} doit être redéposée — "
                . ($this->entite->nom_court ?? $this->entite->nom),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.piece-rejetee');
    }
}
