<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'fedapay' => [
        // 'sandbox' ou 'live'
        'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
        'secret_key' => env('FEDAPAY_SECRET_KEY'),
        'public_key' => env('FEDAPAY_PUBLIC_KEY'),
        // Secret du endpoint webhook (dashboard FedaPay > Webhooks), pas la clé API.
        'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),
        // Page du site où FedaPay redirige le client après paiement.
        'callback_url' => env('FEDAPAY_CALLBACK_URL', env('FRONTEND_URL', 'http://localhost:5173') . '/paiement/retour'),
    ],

    'facebook' => [
        // ID de la Page Facebook du consulat (visible dans Paramètres de la Page > À propos,
        // ou via https://graph.facebook.com/{nom-de-la-page}?fields=id).
        'page_id' => env('FACEBOOK_PAGE_ID'),
        // Token d'accès de PAGE longue durée (pas un token utilisateur) généré
        // via Meta for Developers avec le scope pages_manage_posts. Sans ces
        // deux valeurs, le bouton "Publier sur Facebook" reste désactivé côté
        // admin et seul le lien de partage manuel est proposé.
        'page_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
    ],

];
