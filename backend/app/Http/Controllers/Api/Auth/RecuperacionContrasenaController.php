<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Recuperación de contraseña pedida desde la app.
 *
 * No devuelve el enlace: envía un correo cuya URL abre una página web. El
 * teléfono no tiene que saber manejar enlaces profundos y funciona igual si la
 * persona abre el correo en la computadora.
 *
 * La respuesta es siempre la misma, exista o no la cuenta, para no revelar
 * qué correos están registrados.
 */
class RecuperacionContrasenaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email'],
        ], attributes: ['email' => 'correo']);

        $estado = Password::sendResetLink(['email' => mb_strtolower($datos['email'])]);

        if ($estado === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Ya pediste un enlace hace poco. Espera un minuto antes de volver a intentarlo.',
            ], 429);
        }

        return response()->json([
            'message' => 'Si ese correo tiene una cuenta en GAZ, te enviamos un enlace para crear una contraseña nueva. Revisa tu bandeja de entrada.',
        ]);
    }
}
