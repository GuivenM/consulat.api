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
            'audience' => "📅 Demande d'audience",
            'sos' => '🆘 SOS',
            'autre' => '📝 Autre'
        ];

        $entite = $this->message->entity ?? CurrentEntity::resolve();

        $estSos = $this->message->objet === 'sos';
        $nomEntite = $entite->nom_court ?? $entite->nom;
        $expediteur = trim($this->message->prenom . ' ' . $this->message->nom);

        $sujet = match ($this->message->objet) {
            'sos' => '🆘 SOS — demande d\'aide urgente de ' . $expediteur,
            'audience' => '📅 Demande d\'audience — ' . $expediteur,
            default => '📬 Nouveau message de contact — ' . $nomEntite,
        };

        $mail = $this->subject($sujet)
                    ->view('emails.nouveau-message')
                    ->with([
                        'entite' => $entite,
                        'nom' => $this->message->nom,
                        'prenom' => $this->message->prenom,
                        'email' => $this->message->email ?: 'Non renseigné',
                        'estSos' => $estSos,
                        'dateSouhaitee' => $this->message->date_souhaitee?->format('d/m/Y'),
                        'typeAide' => $this->message->type_aide_label,
                        'piece' => $this->message->piece_type
                            ? trim($this->message->piece_type_label . ' ' . ($this->message->piece_numero ?? '')) . ' (copie jointe, à consulter dans le dashboard)'
                            : null,
                        'localisation' => $this->message->localisation,
                        'carteUrl' => ($this->message->latitude !== null && $this->message->longitude !== null)
                            ? 'https://www.google.com/maps?q=' . $this->message->latitude . ',' . $this->message->longitude
                            : null,
                        'telephone' => $this->message->telephone,
                        'objet' => $objetLabels[$this->message->objet] ?? $this->message->objet,
                        'contenu' => $this->message->message,
                        'date' => $this->message->created_at->format('d/m/Y H:i'),
                        'dashboardUrl' => $frontendUrl . '/admin/messages/' . $this->message->id,
                        'listeUrl' => $frontendUrl . '/admin/messages'
                    ]);

        if ($estSos) {
            $mail->priority(1); // importance haute dans la messagerie
        }

        return $mail;
    }
}