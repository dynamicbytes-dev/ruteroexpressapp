<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as ReglasPassword;
use Illuminate\View\View;

/**
 * Pantalla donde se define la contraseña nueva a partir del enlace del correo.
 *
 * Al cambiarla se cierran todas las sesiones abiertas en la app: si alguien
 * tenía acceso, lo pierde en ese momento.
 */
class NuevaContrasenaController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', ReglasPassword::min(8)->letters()->numbers()],
        ], attributes: [
            'email' => 'correo',
            'password' => 'contraseña',
        ]);

        $usuario = null;

        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, &$usuario): void {
                $user->forceFill([
                    'password' => Hash::make((string) $request->input('password')),
                    'remember_token' => Str::random(60),
                ])->save();

                // Cambiar la contraseña invalida los accesos que ya estaban abiertos.
                $user->tokens()->delete();

                $usuario = $user;

                event(new PasswordReset($user));
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'El enlace venció o ya se usó. Pide uno nuevo para continuar.']);
        }

        // El auditor termina en el panel; la persona que usa la app vuelve al
        // teléfono con un mensaje claro.
        if ($usuario?->isAuditor()) {
            return redirect()
                ->route('login')
                ->with('status', 'Tu contraseña fue actualizada. Ya puedes entrar al panel.');
        }

        return redirect()->route('password.reset.done');
    }
}
