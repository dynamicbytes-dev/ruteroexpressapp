<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sesión del panel web del auditor
|--------------------------------------------------------------------------
|
| Se monta bajo /panel. No existe registro público: las cuentas de auditor las
| crea un administrador por consola (php artisan motoruta:crear-auditor).
|
| La recuperación de contraseña NO vive aquí: el enlace del correo abre una
| página pública (ver routes/web.php) porque el mismo enlace tiene que servirle
| a una persona que usa la app móvil.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1');

    // Segundo paso del inicio de sesión (código TOTP o de recuperación).
    Route::get('2fa/desafio', [TwoFactorChallengeController::class, 'create'])
        ->name('panel.2fa.challenge');

    Route::post('2fa/desafio', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('panel.2fa.verify');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
