<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autorización de canales privados para la app móvil.
 *
 * La app pide el token de suscripción a `/api/broadcasting/auth` con su token
 * de Sanctum. Esto es la base del tiempo real de F4 (Reverb).
 */
class BroadcastingAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Las pruebas corren con el broadcaster en `null`; aquí se necesita uno
        // real para firmar el token. Reverb/Pusher firma en local, sin red.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'clave-de-prueba',
            'broadcasting.connections.reverb.secret' => 'secreto-de-prueba',
            'broadcasting.connections.reverb.app_id' => 'app-de-prueba',
        ]);

        // `routes/channels.php` se cargó al arrancar con el broadcaster `null`,
        // así que los canales hay que registrarlos en la conexión activa.
        require base_path('routes/channels.php');
    }

    public function test_la_app_autoriza_su_propio_canal_privado_con_el_token(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.'.$pasajero->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_la_app_no_autoriza_el_canal_de_otra_persona(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $otro = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.'.$otro->id,
            ])
            ->assertForbidden();
    }

    public function test_sin_token_no_autoriza_canales_privados(): void
    {
        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.1',
        ])->assertUnauthorized();
    }
}
