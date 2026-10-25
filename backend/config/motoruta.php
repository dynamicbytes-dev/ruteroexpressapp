<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dominios de correo permitidos
    |--------------------------------------------------------------------------
    |
    | Regla del negocio: solo se aceptan correos de Gmail. Hotmail, Outlook y
    | cualquier otro proveedor quedan bloqueados en el registro.
    |
    */
    'allowed_email_domains' => env('MOTORUTA_ALLOWED_EMAIL_DOMAINS', 'gmail.com'),

    /*
    |--------------------------------------------------------------------------
    | Comisión de la plataforma
    |--------------------------------------------------------------------------
    |
    | Porcentaje que retiene GAZ sobre cada viaje. El conductor recibe el
    | resto. Ejemplo con 10%: viaje de $5.000 -> pasajero paga $5.000 y al
    | conductor se le acreditan $4.500.
    |
    */
    'service_fee_percent' => (float) env('MOTORUTA_SERVICE_FEE_PERCENT', 10),

    /*
    |--------------------------------------------------------------------------
    | Almacenamiento de documentos
    |--------------------------------------------------------------------------
    |
    | Los documentos de identidad y los comprobantes de pago viven en un disco
    | privado y se sirven solo con URLs firmadas temporales.
    |
    */
    'documents' => [
        'disk' => env('MOTORUTA_DOCUMENTS_DISK', 'local'),
        'directory' => 'documentos',
        'signed_url_ttl_minutes' => 5,
        // La app conserva la selfie como avatar; el enlace puede vivir más.
        'avatar_url_ttl_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Almacenamiento de avatares y fotos de perfil
    |--------------------------------------------------------------------------
    |
    | Las fotos de perfil (selfies) se guardan en el disco público o S3.
    |
    */
    'avatar_disk' => env('MOTORUTA_AVATAR_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Moneda
    |--------------------------------------------------------------------------
    |
    | Los viajes y la billetera se manejan en dólares. El bolívar solo aparece
    | como referencia usando la tasa del día que carga el auditor.
    |
    */
    'currency' => [
        'base' => 'USD',
        'symbol' => '$',
        'decimals' => 2,
        'local' => 'VES',
        'local_symbol' => 'Bs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rutas y distancias
    |--------------------------------------------------------------------------
    |
    | Con `GEOAPIFY_API_KEY` se usa Geoapify en modo moto. Si no hay clave
    | (o el servicio falla), se estima con la distancia en línea recta, un
    | factor de rodeo y una velocidad promedio. La app recibe el mismo contrato.
    |
    */
    'routing' => [
        'road_factor' => (float) env('MOTORUTA_ROAD_FACTOR', 1.25),
        'average_speed_kmh' => (float) env('MOTORUTA_ROUTE_SPEED_KMH', 25),
        'average_speed_car_kmh' => (float) env('MOTORUTA_CAR_ROUTE_SPEED_KMH', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conductores
    |--------------------------------------------------------------------------
    |
    | Si la app deja de reportar ubicación durante este tiempo, el conductor se
    | marca desconectado y deja de recibir alertas. Es el respaldo para cuando
    | la app se cierra sin avisar o pierde la conexión.
    |
    */
    'drivers' => [
        'offline_after_minutes' => (int) env('MOTORUTA_DRIVER_OFFLINE_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tasa de cambio del dólar (BCV / fuente externa)
    |--------------------------------------------------------------------------
    |
    | La tasa se puede cargar manualmente desde el panel (/panel/tasa) o
    | actualizarse automáticamente con `php artisan tasa:actualizar`, que
    | consulta la URL configurada abajo. Por defecto apunta al BCV.
    */
    'exchange_rate' => [
        'api_url' => env('BCV_API_URL', 'https://www.bcv.org.ve/tasas-cambio'),
        'timeout' => (int) env('BCV_API_TIMEOUT', 10),
    ],

];
