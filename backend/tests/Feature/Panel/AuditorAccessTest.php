<?php

namespace Tests\Feature\Panel;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El panel web es exclusivo del auditor:
 * los pasajeros y conductores operan desde la app móvil.
 */
class AuditorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_invitado_es_enviado_al_login_del_panel(): void
    {
        $this->get('/panel')->assertRedirect('/panel/login');
    }

    public function test_la_raiz_redirige_al_panel(): void
    {
        $this->get('/')->assertRedirect('/panel');
    }

    public function test_no_existe_registro_publico(): void
    {
        $this->get('/panel/register')->assertNotFound();
    }

    public function test_el_auditor_entra_al_panel(): void
    {
        $auditor = User::factory()->auditor()->create(['email' => 'auditor@gmail.com']);

        $this->actingAs($auditor)
            ->get('/panel')
            ->assertOk()
            ->assertSee('Panel de auditoría')
            ->assertSee('Usuarios por verificar');
    }

    public function test_el_auditor_inicia_sesion_con_su_correo_y_contrasena(): void
    {
        User::factory()->auditor()->create([
            'email' => 'auditor@gmail.com',
            'password' => 'password',
        ]);

        $this->post('/panel/login', [
            'email' => 'auditor@gmail.com',
            'password' => 'password',
        ])->assertRedirect('/panel');

        $this->assertAuthenticated();
    }

    public function test_el_pasajero_no_puede_iniciar_sesion_en_el_panel(): void
    {
        User::factory()->passenger()->approved()->create([
            'email' => 'maria@gmail.com',
            'password' => 'password',
        ]);

        $this->post('/panel/login', [
            'email' => 'maria@gmail.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_el_conductor_no_puede_iniciar_sesion_en_el_panel(): void
    {
        User::factory()->driver()->approved()->create([
            'email' => 'pedro@gmail.com',
            'password' => 'password',
        ]);

        $this->post('/panel/login', [
            'email' => 'pedro@gmail.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_no_auditor_autenticado_recibe_403_en_el_panel(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero)->get('/panel')->assertForbidden();
    }

    public function test_el_auditor_puede_cambiar_su_contrasena_desde_el_panel(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'password']);

        $this->actingAs($auditor)->get('/panel/profile')->assertOk();

        $this->actingAs($auditor)->patch('/panel/profile', [
            'first_name' => 'Ana',
            'last_name' => 'Rivas',
            'email' => $auditor->email,
        ])->assertRedirect('/panel/profile');

        $this->assertSame('Ana', $auditor->fresh()->first_name);
    }

    public function test_el_auditor_principal_ve_el_listado_y_el_formulario_de_auditores(): void
    {
        $auditor = User::factory()->auditor()->owner()->create();

        $this->actingAs($auditor)
            ->get('/panel/auditores')
            ->assertOk()
            ->assertSee('Nuevo auditor');

        $this->actingAs($auditor)
            ->get('/panel/auditores/nuevo')
            ->assertOk()
            ->assertSee('Crear auditor');
    }

    public function test_el_auditor_principal_puede_registrar_otro_auditor(): void
    {
        $auditor = User::factory()->auditor()->owner()->create();

        $this->actingAs($auditor)
            ->post('/panel/auditores', [
                'first_name' => 'Lucía',
                'last_name' => 'Márquez',
                'national_id' => '20123456',
                'email' => 'LUCIA@RUTEROEXPRESS.COM',
                'phone' => '0414-7654321',
                'password' => 'clave-segura-123',
                'password_confirmation' => 'clave-segura-123',
            ])
            ->assertRedirect('/panel/auditores')
            ->assertSessionHas('status');

        $nuevo = User::query()->where('email', 'lucia@ruteroexpress.com')->firstOrFail();

        $this->assertTrue($nuevo->isAuditor());
        $this->assertFalse($nuevo->isOwner());
        $this->assertSame(UserStatus::Approved, $nuevo->status);
        $this->assertNotNull($nuevo->email_verified_at);
        $this->assertTrue($nuevo->hasRole('auditor'));

        // La contraseña definida sirve para entrar al panel.
        auth()->logout();
        $this->post('/panel/login', [
            'email' => 'lucia@ruteroexpress.com',
            'password' => 'clave-segura-123',
        ])->assertRedirect('/panel');

        $this->assertAuthenticatedAs($nuevo);
        $this->get('/panel')->assertOk();
    }

    public function test_un_auditor_secundario_no_puede_crear_mas_auditores(): void
    {
        $principal = User::factory()->auditor()->owner()->create([
            'first_name' => 'Ana',
            'last_name' => 'Principal',
        ]);
        $secundario = User::factory()->auditor()->create([
            'first_name' => 'Luis',
            'last_name' => 'Secundario',
        ]);

        // Puede consultar quiénes tienen acceso, pero no ve el botón de alta.
        $this->actingAs($secundario)
            ->get('/panel/auditores')
            ->assertOk()
            ->assertSee('Ana Principal')
            ->assertSee('Auditor principal')
            ->assertDontSee('Nuevo auditor');

        $this->actingAs($secundario)
            ->get('/panel/auditores/nuevo')
            ->assertForbidden();

        $this->actingAs($secundario)
            ->post('/panel/auditores', [
                'first_name' => 'Intruso',
                'last_name' => 'Auditor',
                'national_id' => '20999999',
                'email' => 'intruso@ruteroexpress.com',
                'phone' => '0414-1112233',
                'password' => 'clave-segura-123',
                'password_confirmation' => 'clave-segura-123',
            ])
            ->assertForbidden();

        $this->assertSame(2, User::query()->count());
        $this->assertTrue($principal->fresh()->isOwner());
        $this->assertFalse($secundario->fresh()->isOwner());
    }

    public function test_no_se_puede_crear_un_auditor_con_datos_duplicados(): void
    {
        $auditor = User::factory()->auditor()->owner()->create();
        $existente = User::factory()->passenger()->create([
            'national_id' => '20123456',
            'email' => 'ocupado@ruteroexpress.com',
        ]);

        $this->actingAs($auditor)
            ->post('/panel/auditores', [
                'first_name' => 'Lucía',
                'last_name' => 'Márquez',
                'national_id' => $existente->national_id,
                'email' => $existente->email,
                'phone' => '0414-7654321',
                'password' => 'clave-segura-123',
                'password_confirmation' => 'clave-segura-123',
            ])
            ->assertSessionHasErrors(['national_id', 'email']);

        $this->assertSame(2, User::query()->count());
    }

    public function test_solo_los_roles_de_la_app_se_registran_solos(): void
    {
        $this->assertSame(
            [UserRole::Passenger, UserRole::Driver],
            UserRole::selfRegistrable(),
        );

        $this->assertSame(UserStatus::Pending, User::factory()->passenger()->make()->status);
    }

    public function test_el_auditor_principal_ve_el_boton_editar_y_puede_actualizar_usuario(): void
    {
        $principal = User::factory()->auditor()->owner()->create();
        $usuario = User::factory()->passenger()->approved()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Pérez',
            'phone' => '0412-1112233',
        ]);

        $this->actingAs($principal)
            ->get('/panel/usuarios?tab=aprobados')
            ->assertOk()
            ->assertSee('Editar')
            ->assertSee('Revisar');

        $this->actingAs($principal)
            ->patch("/panel/usuarios/{$usuario->id}", [
                'first_name' => 'Carlos Modificado',
                'last_name' => 'Pérez Nuevo',
                'phone' => '0412-9998877',
                'status' => UserStatus::Approved->value,
            ])
            ->assertRedirect();

        $actualizado = $usuario->fresh();
        $this->assertSame('Carlos Modificado', $actualizado->first_name);
        $this->assertSame('Pérez Nuevo', $actualizado->last_name);
        $this->assertSame('0412-9998877', $actualizado->phone);
    }

    public function test_el_auditor_secundario_no_ve_el_boton_editar_y_recibe_403_al_intentar_actualizar(): void
    {
        $secundario = User::factory()->auditor()->create(); // is_owner = false
        $usuario = User::factory()->passenger()->approved()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Pérez',
            'phone' => '0412-1112233',
        ]);

        $this->actingAs($secundario)
            ->get('/panel/usuarios?tab=aprobados')
            ->assertOk()
            ->assertSee('Revisar')
            ->assertDontSee('Editar');

        $this->actingAs($secundario)
            ->patch("/panel/usuarios/{$usuario->id}", [
                'first_name' => 'Intento Hack',
                'last_name' => 'Fallido',
                'phone' => '0412-0000000',
                'status' => UserStatus::Approved->value,
            ])
            ->assertForbidden();

        $this->assertSame('Carlos', $usuario->fresh()->first_name);
    }

    public function test_el_auditor_principal_ve_el_boton_editar_en_auditores_y_puede_actualizarlo(): void
    {
        $principal = User::factory()->auditor()->owner()->create();
        $secundario = User::factory()->auditor()->create([
            'first_name' => 'Marcos',
            'last_name' => 'González',
            'phone' => '0414-1112233',
            'is_owner' => false,
        ]);

        $this->actingAs($principal)
            ->get('/panel/auditores')
            ->assertOk()
            ->assertSee('Editar')
            ->assertSee('Marcos González');

        $this->actingAs($principal)
            ->patch("/panel/auditores/{$secundario->id}", [
                'first_name' => 'Marcos Editado',
                'last_name' => 'González Promovido',
                'phone' => '0414-9998877',
                'status' => UserStatus::Approved->value,
                'is_owner' => true,
            ])
            ->assertRedirect('/panel/auditores')
            ->assertSessionHas('status');

        $actualizado = $secundario->fresh();
        $this->assertSame('Marcos Editado', $actualizado->first_name);
        $this->assertSame('González Promovido', $actualizado->last_name);
        $this->assertSame('0414-9998877', $actualizado->phone);
        $this->assertTrue($actualizado->isOwner());
    }

    public function test_el_auditor_secundario_no_ve_el_boton_editar_en_auditores_y_recibe_403_al_intentar_actualizar(): void
    {
        $secundario = User::factory()->auditor()->create(['is_owner' => false]);
        $otroAuditor = User::factory()->auditor()->create(['is_owner' => false, 'first_name' => 'Pedro']);

        $this->actingAs($secundario)
            ->get('/panel/auditores')
            ->assertOk()
            ->assertDontSee('Editar');

        $this->actingAs($secundario)
            ->patch("/panel/auditores/{$otroAuditor->id}", [
                'first_name' => 'Hackeado',
                'last_name' => 'Por Secundario',
                'phone' => '0416-0000000',
                'status' => UserStatus::Approved->value,
                'is_owner' => true,
            ])
            ->assertForbidden();

        $this->assertSame('Pedro', $otroAuditor->fresh()->first_name);
    }
}
