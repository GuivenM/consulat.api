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

class ConfirmationMessage extends Mailable
{
    use Queueable, SerializesModels;

    public Message $messageData;
    public Entity $entite;
    public string $siteUrl;

    public function __construct(Message $messageData)
    {
        $this->messageData = $messageData;
        $this->entite = $messageData->entity ?? CurrentEntity::resolve();
        $this->siteUrl = rtrim(config('app.frontend_url'), '/');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmation de réception de votre message — ' . ($this->entite->nom_court ?? $this->entite->nom),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.confirmation-message',
        );
    }
}