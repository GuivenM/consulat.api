<?php

namespace App\Mail;

use App\Models\Entity;
use App\Models\Message;
use App\Support\CurrentEntity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReponseMessage extends Mailable
{
    use Queueable, SerializesModels;

    public Message $messageData;
    public string $reponse;
    public string $objet;
    public Entity $entite;

    public function __construct(Message $messageData, string $reponse, string $objet)
    {
        $this->messageData = $messageData;
        $this->reponse = $reponse;
        $this->objet = $objet;
        $this->entite = $messageData->entity ?? CurrentEntity::resolve();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->objet ?: 'Réponse à votre message — ' . ($this->entite->nom_court ?? $this->entite->nom),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reponse-message',
        );
    }
}