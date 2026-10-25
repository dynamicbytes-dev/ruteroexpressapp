<?php

use App\Http\Controllers\Auth\NuevaContrasenaController;
use App\Http\Controllers\Auth\RecuperacionContrasenaController;
use App\Http\Controllers\Auth\VerificarCorreoController;
use App\Http\Controllers\Panel\AuditController;
use App\Http\Controllers\Panel\AuditorController;
use App\Http\Controllers\Panel\CuponesController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\DocumentController;
use App\Http\Controllers\Panel\DriverController;
use App\Http\Controllers\Panel\ExchangeRateController;
use App\Http\Controllers\Panel\NotificationController;
use App\Http\Controllers\Panel\RechargeController;
use App\Http\Controllers\Panel\ReportController;
use App\Http\Controllers\Panel\SettingsController;
use App\Http\Controllers\Panel\SupportController;
use App\Http\Controllers\Panel\TripController;
use App\Http\Controllers\Panel\TwoFactorController;
use App\Http\Controllers\Panel\UserController;
use App\Http\Controllers\Panel\VerificationController;
use App\Http\Controllers\Panel\WalletAdjustmentController;
use App\Http\Controllers\Panel\WithdrawalController;
use App\Http\Controllers\ProfileController;
use App\Support\PermisosPanel;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel web del auditor
|--------------------------------------------------------------------------
|
| Todo el panel vive bajo /panel. Los pasajeros y conductores no usan la web:
| ellos operan desde la app móvil contra /api.
|
*/

Route::redirect('/', '/panel');

/*
|--------------------------------------------------------------------------
| Páginas públicas de los correos
|--------------------------------------------------------------------------
|
| Los enlaces que salen por correo (verificación y recuperación) abren en el
| navegador, así que sirven igual para el auditor y para pasajeros y
| conductores: los de la app ven una página que les dice que vuelvan al
| teléfono.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/recuperar', [RecuperacionContrasenaController::class, 'create'])
        ->name('password.request');

    Route::post('/recuperar', [RecuperacionContrasenaController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('/restablecer/{token}', [NuevaContrasenaController::class, 'create'])
        ->name('password.reset');

    Route::post('/restablecer', [NuevaContrasenaController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');

    Route::view('/contrasena-lista', 'auth.password-reset-done')
        ->name('password.reset.done');
});

// El enlace de verificación puede abrirse con la sesión del panel ya iniciada,
// por eso esta ruta no lleva el middleware "guest".
Route::get('/verificar-correo/{id}/{hash}', VerificarCorreoController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::prefix('panel')->group(function () {
    // Sesión del panel (login, logout, contraseña, verificación del auditor).
    require __DIR__.'/auth.php';

    Route::middleware(['auth', 'role:auditor'])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        // Bandeja de avisos operativos del auditor (solo panel, sin correo).
        Route::get('/notificaciones', [NotificationController::class, 'index'])
            ->name('panel.notificaciones.index');
        Route::get('/notificaciones/contador', [NotificationController::class, 'unreadCount'])
            ->name('panel.notificaciones.contador');
        Route::post('/notificaciones/leer-todas', [NotificationController::class, 'readAll'])
            ->name('panel.notificaciones.leer-todas');
        Route::post('/notificaciones/{notification}/leer', [NotificationController::class, 'read'])
            ->name('panel.notificaciones.leer');

        // Chat de soporte: bandeja, hilo, respuestas y cierre.
        Route::get('/soporte', [SupportController::class, 'index'])->name('panel.soporte.index');
        Route::get('/soporte/{conversation}', [SupportController::class, 'show'])->name('panel.soporte.show');
        Route::get('/soporte/{conversation}/mensajes', [SupportController::class, 'mensajes'])
            ->name('panel.soporte.mensajes');
        Route::post('/soporte/{conversation}/mensajes', [SupportController::class, 'responder'])
            ->name('panel.soporte.responder');
        Route::post('/soporte/{conversation}/cerrar', [SupportController::class, 'cerrar'])
            ->name('panel.soporte.cerrar');

        // Verificación de usuarios: cola, ficha y decisiones.
        Route::get('/usuarios', [UserController::class, 'index'])->name('panel.usuarios.index');
        Route::get('/usuarios/{user}', [UserController::class, 'show'])->name('panel.usuarios.show');
        Route::patch('/usuarios/{user}', [UserController::class, 'update'])->name('panel.usuarios.update');

        // Auditores: el listado es visible para todos; solo el auditor principal
        // (clase A) puede registrar cuentas nuevas.
        Route::get('/auditores', [AuditorController::class, 'index'])->name('panel.auditores.index');

        Route::middleware('can:crear-auditores')->group(function () {
            Route::get('/auditores/rendimiento', [AuditorController::class, 'rendimiento'])
                ->name('panel.auditores.rendimiento');
            Route::get('/auditores/nuevo', [AuditorController::class, 'create'])->name('panel.auditores.create');
            Route::post('/auditores', [AuditorController::class, 'store'])->name('panel.auditores.store');
            Route::patch('/auditores/{auditor}', [AuditorController::class, 'update'])->name('panel.auditores.update');
        });

        Route::post('/usuarios/{user}/aprobar', [VerificationController::class, 'aprobarCuenta'])
            ->name('panel.usuarios.aprobar');
        Route::post('/usuarios/{user}/rechazar', [VerificationController::class, 'rechazarCuenta'])
            ->name('panel.usuarios.rechazar');
        Route::post('/usuarios/{user}/suspender', [VerificationController::class, 'suspenderCuenta'])
            ->name('panel.usuarios.suspender');
        Route::post('/usuarios/{user}/reactivar', [VerificationController::class, 'reactivarCuenta'])
            ->name('panel.usuarios.reactivar');
        Route::post('/usuarios/{user}/cedula', [VerificationController::class, 'corregirCedula'])
            ->name('panel.usuarios.cedula');

        Route::post('/usuarios/{user}/ajuste', [WalletAdjustmentController::class, 'store'])
            ->name('panel.usuarios.ajuste');

        Route::post('/documentos/{document}/aprobar', [VerificationController::class, 'aprobarDocumento'])
            ->name('panel.documentos.aprobar');
        Route::post('/documentos/{document}/rechazar', [VerificationController::class, 'rechazarDocumento'])
            ->name('panel.documentos.rechazar');

        // Los documentos se sirven con URL firmada y temporal: nunca son públicos.
        Route::get('/documentos/{document}', [DocumentController::class, 'show'])
            ->middleware('signed')
            ->name('panel.documents.show');

        // Recargas con comprobante (fase F2).
        Route::get('/recargas', [RechargeController::class, 'index'])->name('panel.recargas.index');
        Route::get('/recargas/{recharge}', [RechargeController::class, 'show'])->name('panel.recargas.show');
        Route::post('/recargas/{recharge}/aprobar', [RechargeController::class, 'approve'])
            ->name('panel.recargas.aprobar');
        Route::post('/recargas/{recharge}/rechazar', [RechargeController::class, 'reject'])
            ->name('panel.recargas.rechazar');
        Route::get('/recargas/{recharge}/comprobante', [RechargeController::class, 'proof'])
            ->middleware('signed')
            ->name('panel.recargas.comprobante');

        // Retiros de conductores (fase F2).
        Route::get('/retiros', [WithdrawalController::class, 'index'])->name('panel.retiros.index');
        Route::get('/retiros/{withdrawal}', [WithdrawalController::class, 'show'])->name('panel.retiros.show');
        Route::post('/retiros/{withdrawal}/pagar', [WithdrawalController::class, 'pay'])
            ->name('panel.retiros.pagar');
        Route::post('/retiros/{withdrawal}/rechazar', [WithdrawalController::class, 'reject'])
            ->name('panel.retiros.rechazar');
        Route::get('/retiros/{withdrawal}/comprobante', [WithdrawalController::class, 'proof'])
            ->middleware('signed')
            ->name('panel.retiros.comprobante');

        // Viajes (fase F3). Solo lectura para el auditor.
        Route::get('/viajes', [TripController::class, 'index'])->name('panel.viajes.index');
        Route::get('/viajes/{trip}', [TripController::class, 'show'])->name('panel.viajes.show');

        // Conductores (fase F5): disponibilidad, bloqueos, deuda y documentos.
        Route::middleware('can:'.PermisosPanel::CONDUCTORES)->group(function () {
            Route::get('/conductores', [DriverController::class, 'index'])->name('panel.conductores.index');
            Route::get('/conductores/{user}', [DriverController::class, 'show'])->name('panel.conductores.show');
            Route::post('/conductores/{user}/inhabilitar', [DriverController::class, 'inhabilitar'])
                ->name('panel.conductores.inhabilitar');
            Route::post('/conductores/{user}/habilitar', [DriverController::class, 'habilitar'])
                ->name('panel.conductores.habilitar');
            Route::post('/conductores/{user}/zona', [DriverController::class, 'cambiarZona'])
                ->name('panel.conductores.zona');
        });

        // Reportes (fase F5): métricas del período y exportables.
        Route::middleware('can:'.PermisosPanel::REPORTES)->group(function () {
            Route::get('/reportes', [ReportController::class, 'index'])->name('panel.reportes.index');
            Route::get('/reportes/viajes.csv', [ReportController::class, 'exportTrips'])->name('panel.reportes.viajes.csv');
            Route::get('/reportes/movimientos.csv', [ReportController::class, 'exportMovements'])->name('panel.reportes.movimientos.csv');
        });

        // Auditoría (fase F5): log de actividad y exportable CSV.
        Route::middleware('can:'.PermisosPanel::AUDITORIA)->group(function () {
            Route::get('/auditoria', [AuditController::class, 'index'])->name('panel.auditoria.index');
            Route::get('/auditoria/exportar.csv', [AuditController::class, 'export'])->name('panel.auditoria.export');
        });

        // Tasa de cambio manual y configuración operativa (fase F2).
        Route::get('/tasa', [ExchangeRateController::class, 'index'])->name('panel.tasa.index');
        Route::post('/tasa', [ExchangeRateController::class, 'store'])->name('panel.tasa.store');

        // Cupones de descuento (creados por el auditor).
        Route::get('/cupones', [CuponesController::class, 'index'])->name('panel.cupones.index');
        Route::get('/cupones/nuevo', [CuponesController::class, 'create'])->name('panel.cupones.create');
        Route::post('/cupones', [CuponesController::class, 'store'])->name('panel.cupones.store');
        Route::get('/cupones/{coupon}', [CuponesController::class, 'show'])->name('panel.cupones.show');
        Route::get('/cupones/{coupon}/editar', [CuponesController::class, 'edit'])->name('panel.cupones.edit');
        Route::put('/cupones/{coupon}', [CuponesController::class, 'update'])->name('panel.cupones.update');
        Route::delete('/cupones/{coupon}', [CuponesController::class, 'destroy'])->name('panel.cupones.destroy');

        Route::get('/configuracion', [SettingsController::class, 'edit'])->name('panel.configuracion.edit');
        Route::put('/configuracion', [SettingsController::class, 'update'])->name('panel.configuracion.update');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Doble factor del auditor.
        Route::get('/2fa', [TwoFactorController::class, 'show'])->name('panel.2fa.show');
        Route::post('/2fa', [TwoFactorController::class, 'store'])->name('panel.2fa.store');
        Route::post('/2fa/confirmar', [TwoFactorController::class, 'confirm'])->name('panel.2fa.confirm');
        Route::delete('/2fa', [TwoFactorController::class, 'destroy'])->name('panel.2fa.destroy');
        Route::post('/2fa/codigos', [TwoFactorController::class, 'recoveryCodes'])->name('panel.2fa.recovery-codes');
    });
});
