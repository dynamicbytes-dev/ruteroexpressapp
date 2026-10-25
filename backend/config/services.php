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

    'geoapify' => [
        // Mapas, rutas y geocodificación. Gratis y sin tarjeta.
        // Sin clave se usan tiles de OpenStreetMap y rutas estimadas.
        'key' => env('GEOAPIFY_API_KEY'),
        'routing_mode' => env('GEOAPIFY_ROUTING_MODE', 'motorcycle'),
    ],

    'nominatim' => [
        // Respaldo gratuito de OpenStreetMap para búsqueda de direcciones.
        'url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'GAZ/1.0 (+https://motoruta.app)'),
    ],

    'google_maps' => [
        // Opcional: Directions/Geocoding de Google si algún día hay clave.
        'server_key' => env('GOOGLE_MAPS_SERVER_KEY'),
    ],

    'fcm' => [
        // Notificaciones push (F4). El JSON de la cuenta de servicio de
        // Firebase vive fuera de git, en storage/app/firebase/.
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials' => env(
            'FCM_CREDENTIALS_PATH',
            storage_path('app/firebase/firebase-credentials.json'),
        ),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
