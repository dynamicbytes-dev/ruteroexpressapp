<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $usuario = $request->user();

        // Si la cuenta tiene doble factor, la contraseña sola no abre sesión:
        // se guarda el usuario pendiente y se pide el código en otro paso.
        if ($usuario->hasEnabledTwoFactor()) {
            $destino = $request->session()->get('url.intended', route('dashboard', absolute: false));

            Auth::guard('web')->logout();

            $request->session()->regenerate();
            $request->session()->put([
                '2fa.user_id' => $usuario->getKey(),
                '2fa.remember' => $request->boolean('remember'),
                '2fa.intended' => $destino,
            ]);

            return redirect()->route('panel.2fa.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
