<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Identity\Actions\RegistrarUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Registro de pasajeros y conductores desde la app.
 *
 * Devuelve el token de acceso de una vez: la persona puede entrar a la app,
 * ver el estado "en espera de verificación" y subir los documentos que falten
 * sin tener que volver a autenticarse.
 */
class RegistroController extends Controller
{
    public function __construct(private readonly RegistrarUsuario $registrarUsuario) {}

    public function store(RegisterRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $documentos = collect($request->file('documentos') ?? [])
            ->filter(fn ($archivo): bool => $archivo instanceof UploadedFile)
            ->all();

        $fotoDirecta = $request->file('avatar') ?? $request->file('photo');
        if ($fotoDirecta instanceof UploadedFile && ! isset($documentos['selfie'])) {
            $documentos['selfie'] = $fotoDirecta;
        }

        $usuario = $this->registrarUsuario->ejecutar(
            datos: $datos,
            documentos: $documentos,
            vencimientos: $request->input('documentos_expiracion', []),
        );

        $token = $usuario->createToken($request->input('device_name', 'app'))->plainTextToken;

        return response()->json([
            'message' => 'Tu cuenta fue creada. Está en espera de verificación: te avisaremos cuando el equipo apruebe tus documentos.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => UserResource::make($usuario->load(
                    'documents',
                    'driverProfile',
                    'city',
                    'driverProfile.city',
                )),
            ],
        ], 201);
    }
}
