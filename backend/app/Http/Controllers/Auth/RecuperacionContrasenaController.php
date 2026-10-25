<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Solicitud del enlace de recuperación de contraseña.
 *
 * Es la misma pantalla para el auditor (panel web) y para pasajeros y
 * conductores (app móvil): el enlace del correo abre una página web, que
 * funciona igual desde el navegador y desde el teléfono.
 */
class RecuperacionContrasenaController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ], attributes: ['email' => 'correo']);

        $estado = Password::sendResetLink(
            ['email' => mb_strtolower((string) $request->input('email'))]
        );

        // Nunca se confirma si el correo existe o no: eso permitiría averiguar
        // quién está registrado en GAZ.
        if ($estado === Password::RESET_THROTTLED) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Ya pediste un enlace hace poco. Espera un minuto antes de volver a intentarlo.']);
        }

        return back()->with('status', 'Si ese correo tiene una cuenta en GAZ, te enviamos un enlace para crear una contraseña nueva.');
    }
}
