<?php

use App\Http\Controllers\Api\Auth\RecuperacionContrasenaController;
use App\Http\Controllers\Api\Auth\RegistroController;
use App\Http\Controllers\Api\Auth\SesionController;
use App\Http\Controllers\Api\Auth\VerificacionCorreoController;
use App\Http\Controllers\Api\BilleteraController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\DispositivoController;
use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\GeocodingController;
use App\Http\Controllers\Api\NotificacionController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\PerfilFotoController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\RecargaController;
use App\Http\Controllers\Api\RetiroController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\CuponesController;
use App\Http\Controllers\Api\TasaCambioController;
use App\Http\Controllers\Api\TripController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API para la app móvil (pasajeros y conductores)
|--------------------------------------------------------------------------
|
| Autenticación por token (Sanctum). Los intentos de registro, acceso y
| recuperación están limitados por IP para que no se puedan usar como
| generadores de correo.
|
| Contrato de respuesta:
|   éxito   -> { "message": "...", "data": { ... } }
|   error   -> 422 { "message": "...", "errors": { "campo": ["..."] } }
|
*/

Route::get('ciudades', [CityController::class, 'index'])
    ->middleware('throttle:60,1');

// La app muestra la selfie como avatar. La URL va firmada y atada al dueño:
// no se entrega ningún otro documento ni se permite adivinar el identificador.
Route::get('perfil/foto/{document}', PerfilFotoController::class)
    ->middleware('signed')
    ->name('api.perfil.foto');

Route::prefix('auth')->group(function () {
    Route::post('register', [RegistroController::class, 'store'])->middleware('throttle:10,1');
    Route::post('login', [SesionController::class, 'store'])->middleware('throttle:10,1');
    Route::post('forgot-password', [RecuperacionContrasenaController::class, 'store'])->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [SesionController::class, 'destroy']);

        Route::post('email/verification-notification', [VerificacionCorreoController::class, 'store'])
            ->middleware('throttle:6,1');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Estado de la cuenta y lista de verificación de documentos.
    Route::get('perfil', [PerfilController::class, 'show']);
    Route::match(['patch', 'post'], 'perfil', [PerfilController::class, 'update']);
    Route::post('perfil/avatar', [PerfilController::class, 'updateAvatar']);

    // Bandeja de avisos (documentos por vencer, decisiones de cuenta, etc.).
    Route::get('notificaciones', [NotificacionController::class, 'index']);
    Route::post('notificaciones/{notification}/leer', [NotificacionController::class, 'leer']);
    Route::post('notificaciones/leer-todas', [NotificacionController::class, 'leerTodas']);

    // Chat de soporte: una conversación abierta por persona. El auditor la
    // atiende y la finaliza desde el panel.
    Route::get('soporte', [SupportController::class, 'show']);
    Route::post('soporte/mensajes', [SupportController::class, 'store'])
        ->middleware('throttle:30,1');

    // Equipos que reciben notificaciones push (Firebase Cloud Messaging).
    Route::post('dispositivos', [DispositivoController::class, 'store']);
    Route::delete('dispositivos', [DispositivoController::class, 'destroy']);

    // Subida individual de documentos, con progreso uno por uno en la app.
    Route::post('documentos/{tipo}', [DocumentoController::class, 'store']);

    // Billetera: saldo, movimientos, recargas y retiros (fase F2).
    Route::get('billetera', [BilleteraController::class, 'show']);
    Route::get('billetera/movimientos', [BilleteraController::class, 'movimientos']);

    Route::get('recargas', [RecargaController::class, 'index']);
    Route::post('recargas', [RecargaController::class, 'store']);

    Route::get('retiros', [RetiroController::class, 'index']);
    Route::post('retiros', [RetiroController::class, 'store']);

    Route::get('tasa-cambio', [TasaCambioController::class, 'show']);
    Route::post('tasa-cambio/actualizar', [TasaCambioController::class, 'actualizar'])
        ->middleware('throttle:5,1');

    // Cupones de descuento (valida antes de pedir el viaje).
    Route::post('cupones/validar', [CuponesController::class, 'validar'])
        ->middleware('throttle:20,1');

    // Viajes (fase F3). La ruta "activo" va antes de "{trip}" para que no se
    // confunda con un identificador.
    Route::get('viajes/activo', [TripController::class, 'activo']);
    Route::get('viajes', [TripController::class, 'index']);
    Route::post('viajes/cotizar', [TripController::class, 'cotizar']);
    Route::post('viajes', [TripController::class, 'store']);
    Route::get('viajes/{trip}', [TripController::class, 'show']);
    Route::post('viajes/{trip}/cancelar', [TripController::class, 'cancelar']);
    Route::post('viajes/{trip}/llegada', [TripController::class, 'llegada']);
    Route::post('viajes/{trip}/esperando', [TripController::class, 'esperando']);
    Route::post('viajes/{trip}/iniciar', [TripController::class, 'iniciar']);
    Route::post('viajes/{trip}/finalizar', [TripController::class, 'finalizar']);
    Route::post('viajes/{trip}/pagar', [TripController::class, 'pagar']);

    // Calificación del pasajero y conductores favoritos.
    Route::post('viajes/{trip}/calificar', [RatingController::class, 'store'])
        ->middleware('throttle:20,1');
    Route::get('conductores-favoritos', [FavoriteController::class, 'index']);
    Route::post('conductores-favoritos/{driver}', [FavoriteController::class, 'store']);
    Route::delete('conductores-favoritos/{driver}', [FavoriteController::class, 'destroy']);

    // Disponibilidad, ubicación y alertas del conductor.
    Route::get('conductor/estado', [DriverController::class, 'estado']);
    Route::put('conductor/disponibilidad', [DriverController::class, 'disponibilidad']);
    Route::post('conductor/ubicacion', [DriverController::class, 'ubicacion']);
    Route::get('conductor/ofertas', [DriverController::class, 'ofertas']);
    Route::get('conductor/viaje-activo', [DriverController::class, 'viajeActivo']);
    Route::post('ofertas/{offer}/aceptar', [DriverController::class, 'aceptar']);
    Route::post('ofertas/{offer}/rechazar', [DriverController::class, 'rechazar']);
    Route::get('conductor/calificaciones', [RatingController::class, 'conductor']);

    // Geocodificación para el mapa (Geoapify o OpenStreetMap).
    Route::get('ubicaciones/buscar', [GeocodingController::class, 'buscar']);
    Route::get('ubicaciones/reversa', [GeocodingController::class, 'reversa']);
});
