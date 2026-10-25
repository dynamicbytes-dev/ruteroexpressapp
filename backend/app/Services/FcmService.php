<?php

namespace App\Services;

use App\Models\DeviceToken;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Envío de notificaciones push con Firebase Cloud Messaging (HTTP v1).
 *
 * Usa la cuenta de servicio de Firebase para firmar un JWT, lo cambia por un
 * token de OAuth2 y lo cachea hasta que está por vencer. Si no hay credenciales
 * configuradas, `enviar()` devuelve `false` sin romper la operación del viaje.
 */
class FcmService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const FCM_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    public function __construct(
        private readonly ?string $projectId = null,
        private readonly ?string $credentialsPath = null,
    ) {}

    public function configurado(): bool
    {
        try {
            return $this->projectId() !== '' && $this->credentials() !== [];
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Envía una notificación a un token concreto.
     *
     * Devuelve `true` si Firebase aceptó el mensaje. Si el token ya no existe,
     * lo elimina de la base para no seguir intentando con un equipo viejo.
     *
     * @param  array<string, mixed>  $datos
     */
    public function enviar(
        string $token,
        string $titulo,
        string $cuerpo,
        array $datos = [],
    ): bool {
        if ($token === '' || ! $this->configurado()) {
            return false;
        }

        try {
            $acceso = $this->accessToken();
        } catch (RuntimeException $error) {
            Log::warning('FCM: no se pudo obtener el token de acceso.', [
                'error' => $error->getMessage(),
            ]);

            return false;
        }

        $mensaje = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $titulo,
                    'body' => $cuerpo,
                ],
                'data' => $this->datosComoTexto($datos),
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'default_sound' => true,
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                        'apns-push-type' => 'alert',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        $respuesta = Http::withToken($acceso)
            ->acceptJson()
            ->timeout(10)
            ->post(sprintf(self::FCM_URL, $this->projectId()), $mensaje);

        if ($respuesta->successful()) {
            return true;
        }

        $codigo = $respuesta->json('error.details.0.errorCode');
        $estado = $respuesta->json('error.status');
        $invalido = $respuesta->status() === 404
            || in_array($codigo, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)
            || $estado === 'NOT_FOUND';

        if ($invalido) {
            DeviceToken::query()->where('token', $token)->delete();
        } else {
            Log::warning('FCM: Firebase rechazó el mensaje.', [
                'status' => $respuesta->status(),
                'error' => $respuesta->json('error.message'),
            ]);
        }

        return false;
    }

    /** @return array<string, string> */
    private function datosComoTexto(array $datos): array
    {
        $limpios = [];

        foreach ($datos as $clave => $valor) {
            if ($valor === null) {
                continue;
            }

            $limpios[(string) $clave] = is_bool($valor)
                ? ($valor ? '1' : '0')
                : (string) $valor;
        }

        return $limpios;
    }

    private function accessToken(): string
    {
        $credenciales = $this->credentials();
        $cacheKey = 'fcm_access_token:'.sha1(
            $this->projectId().'|'.($credenciales['client_email'] ?? '')
        );

        $guardado = Cache::get($cacheKey);

        if (is_string($guardado) && $guardado !== '') {
            return $guardado;
        }

        $ahora = time();
        $afirmacion = JWT::encode(
            [
                'iss' => $credenciales['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $ahora,
                'exp' => $ahora + 3600,
            ],
            $credenciales['private_key'],
            'RS256',
        );

        $respuesta = Http::asForm()
            ->timeout(10)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $afirmacion,
            ]);

        if (! $respuesta->successful()) {
            throw new RuntimeException(
                'Google rechazó la cuenta de servicio: '.$respuesta->status()
            );
        }

        $token = $respuesta->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google no devolvió un token de acceso.');
        }

        $segundos = max(60, (int) $respuesta->json('expires_in', 3600) - 60);
        Cache::put($cacheKey, $token, now()->addSeconds($segundos));

        return $token;
    }

    /** @return array<string, mixed> */
    private function credentials(): array
    {
        $ruta = $this->credentialsPath ?? (string) config('services.fcm.credentials');

        if ($ruta === '') {
            throw new RuntimeException('FCM no tiene ruta de credenciales.');
        }

        if (! str_starts_with($ruta, DIRECTORY_SEPARATOR)) {
            $ruta = base_path($ruta);
        }

        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw new RuntimeException('No se encontró el JSON de la cuenta de servicio.');
        }

        $datos = json_decode((string) file_get_contents($ruta), true);

        if (! is_array($datos)
            || empty($datos['private_key'])
            || empty($datos['client_email'])) {
            throw new RuntimeException('El JSON de Firebase no tiene las claves esperadas.');
        }

        return $datos;
    }

    private function projectId(): string
    {
        $desdeConfig = $this->projectId ?? (string) config('services.fcm.project_id');

        if ($desdeConfig !== '') {
            return $desdeConfig;
        }

        return (string) ($this->credentials()['project_id'] ?? '');
    }
}
