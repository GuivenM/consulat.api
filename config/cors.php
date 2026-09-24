<?php

// ATTENTION SÉCURITÉ : 'allowed_origins' => ['*'] combiné à
// 'supports_credentials' => true est une configuration incohérente (rejetée
// par les navigateurs modernes) et, si elle fonctionnait, autoriserait
// n'importe quel site à appeler cette API avec les cookies/tokens de
// l'utilisateur. On restreint désormais aux origines listées dans
// CORS_ALLOWED_ORIGINS (.env), séparées par des virgules.
// Exemple .env : CORS_ALLOWED_ORIGINS=https://consulatcongo-benin.com,http://localhost:5173
return [
    'paths' => ['api/*', 'storage/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))
    )),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];