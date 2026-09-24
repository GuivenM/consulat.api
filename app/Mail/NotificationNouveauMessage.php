<?php
// App\Mail\NotificationNouveauMessage.php

namespace App\Mail;

use App\Models\Entity;
use App\Models\Message;
use App\Support\CurrentEntity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificationNouveauMessage extends Mailable
{
    use Queueable, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function build()
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $objetLabels = [
            'question' => '❓ Question',
            'partenariat' => '🤝 Partenariat',
            'service_consulaire' => '📋 Question sur une démarche',
            'urgence' => '⚠️ Urgence',
            'autre' => '📝 Autre'
        ];

        $entite = $this->message->entity ?? CurrentEntity::resolve();

        return $this->subject('📬 Nouveau message de contact — ' . ($entite->nom_court ?? $entite->nom))
                    ->view('emails.nouveau-message')
                    ->with([
                        'entite' => $entite,
                        'nom' => $this->message->nom,
                        'prenom' => $this->message->prenom,
                        'email' => $this->message->email,
                        'telephone' => $this->message->telephone,
                        'objet' => $objetLabels[$this->message->objet] ?? $this->message->objet,
                        'contenu' => $this->message->message,
                        'date' => $this->message->created_at->format('d/m/Y H:i'),
                        'dashboardUrl' => $frontendUrl . '/admin/messages/' . $this->message->id,
                        'listeUrl' => $frontendUrl . '/admin/messages'
                    ]);
    }
}