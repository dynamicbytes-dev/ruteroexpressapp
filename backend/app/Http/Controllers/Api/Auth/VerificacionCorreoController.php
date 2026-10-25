<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reenvía el correo de verificación.
 *
 * Es el mismo correo del registro; la app lo ofrece mientras la persona no haya
 * confirmado su dirección. La ruta lleva límite de intentos para que no se use
 * como generador de spam.
 */
class VerificacionCorreoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Tu correo ya estaba verificado.',
            ]);
        }

        $usuario->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Te reenviamos el correo de verificación a '.$usuario->email.'.',
        ]);
    }
}
