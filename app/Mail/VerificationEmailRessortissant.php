<?php

namespace App\Mail;

use App\Models\Ressortissant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Couvre deux usages avec le même token (`ressortissant.activation_token`) :
 * vérification de l'email à l'inscription, et réinitialisation de mot de
 * passe. Jamais actifs en même temps sur un même compte, d'où le partage
 * du champ. Le contenu s'adapte à l'entité du ressortissant plutôt que de
 * coder en dur "Consulat du Congo" — nécessaire dès qu'une deuxième entité
 * existera.
 */
class VerificationEmailRessortissant extends Mailable
{
    use Queueable, SerializesModels;

    public Ressortissant $ressortissant;
    public string $lien;
    public bool $reinitialisation;

    public function __construct(Ressortissant $ressortissant, bool $reinitialisation = false)
    {
        $this->ressortissant = $ressortissant;
        $this->reinitialisation = $reinitialisation;

        $chemin = $reinitialisation ? '/reinitialiser-mot-de-passe' : '/verifier-email';
        $this->lien = rtrim(config('app.frontend_url'), '/')
            . $chemin . '?token=' . $ressortissant->activation_token;
    }

    public function envelope(): Envelope
    {
        $nomEntite = $this->ressortissant->entity->nom_court ?? $this->ressortissant->entity->nom;

        return new Envelope(
            subject: $this->reinitialisation
                ? "Réinitialisation de votre mot de passe — {$nomEntite}"
                : "Vérifiez votre adresse email — {$nomEntite}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-ressortissant',
        );
    }
}
