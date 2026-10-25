<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Activación y administración del doble factor del auditor.
 *
 * El secreto queda en la cuenta cifrado por el cast del modelo. Hasta que el
 * auditor escribe un código válido no se considera activo, así nadie queda
 * fuera por generar un QR y cerrar la página.
 */
class TwoFactorController extends Controller
{
    public function show(Request $request): View
    {
        $usuario = $request->user();

        return view('panel.seguridad.two-factor', [
            'activo' => $usuario->hasEnabledTwoFactor(),
            'pendiente' => $usuario->two_factor_secret !== null && ! $usuario->hasEnabledTwoFactor(),
            'qr' => $usuario->hasEnabledTwoFactor() ? null : $usuario->twoFactorQrCodeInline(),
            'secreto' => $usuario->hasEnabledTwoFactor() ? null : $usuario->two_factor_secret,
            'codigos' => $request->session()->pull('recovery_codes', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasEnabledTwoFactor()) {
            throw ValidationException::withMessages([
                'two_factor' => 'El doble factor ya está activo. Desactívalo antes de generar otro secreto.',
            ]);
        }

        $request->user()->generateTwoFactorSecret();

        return redirect()
            ->route('panel.2fa.show')
            ->with('status', 'Escanea el código con tu aplicación de autenticación y confirma con un código.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ], messages: [
            'code.required' => 'Escribe el código que muestra tu aplicación de autenticación.',
        ]);

        $usuario = $request->user();

        if (! $usuario->two_factor_secret || $usuario->hasEnabledTwoFactor()) {
            return redirect()->route('panel.2fa.show');
        }

        if (! $usuario->verifyTwoFactorCode($request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'El código no es válido. Revisa la hora del teléfono e inténtalo otra vez.',
            ]);
        }

        $codigos = $usuario->confirmTwoFactor();

        return redirect()
            ->route('panel.2fa.show')
            ->with('status', 'Doble factor activado. Guarda tus códigos de recuperación.')
            ->with('recovery_codes', $codigos);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:20'],
        ], messages: [
            'password.required' => 'Escribe tu contraseña para desactivar el doble factor.',
            'password.current_password' => 'La contraseña no coincide.',
            'code.required' => 'Escribe un código de tu aplicación o uno de recuperación.',
        ]);

        $usuario = $request->user();

        if (! $usuario->verifyTwoFactorCode($request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'El código no es válido.',
            ]);
        }

        $usuario->disableTwoFactor();

        return redirect()
            ->route('panel.2fa.show')
            ->with('status', 'Doble factor desactivado. Tu cuenta vuelve a usar solo la contraseña.');
    }

    public function recoveryCodes(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ], messages: [
            'password.current_password' => 'La contraseña no coincide.',
        ]);

        $usuario = $request->user();

        if (! $usuario->hasEnabledTwoFactor()) {
            return redirect()->route('panel.2fa.show');
        }

        $codigos = $usuario->generateRecoveryCodes();

        return redirect()
            ->route('panel.2fa.show')
            ->with('status', 'Códigos de recuperación nuevos. Los anteriores ya no sirven.')
            ->with('recovery_codes', $codigos);
    }
}
