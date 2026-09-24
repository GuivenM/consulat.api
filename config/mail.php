<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.gmail.com'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'contact@consulatcongo-benin.com'),
        'name' => env('MAIL_FROM_NAME', 'Consulat Honoraire du Congo au Bénin'),
    ],

    // Reçoit les notifications de nouveau message de contact.
    // Avant : cette clé n'existait pas dans ce fichier alors que les
    // contrôleurs faisaient config('mail.admin_address') — l'appel renvoyait
    // toujours null, d'où les adresses de secours codées en dur et
    // incohérentes ('admin@ajecb.com' vs 'contact@ajecb.org') qu'on avait
    // dans le code des contrôleurs.
    'admin_address' => env('MAIL_ADMIN_ADDRESS', 'contact@consulatcongo-benin.com'),
];