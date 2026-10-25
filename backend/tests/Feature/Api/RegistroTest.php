<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Registro desde la app: pasajero y conductor, con y sin documentos en la
 * misma petición.
 */
class RegistroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    /** @param  array<string, mixed>  $extra */
    private function datosPasajero(array $extra = []): array
    {
        return array_merge([
            'role' => 'passenger',
            'first_name' => 'María',
            'last_name' => 'Pérez',
            'national_id' => '12345678',
            'email' => 'maria@gmail.com',
            'phone' => '0412-1234567',
            'address' => 'Av. Bolívar, casa 12, Maracay',
            'city_id' => City::query()->where('slug', 'barcelona')->value('id'),
            'password' => 'clave12345',
            'password_confirmation' => 'clave12345',
            'accept_terms' => true,
            'terms_version' => config('legal.terms_version'),
        ], $extra);
    }

    /** @param  array<string, mixed>  $extra */
    private function datosConductor(array $extra = []): array
    {
        return $this->datosPasajero(array_merge([
            'role' => 'driver',
            'vehicle_type' => 'moto',
            'vehicle_brand' => 'Bera',
            'vehicle_model' => 'BR 150',
            'vehicle_year' => 2021,
            'vehicle_plate' => 'ab123cd',
        ], $extra));
    }

    public function test_un_pasajero_se_registra_y_recibe_su_token(): void
    {
        $respuesta = $this->post('/api/auth/register', $this->datosPasajero());

        $respuesta->assertCreated()
            ->assertJsonPath('data.user.status', 'pending')
            ->assertJsonPath('data.user.role', 'passenger')
            ->assertJsonPath('data.user.email_verified', false)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'verification' => ['items']]]]);

        $usuario = User::firstWhere('email', 'maria@gmail.com');

        $this->assertSame('María Pérez', $usuario->name);
        $this->assertSame('04121234567', $usuario->phone, 'El teléfono se guarda normalizado.');
        $this->assertSame('Barcelona', $usuario->city?->name, 'La cuenta guarda su zona de registro.');
        $this->assertFalse($usuario->canOperate(), 'Nace en espera de verificación.');
        $this->assertNull($usuario->submitted_at, 'Sin documentos todavía no entra a la cola.');
        $this->assertNotNull($usuario->terms_accepted_at, 'Guarda cuándo aceptó.');
        $this->assertSame(config('legal.terms_version'), $usuario->terms_version);
    }

    public function test_no_se_puede_registrar_sin_aceptar_los_terminos(): void
    {
        $this->post('/api/auth/register', $this->datosPasajero([
            'accept_terms' => false,
        ]))->assertStatus(422)->assertJsonValidationErrors('accept_terms');

        $this->post('/api/auth/register', $this->datosPasajero([
            'accept_terms' => null,
        ]))->assertStatus(422)->assertJsonValidationErrors('accept_terms');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_la_version_de_los_terminos_debe_ser_la_vigente(): void
    {
        $this->post('/api/auth/register', $this->datosPasajero([
            'terms_version' => '2020-01-01',
        ]))->assertStatus(422)->assertJsonValidationErrors('terms_version');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_el_registro_normaliza_correo_cedula_telefono_y_placa(): void
    {
        $this->post('/api/auth/register', $this->datosConductor([
            'email' => '  CARLOS@Gmail.com ',
            'national_id' => '1.234.567',
            'phone' => '+58 414 7654321',
            'vehicle_plate' => 'ab-123-cd',
        ]))->assertCreated();

        $conductor = User::firstWhere('national_id', '1234567');

        $this->assertSame('carlos@gmail.com', $conductor->email);
        $this->assertSame('04147654321', $conductor->phone);
        $this->assertSame('AB123CD', $conductor->driverProfile->vehicle_plate);
    }

    public function test_el_conductor_se_registra_con_su_vehiculo(): void
    {
        $this->post('/api/auth/register', $this->datosConductor())->assertCreated();

        $conductor = User::firstWhere('email', 'maria@gmail.com');

        $this->assertTrue($conductor->isDriver());
        $this->assertSame('Bera', $conductor->driverProfile->vehicle_brand);
        $this->assertSame('moto', $conductor->driverProfile->vehicle_type->value);
        $this->assertSame(2021, $conductor->driverProfile->vehicle_year);
        $this->assertSame('Barcelona', $conductor->driverProfile->city?->name, 'El conductor opera en la zona elegida.');
        $this->assertFalse($conductor->driverProfile->is_online, 'Nadie arranca en línea.');
    }

    public function test_el_conductor_puede_registrarse_con_carro(): void
    {
        $this->post('/api/auth/register', $this->datosConductor([
            'vehicle_type' => 'carro',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Corolla',
            'vehicle_plate' => 'ac456de',
        ]))->assertCreated()
            ->assertJsonPath('data.user.driver_profile.vehicle_type', 'carro')
            ->assertJsonPath('data.user.driver_profile.vehicle_type_label', 'Carro');

        $conductor = User::firstWhere('email', 'maria@gmail.com');

        $this->assertSame('carro', $conductor->driverProfile->vehicle_type->value);
        $this->assertStringStartsWith('Carro · Toyota Corolla', $conductor->driverProfile->vehicleDescription());
    }

    public function test_el_tipo_de_vehiculo_debe_ser_moto_o_carro(): void
    {
        $this->post('/api/auth/register', $this->datosConductor([
            'vehicle_type' => 'bicicleta',
        ]))->assertStatus(422)->assertJsonValidationErrors('vehicle_type');
    }

    public function test_hay_que_elegir_una_ciudad_activa_al_registrarse(): void
    {
        $this->post('/api/auth/register', $this->datosPasajero(['city_id' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('city_id');

        $this->post('/api/auth/register', $this->datosPasajero(['city_id' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('city_id');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_la_app_puede_listar_las_ciudades_sin_iniciar_sesion(): void
    {
        $this->getJson('/api/ciudades')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonFragment(['name' => 'Lechería', 'state' => 'Anzoátegui'])
            ->assertJsonFragment(['name' => 'Maturín', 'state' => 'Monagas']);
    }

    public function test_los_documentos_pueden_ir_en_el_mismo_registro(): void
    {
        $respuesta = $this->post('/api/auth/register', $this->datosPasajero([
            'documentos' => [
                'cedula' => UploadedFile::fake()->image('cedula.jpg'),
                'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            ],
        ]));

        $respuesta->assertCreated()
            ->assertJsonPath('data.user.verification.uploaded', 2)
            ->assertJsonPath('data.user.verification.missing_labels', []);

        $usuario = User::firstWhere('email', 'maria@gmail.com');

        $this->assertSame(2, $usuario->documents()->count());
        $this->assertNotNull($usuario->fresh()->submitted_at, 'Con todo subido entra a la cola.');
        $this->assertSame(User::count(), User::query()->pendingReview()->count());

        foreach ($usuario->documents as $documento) {
            Storage::disk('local')->assertExists($documento->file_path);
        }

        $this->assertNotNull($usuario->fresh()->avatar);
        Storage::disk('public')->assertExists($usuario->fresh()->avatar);
    }

    public function test_solo_se_aceptan_correos_de_gmail(): void
    {
        $this->post('/api/auth/register', $this->datosPasajero(['email' => 'maria@hotmail.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_no_se_puede_registrar_dos_veces_la_misma_cedula_correo_o_placa(): void
    {
        User::factory()->passenger()->create([
            'national_id' => '12345678',
            'email' => 'maria@gmail.com',
        ]);

        $this->post('/api/auth/register', $this->datosPasajero())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['national_id', 'email']);
    }

    public function test_el_conductor_no_puede_registrarse_sin_los_datos_del_vehiculo(): void
    {
        $this->post('/api/auth/register', $this->datosPasajero(['role' => 'driver']))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'vehicle_type', 'vehicle_brand', 'vehicle_model', 'vehicle_year', 'vehicle_plate',
            ]);
    }

    public function test_un_pasajero_no_puede_colar_documentos_de_conductor(): void
    {
        $this->post('/api/auth/register', $this->datosPasajero([
            'documentos' => [
                'cedula' => UploadedFile::fake()->image('cedula.jpg'),
                'rcv' => UploadedFile::fake()->image('rcv.jpg'),
                'foto_vehiculo' => UploadedFile::fake()->image('vehiculo.jpg'),
            ],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('documentos');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_no_se_puede_registrar_al_auditor_desde_la_app(): void
    {
        $this->post('/api/auth/register', $this->datosPasajero(['role' => 'auditor']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_la_contrasena_debe_coincidir_y_ser_minimo_de_ocho_con_letra_y_numero(): void
    {
        $this->post('/api/auth/register', $this->datosPasajero([
            'password_confirmation' => 'otra-clave-123',
        ]))->assertStatus(422)->assertJsonValidationErrors('password');

        $this->post('/api/auth/register', $this->datosPasajero([
            'password' => 'sololetras',
            'password_confirmation' => 'sololetras',
        ]))->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_los_documentos_deben_ser_imagen_o_pdf(): void
    {
        $this->post('/api/auth/register', $this->datosPasajero([
            'documentos' => ['cedula' => UploadedFile::fake()->create('virus.exe', 10)],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('documentos.cedula');
    }

    public function test_al_registrarse_se_envia_el_correo_de_verificacion(): void
    {
        $this->post('/api/auth/register', $this->datosPasajero())->assertCreated();

        $usuario = User::firstWhere('email', 'maria@gmail.com');

        $this->assertFalse($usuario->hasVerifiedEmail());
    }
}
