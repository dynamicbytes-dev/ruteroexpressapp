<?php

namespace App\Providers;

use App\Events\NotificacionCreada;
use App\Events\OfertaViajeCreada;
use App\Events\ViajeActualizado;
use App\Listeners\EnviarPushDeNotificacion;
use App\Listeners\EnviarPushDeOferta;
use App\Listeners\EnviarPushDeViaje;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Una sola regla de contraseña en toda la plataforma: la del registro de
        // la app, la del panel y la de recuperación. Las cuentas de auditoría se
        // crean por consola con un mínimo más alto (php artisan motoruta:crear-auditor).
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Solo el auditor principal (clase A), dueño del sistema, puede crear
        // otras cuentas de auditor. Los auditores que él crea no pueden hacerlo.
        Gate::define(
            'crear-auditores',
            fn (User $user): bool => $user->isAuditor() && $user->isOwner(),
        );

        // Autorización de canales privados para la app móvil. La ruta por defecto
        // (`/broadcasting/auth`) usa el guard `web` (sesión del panel); la app se
        // autentica con el token de Sanctum, así que expone su propia ruta en
        // `/api/broadcasting/auth`. La app la usa como `authEndpoint`.
        Broadcast::routes([
            'prefix' => 'api',
            'middleware' => ['auth:sanctum'],
        ]);

        // Cuando se guarda un aviso en la bandeja, la app recibe la señal por
        // WebSocket y refresca `GET /api/notificaciones`. El aviso completo
        // sigue en la base de datos: el evento solo lleva el identificador.
        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if ($event->channel !== 'database' || ! $event->notifiable instanceof User) {
                return;
            }

            $notificacion = $event->response;

            NotificacionCreada::dispatch(
                $event->notifiable,
                $notificacion instanceof DatabaseNotification
                    ? (string) $notificacion->getKey()
                    : null,
            );
        });

        // Los listeners de push van a la cola. Así el mensaje sale después de
        // que la transacción del viaje haya confirmado y un fallo de Firebase no
        // revierte la operación principal.
        Event::listen(OfertaViajeCreada::class, EnviarPushDeOferta::class);
        Event::listen(ViajeActualizado::class, EnviarPushDeViaje::class);
        Event::listen(NotificacionCreada::class, EnviarPushDeNotificacion::class);
    }
}
