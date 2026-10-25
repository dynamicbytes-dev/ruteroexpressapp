<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Enlace del correo de verificación.
 *
 * La misma URL sirve para el auditor (que continúa al panel) y para pasajeros y
 * conductores, que ven una página de confirmación y vuelven a la app. El enlace
 * va firmado y caduca, así que nadie puede verificar el correo de otra persona.
 */
class VerificarCorreoController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): View|RedirectResponse
    {
        $usuario = User::find($id);

        // Enlace alterado o de una cuenta que ya no existe.
        if ($usuario === null || ! hash_equals($hash, sha1($usuario->getEmailForVerification()))) {
            return view('auth.correo-verificado', ['verificado' => false]);
        }

        if (! $usuario->hasVerifiedEmail()) {
            $usuario->markEmailAsVerified();

            event(new Verified($usuario));
        }

        // El auditor sigue al panel; quien usa la app vuelve al teléfono.
        if ($usuario->isAuditor()) {
            return redirect(route('dashboard', absolute: false).'?verified=1');
        }

        return view('auth.correo-verificado', [
            'verificado' => true,
            'usuario' => $usuario,
        ]);
    }
}
