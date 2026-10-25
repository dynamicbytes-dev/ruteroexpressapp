<?php

namespace Tests\Feature\Api;

use App\Enums\DocumentType;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\UserDocument;
use App\Notifications\RestablecerContrasena;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Sesión de la app móvil: entrar, salir, consultar el perfil y recuperar la
 * contraseña.
 */
class AutenticacionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pasajero_entra_con_su_correo_y_contrasena(): void
    {
        $pasajero = User::factory()->passenger()->create(['password' => 'clave12345']);

        $respuesta = $this->postJson('/api/auth/login', [
            'email' => $pasajero->email,
            'password' => 'clave12345',
            'device_name' => 'pixel-7',
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('data.user.id', $pasajero->id)
            ->assertJsonStructure(['data' => ['token', 'token_type']]);

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'pixel-7']);
    }

    public function test_una_cuenta_en_espera_de_verificacion_si_puede_entrar(): void
    {
        $pasajero = User::factory()->passenger()->create(['password' => 'clave12345']);

        $this->postJson('/api/auth/login', [
            'email' => $pasajero->email,
            'password' => 'clave12345',
        ])->assertOk()
            ->assertJsonPath('data.user.status', 'pending')
            ->assertJsonPath('data.user.can_operate', false);
    }

    public function test_no_entra_con_contrasena_incorrecta(): void
    {
        $pasajero = User::factory()->passenger()->create(['password' => 'clave12345']);

        $this->postJson('/api/auth/login', [
            'email' => $pasajero->email,
            'password' => 'otra-clave',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_el_auditor_no_entra_por_la_app(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'clave12345']);

        $this->postJson('/api/auth/login', [
            'email' => $auditor->email,
            'password' => 'clave12345',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_el_perfil_pide_token(): void
    {
        $this->getJson('/api/perfil')->assertUnauthorized();
    }

    public function test_el_perfil_dice_que_falta_subir_documentos(): void
    {
        $pasajero = User::factory()->passenger()->create();
        $pasajero->forceFill(['submitted_at' => null])->save();

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/perfil')
            ->assertOk()
            ->assertJsonPath('data.next_step.key', 'upload_documents')
            ->assertJsonPath('data.user.verification.uploaded', 0)
            ->assertJsonCount(2, 'data.user.verification.items');
    }

    public function test_el_perfil_dice_que_esta_en_revision_cuando_ya_envio_todo(): void
    {
        $pasajero = User::factory()->passenger()->create();

        foreach ([DocumentType::NationalId, DocumentType::Selfie] as $tipo) {
            UserDocument::factory()->for($pasajero)->ofType($tipo)->create();
        }

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/perfil')
            ->assertOk()
            ->assertJsonPath('data.next_step.key', 'waiting_review');
    }

    public function test_al_salir_se_revoca_solo_el_token_del_dispositivo(): void
    {
        $pasajero = User::factory()->passenger()->create();

        $otroDispositivo = $pasajero->createToken('tablet')->plainTextToken;
        $token = $pasajero->createToken('celular')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'tablet']);

        $this->withHeader('Authorization', "Bearer {$otroDispositivo}")
            ->getJson('/api/perfil')
            ->assertOk();
    }

    public function test_al_salir_el_conductor_queda_desconectado(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        $perfil = DriverProfile::factory()->for($conductor)->online()->create([
            'last_location_at' => now(),
        ]);
        $token = $conductor->createToken('celular')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertFalse($perfil->fresh()->is_online);
        $this->assertNull($perfil->fresh()->went_online_at);
    }

    public function test_la_recuperacion_de_contrasena_envia_un_correo_con_enlace_web(): void
    {
        Notification::fake();

        $pasajero = User::factory()->passenger()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $pasajero->email])
            ->assertOk();

        Notification::assertSentTo($pasajero, RestablecerContrasena::class, function ($notificacion) use ($pasajero) {
            $url = $notificacion->toMail($pasajero)->actionUrl;
            $partes = parse_url($url);

            $this->assertIsArray($partes);
            $this->assertContains($partes['scheme'] ?? null, ['http', 'https'], 'El enlace debe ser absoluto.');
            $this->assertNotEmpty($partes['host'] ?? null, 'El enlace debe traer un host.');
            $this->assertStringContainsString('/restablecer/', $url);
            $this->assertStringContainsString(urlencode($pasajero->email), $url);

            return true;
        });
    }

    public function test_la_recuperacion_responde_igual_con_un_correo_que_no_existe(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'nadie@gmail.com'])
            ->assertOk();

        Notification::assertNothingSent();
    }

    public function test_se_puede_reenviar_el_correo_de_verificacion(): void
    {
        Notification::fake();

        $pasajero = User::factory()->passenger()->unverified()->create();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/auth/email/verification-notification')
            ->assertOk();

        Notification::assertSentTo($pasajero, VerifyEmail::class);
    }
}
