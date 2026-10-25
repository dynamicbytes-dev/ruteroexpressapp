<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Segundo paso del inicio de sesión del panel.
 *
 * La contraseña ya se validó; aquí se pide el código de la aplicación de
 * autenticación (o uno de los códigos de recuperación). Mientras no se valide,
 * la sesión web no queda autenticada.
 */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $usuario = $this->usuarioPendiente($request);

        if ($usuario === null) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge', [
            'email' => $usuario->email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $usuario = $this->usuarioPendiente($request);

        if ($usuario === null) {
            return redirect()->route('login');
        }

        $datos = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ], messages: [
            'code.required' => 'Escribe el código de tu aplicación de autenticación.',
        ]);

        if (! $usuario->verifyTwoFactorCode($datos['code'])) {
            throw ValidationException::withMessages([
                'code' => 'El código no es válido o ya venció. Inténtalo de nuevo.',
            ]);
        }

        $recordar = (bool) $request->session()->pull('2fa.remember', false);
        $destino = (string) $request->session()->pull('2fa.intended', route('dashboard', absolute: false));

        $request->session()->forget(['2fa.user_id', '2fa.remember', '2fa.intended']);

        Auth::login($usuario, $recordar);

        $request->session()->regenerate();

        return redirect()->to($destino ?: route('dashboard', absolute: false));
    }

    /** Usuario que ya pasó la contraseña y está pendiente del segundo factor. */
    private function usuarioPendiente(Request $request): ?User
    {
        $id = $request->session()->get('2fa.user_id');

        if (! is_int($id) && ! ctype_digit((string) $id)) {
            return null;
        }

        $usuario = User::query()->find((int) $id);

        if ($usuario === null || ! $usuario->hasEnabledTwoFactor()) {
            $request->session()->forget(['2fa.user_id', '2fa.remember', '2fa.intended']);

            return null;
        }

        return $usuario;
    }
}
