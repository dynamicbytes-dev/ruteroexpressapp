<?php

namespace Tests\Feature\Push;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cliente HTTP v1 de Firebase: firma de la cuenta de servicio, envío y limpieza
 * de tokens que Firebase ya no reconoce.
 */
class FcmServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $credentialsPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $llave = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $this->assertNotFalse($llave);

        openssl_pkey_export($llave, $privada);

        $this->credentialsPath = storage_path('framework/testing/fcm-'.uniqid().'.json');
        File::ensureDirectoryExists(dirname($this->credentialsPath));
        File::put($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'rutero-test',
            'client_email' => 'firebase@rutero-test.iam.gserviceaccount.com',
            'private_key' => $privada,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], JSON_THROW_ON_ERROR));

        config([
            'services.fcm.project_id' => 'rutero-test',
            'services.fcm.credentials' => $this->credentialsPath,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->credentialsPath !== '' && is_file($this->credentialsPath)) {
            unlink($this->credentialsPath);
        }

        parent::tearDown();
    }

    public function test_sin_credenciales_no_intenta_enviar(): void
    {
        config([
            'services.fcm.project_id' => '',
            'services.fcm.credentials' => '',
        ]);

        Http::fake();

        $this->assertFalse(app(FcmService::class)->enviar(
            'token-123',
            'Título',
            'Cuerpo',
        ));

        Http::assertNothingSent();
    }

    public function test_envia_el_mensaje_con_el_token_de_google(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'acceso-de-prueba',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/v1/projects/rutero-test/messages:send' => Http::response([]),
        ]);

        $enviado = app(FcmService::class)->enviar(
            'token-del-equipo',
            'Nuevo viaje',
            'Recogida a 1,2 km.',
            ['tipo' => 'oferta', 'trip_id' => 42],
        );

        $this->assertTrue($enviado);

        Http::assertSent(function (Request $peticion): bool {
            return $peticion->url() === 'https://oauth2.googleapis.com/token'
                && $peticion['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && is_string($peticion['assertion']);
        });

        Http::assertSent(function (Request $peticion): bool {
            return $peticion->url() === 'https://fcm.googleapis.com/v1/projects/rutero-test/messages:send'
                && $peticion->hasHeader('Authorization', 'Bearer acceso-de-prueba')
                && $peticion['message']['token'] === 'token-del-equipo'
                && $peticion['message']['notification']['title'] === 'Nuevo viaje'
                && $peticion['message']['notification']['body'] === 'Recogida a 1,2 km.'
                && $peticion['message']['data']['tipo'] === 'oferta'
                && $peticion['message']['data']['trip_id'] === '42';
        });

        Http::assertSentCount(2);
    }

    public function test_el_token_de_acceso_se_reutiliza_durante_la_hora(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'acceso-cacheado',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/v1/projects/rutero-test/messages:send' => Http::response([]),
        ]);

        $servicio = app(FcmService::class);

        $this->assertTrue($servicio->enviar('token-1', 'Uno', 'Cuerpo uno'));
        $this->assertTrue($servicio->enviar('token-2', 'Dos', 'Cuerpo dos'));

        $peticionesAlToken = 0;

        Http::assertSent(function (Request $peticion) use (&$peticionesAlToken): bool {
            if ($peticion->url() === 'https://oauth2.googleapis.com/token') {
                $peticionesAlToken++;
            }

            return true;
        });

        $this->assertSame(1, $peticionesAlToken);
    }

    public function test_elimina_el_token_que_firebase_ya_no_reconoce(): void
    {
        $conductor = User::factory()->driver()->approved()->create();

        DeviceToken::query()->create([
            'user_id' => $conductor->getKey(),
            'token' => 'token-vencido',
            'platform' => 'android',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'acceso-de-prueba',
                'expires_in' => 3600,
            ]),
            'https://fcm.googleapis.com/v1/projects/rutero-test/messages:send' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => 'Requested entity was not found.',
                    'status' => 'NOT_FOUND',
                    'details' => [
                        ['errorCode' => 'UNREGISTERED'],
                    ],
                ],
            ], 404),
        ]);

        $this->assertFalse(app(FcmService::class)->enviar(
            'token-vencido',
            'Aviso',
            'Mensaje',
        ));

        $this->assertDatabaseMissing('device_tokens', ['token' => 'token-vencido']);
    }
}
