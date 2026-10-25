<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Edición de los datos personales desde la app.
 */
class PerfilApiTest extends TestCase
{
    use RefreshDatabase;

    private function pasajero(): User
    {
        return User::factory()->passenger()->create([
            'password' => 'clave12345',
            'email' => 'persona@gmail.com',
            'national_id' => '12345678',
            'phone' => '04141234567',
            'address' => 'Av. Principal, Caracas',
        ]);
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        return [
            'first_name' => 'Ana',
            'last_name' => 'Rivas',
            'phone' => '04149876543',
            'address' => 'Calle Nueva, Caracas',
        ];
    }

    public function test_actualiza_nombre_telefono_y_direccion_sin_tocar_la_cedula(): void
    {
        $pasajero = $this->pasajero();

        $this->actingAs($pasajero, 'sanctum')
            ->patchJson('/api/perfil', $this->datos())
            ->assertOk()
            ->assertJsonPath('data.user.first_name', 'Ana')
            ->assertJsonPath('data.user.phone', '04149876543')
            ->assertJsonPath('data.email_verification_sent', false);

        $pasajero->refresh();

        $this->assertSame('Ana Rivas', $pasajero->name);
        $this->assertSame('12345678', $pasajero->national_id);
        $this->assertSame('persona@gmail.com', $pasajero->email);
    }

    public function test_la_cedula_no_se_puede_editar(): void
    {
        $pasajero = $this->pasajero();

        $this->actingAs($pasajero, 'sanctum')
            ->patchJson('/api/perfil', array_merge($this->datos(), [
                'national_id' => '99999999',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('national_id');

        $this->assertSame('12345678', $pasajero->fresh()->national_id);
    }

    public function test_cambiar_el_correo_exige_la_contrasena_actual_y_lo_deja_sin_verificar(): void
    {
        Notification::fake();

        $pasajero = $this->pasajero();

        $this->actingAs($pasajero, 'sanctum')
            ->patchJson('/api/perfil', array_merge($this->datos(), [
                'email' => 'nueva@gmail.com',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->actingAs($pasajero, 'sanctum')
            ->patchJson('/api/perfil', array_merge($this->datos(), [
                'email' => 'nueva@gmail.com',
                'current_password' => 'clave12345',
            ]))
            ->assertOk()
            ->assertJsonPath('data.email_verification_sent', true);

        $pasajero->refresh();

        $this->assertSame('nueva@gmail.com', $pasajero->email);
        $this->assertNull($pasajero->email_verified_at);
        Notification::assertSentTo($pasajero, VerifyEmail::class);
    }

    public function test_rechaza_un_correo_que_no_es_de_gmail(): void
    {
        $pasajero = $this->pasajero();

        $this->actingAs($pasajero, 'sanctum')
            ->patchJson('/api/perfil', array_merge($this->datos(), [
                'email' => 'nueva@hotmail.com',
                'current_password' => 'clave12345',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_puede_cambiar_correo_a_uno_que_usa_un_conductor_pero_no_otro_pasajero(): void
    {
        $pasajero = $this->pasajero();

        // Conductor con el correo objetivo (permitido: roles distintos)
        User::factory()->driver()->create(['email' => 'conductor_existente@gmail.com']);

        // Otro pasajero con correo distinto (no permitido: mismo rol)
        User::factory()->passenger()->create(['email' => 'otro_pasajero@gmail.com']);

        // Intentar usar el correo de otro pasajero -> error 422
        $this->actingAs($pasajero, 'sanctum')
            ->patchJson('/api/perfil', array_merge($this->datos(), [
                'email' => 'otro_pasajero@gmail.com',
                'current_password' => 'clave12345',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        // Usar el correo del conductor -> permitido (200 OK)
        $this->actingAs($pasajero, 'sanctum')
            ->patchJson('/api/perfil', array_merge($this->datos(), [
                'email' => 'conductor_existente@gmail.com',
                'current_password' => 'clave12345',
            ]))
            ->assertOk();

        $this->assertSame('conductor_existente@gmail.com', $pasajero->fresh()->email);
    }

    public function test_actualiza_foto_de_perfil_con_avatar_valido(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $pasajero = $this->pasajero();
        $foto = UploadedFile::fake()->image('mi_foto.jpg', 400, 400);

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/perfil', array_merge($this->datos(), [
                'avatar' => $foto,
            ]))
            ->assertOk()
            ->assertJsonPath('data.user.avatar', fn ($url): bool => is_string($url) && str_contains($url, '/storage/avatars/'));

        $pasajero->refresh();
        $this->assertNotNull($pasajero->avatar);
        Storage::disk('public')->assertExists($pasajero->avatar);
        $this->assertSame(
            Storage::disk('public')->url($pasajero->avatar),
            $pasajero->avatarUrl(),
        );
    }

    public function test_rechaza_foto_de_perfil_con_archivo_no_imagen(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $pasajero = $this->pasajero();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/perfil', array_merge($this->datos(), [
                'avatar' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');
    }

    public function test_actualiza_solo_avatar_sin_campos_adicionales(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $pasajero = $this->pasajero();
        $foto = UploadedFile::fake()->image('selfie.png', 400, 400);

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/perfil', [
                'avatar' => $foto,
            ])
            ->assertOk()
            ->assertJsonPath('data.user.avatar', fn ($url): bool => is_string($url) && str_contains($url, '/storage/avatars/'));

        $this->assertNotNull($pasajero->fresh()->avatar);
    }

    public function test_endpoint_dedicado_perfil_avatar(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $pasajero = $this->pasajero();
        $foto = UploadedFile::fake()->image('avatar_dedicado.jpg', 300, 300);

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/perfil/avatar', [
                'avatar' => $foto,
            ])
            ->assertOk()
            ->assertJsonPath('data.user.avatar', fn ($url): bool => is_string($url) && str_contains($url, '/storage/avatars/'));

        $this->assertNotNull($pasajero->fresh()->avatar);
    }
}
