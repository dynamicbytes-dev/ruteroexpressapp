<?php

namespace Tests\Feature\Push;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registro y baja de los equipos que reciben notificaciones push.
 */
class DispositivosApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_equipos_piden_autenticacion(): void
    {
        $this->postJson('/api/dispositivos', [
            'token' => 'token-de-prueba',
            'platform' => 'android',
        ])->assertUnauthorized();

        $this->deleteJson('/api/dispositivos', [
            'token' => 'token-de-prueba',
        ])->assertUnauthorized();
    }

    public function test_registra_el_token_del_equipo(): void
    {
        $conductor = User::factory()->driver()->approved()->create();

        $this->actingAs($conductor, 'sanctum')
            ->postJson('/api/dispositivos', [
                'token' => 'token-android-123',
                'platform' => 'android',
                'device_name' => 'Pixel 7',
            ])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $conductor->getKey(),
            'token' => 'token-android-123',
            'platform' => 'android',
            'device_name' => 'Pixel 7',
        ]);
    }

    public function test_el_mismo_token_se_reasigna_si_otra_cuenta_inicia_sesion(): void
    {
        $primero = User::factory()->driver()->approved()->create();
        $segundo = User::factory()->driver()->approved()->create();

        DeviceToken::query()->create([
            'user_id' => $primero->getKey(),
            'token' => 'token-compartido',
            'platform' => 'android',
            'last_used_at' => now()->subDay(),
        ]);

        $this->actingAs($segundo, 'sanctum')
            ->postJson('/api/dispositivos', [
                'token' => 'token-compartido',
                'platform' => 'android',
            ])
            ->assertOk();

        $this->assertSame(1, DeviceToken::query()->count());
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $segundo->getKey(),
            'token' => 'token-compartido',
        ]);
    }

    public function test_elimina_solo_un_equipo_de_la_cuenta_autenticada(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        $otro = User::factory()->driver()->approved()->create();

        DeviceToken::query()->create([
            'user_id' => $conductor->getKey(),
            'token' => 'token-propio',
            'platform' => 'ios',
        ]);
        DeviceToken::query()->create([
            'user_id' => $otro->getKey(),
            'token' => 'token-ajeno',
            'platform' => 'android',
        ]);

        $this->actingAs($conductor, 'sanctum')
            ->deleteJson('/api/dispositivos', ['token' => 'token-propio'])
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'token-propio']);
        $this->assertDatabaseHas('device_tokens', ['token' => 'token-ajeno']);
    }

    public function test_valida_la_plataforma_del_equipo(): void
    {
        $conductor = User::factory()->driver()->approved()->create();

        $this->actingAs($conductor, 'sanctum')
            ->postJson('/api/dispositivos', [
                'token' => 'token-123',
                'platform' => 'windows',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('platform');
    }
}
