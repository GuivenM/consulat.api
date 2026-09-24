<?php

namespace App\Support;

use App\Models\Ressortissant;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Point d'envoi unique des notifications aux ressortissants. Un email est
 * un « plus » : son échec (SMTP indisponible, adresse invalide) ne doit
 * jamais faire échouer l'action de l'agent qui l'a déclenché — on le
 * journalise et on continue. Un ressortissant sans email (le champ est
 * facultatif) est simplement ignoré.
 */
class MailRessortissant
{
    public static function envoyer(?Ressortissant $ressortissant, Mailable $mailable, string $contexte): void
    {
        if (!$ressortissant || !$ressortissant->email) {
            return;
        }

        try {
            Mail::to($ressortissant->email)->send($mailable);
        } catch (\Throwable $e) {
            Log::error("Envoi email ressortissant échoué ({$contexte}) : " . $e->getMessage());
        }
    }
}
