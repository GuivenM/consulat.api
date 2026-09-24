<?php

namespace App\Support;

use App\Models\Ressortissant;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Point d'envoi unique des notifications aux ressortissants. Un email est
 * un « plus » : son échec (SMTP indisponible, adresse invalide, vue
 * défaillante) ne doit jamais faire échouer l'action de l'agent qui l'a
 * déclenché — on le journalise et on continue.
 *
 * Le mail est reçu sous forme de fabrique (Closure) et non d'objet : sa
 * construction se fait ainsi dans le try, une erreur dans le constructeur
 * du Mailable est donc journalisée au lieu de faire une 500.
 *
 * Chaque issue laisse une trace dans laravel.log (envoyé / ignoré / échoué),
 * pour pouvoir répondre à « pourquoi le client n'a rien reçu ? » sans
 * deviner. Un ressortissant sans email (champ facultatif) est ignoré.
 */
class MailRessortissant
{
    public static function envoyer(?Ressortissant $ressortissant, Closure $fabrique, string $contexte): void
    {
        if (!$ressortissant) {
            Log::warning("Notification ignorée ({$contexte}) : ressortissant introuvable.");
            return;
        }

        if (!$ressortissant->email) {
            Log::warning("Notification ignorée ({$contexte}) : le ressortissant #{$ressortissant->id} n'a pas d'email.");
            return;
        }

        try {
            Mail::to($ressortissant->email)->send($fabrique());
            Log::info("Notification envoyée ({$contexte}) à {$ressortissant->email}.");
        } catch (\Throwable $e) {
            Log::error("Envoi email ressortissant échoué ({$contexte}) : " . $e->getMessage());
        }
    }
}
