<?php

namespace Tests\Feature\Push;

use App\Enums\DocumentType;
use App\Events\NotificacionCreada;
use App\Events\OfertaViajeCreada;
use App\Events\ViajeActualizado;
use App\Listeners\EnviarPushDeNotificacion;
use App\Listeners\EnviarPushDeOferta;
use App\Listeners\EnviarPushDeViaje;
use App\Models\DeviceToken;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\User;
use App\Models\UserDocument;
use App\Notifications\DocumentoPorVencer;
use App\Services\FcmService;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Los listeners de push reciben los eventos de viajes y avisos, eligen al
 * destinatario correcto y mandan el mensaje a todos sus equipos.
 */
class PushListenersTest extends TestCase
{
    use RefreshDatabase;

    private function tokenPara(User $usuario, string $token = 'token-del-equipo'): void
    {
        DeviceToken::query()->create([
            'user_id' => $usuario->getKey(),
            'token' => $token,
            'platform' => 'android',
        ]);
    }

    public function test_los_eventos_encolan_los_listeners_de_push(): void
    {
        Queue::fake();

        $conductor = User::factory()->driver()->approved()->create();
        $viaje = Trip::factory()->assignedTo($conductor)->create();
        $oferta = TripOffer::factory()->for($viaje)->for($conductor, 'driver')->create();
        $persona = User::factory()->driver()->approved()->create();

        OfertaViajeCreada::dispatch($oferta);
        ViajeActualizado::dispatch($viaje, [$viaje->passenger_id, $viaje->driver_id], 'aceptado');
        NotificacionCreada::dispatch($persona, null);

        foreach ([
            EnviarPushDeOferta::class,
            EnviarPushDeViaje::class,
            EnviarPushDeNotificacion::class,
        ] as $listener) {
            Queue::assertPushed(
                CallQueuedListener::class,
                fn (CallQueuedListener $trabajo): bool => $trabajo->class === $listener,
            );
        }
    }

    public function test_la_oferta_avisa_al_conductor_con_la_distancia(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        $viaje = Trip::factory()->assignedTo($conductor)->create();
        $oferta = TripOffer::factory()->for($viaje)->for($conductor, 'driver')->create([
            'distance_to_pickup_km' => 1.2,
        ]);

        $this->tokenPara($conductor, 'token-conductor');

        $this->mock(FcmService::class, function ($mock) use ($oferta): void {
            $mock->shouldReceive('enviar')
                ->once()
                ->with(
                    'token-conductor',
                    'Nuevo viaje cerca de ti',
                    Mockery::on(fn (string $cuerpo): bool => str_contains($cuerpo, 'Recogida a 1,2 km')),
                    Mockery::on(fn (array $datos): bool => $datos['tipo'] === 'oferta'
                        && $datos['offer_id'] === $oferta->getKey()),
                )
                ->andReturn(true);
        });

        (new EnviarPushDeOferta(app(FcmService::class)))->handle(
            new OfertaViajeCreada($oferta),
        );
    }

    public function test_la_aceptacion_avisa_al_pasajero_y_no_al_conductor(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        $viaje = Trip::factory()->assignedTo($conductor)->create();

        $this->tokenPara($viaje->passenger, 'token-pasajero');
        $this->tokenPara($conductor, 'token-conductor');

        $this->mock(FcmService::class, function ($mock): void {
            $mock->shouldReceive('enviar')
                ->once()
                ->with(
                    'token-pasajero',
                    'Tu conductor aceptó',
                    'El conductor va en camino al punto de recogida.',
                    Mockery::on(fn (array $datos): bool => $datos['tipo'] === 'viaje'
                        && $datos['motivo'] === 'aceptado'),
                )
                ->andReturn(true);
        });

        (new EnviarPushDeViaje(app(FcmService::class)))->handle(
            new ViajeActualizado(
                $viaje,
                [$viaje->passenger_id, $viaje->driver_id],
                'aceptado',
            ),
        );
    }

    public function test_la_cancelacion_del_pasajero_avisa_al_conductor(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        $viaje = Trip::factory()->assignedTo($conductor)->create();

        $this->tokenPara($conductor, 'token-conductor');

        $this->mock(FcmService::class, function ($mock): void {
            $mock->shouldReceive('enviar')
                ->once()
                ->with(
                    'token-conductor',
                    'El pasajero canceló el viaje',
                    'El viaje fue cancelado por el pasajero.',
                    Mockery::on(fn (array $datos): bool => $datos['motivo'] === 'cancelado'),
                )
                ->andReturn(true);
        });

        (new EnviarPushDeViaje(app(FcmService::class)))->handle(
            new ViajeActualizado(
                $viaje,
                [$viaje->passenger_id, $viaje->driver_id],
                'cancelado',
            ),
        );
    }

    public function test_un_aviso_de_la_bandeja_se_convierte_en_push(): void
    {
        Queue::fake();

        $conductor = User::factory()->driver()->approved()->create();
        $documento = UserDocument::factory()
            ->for($conductor)
            ->ofType(DocumentType::DriversLicense)
            ->approved()
            ->create(['expires_at' => now()->addDays(5)->toDateString()]);

        $conductor->notify(new DocumentoPorVencer($documento, 5));
        $aviso = $conductor->notifications()->firstOrFail();

        $this->tokenPara($conductor, 'token-conductor');

        $this->mock(FcmService::class, function ($mock) use ($aviso): void {
            $mock->shouldReceive('enviar')
                ->once()
                ->with(
                    'token-conductor',
                    'Documento por vencer',
                    Mockery::on(fn (string $cuerpo): bool => str_contains($cuerpo, 'Vuelve a subirla')
                        || str_contains($cuerpo, 'Tenla lista')),
                    Mockery::on(fn (array $datos): bool => $datos['tipo'] === 'aviso'
                        && $datos['notification_id'] === $aviso->id),
                )
                ->andReturn(true);
        });

        (new EnviarPushDeNotificacion(app(FcmService::class)))->handle(
            new NotificacionCreada($conductor, (string) $aviso->getKey()),
        );
    }
}
