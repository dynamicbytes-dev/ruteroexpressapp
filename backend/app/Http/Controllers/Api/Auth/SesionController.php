<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Trips\DriverService;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Sesión de la app móvil: entrar y salir.
 *
 * Un usuario en espera de verificación sí puede entrar: la app le muestra su
 * estado. Lo que no puede es operar (pedir viajes, activarse, recargar); eso lo
 * controla `canOperate()` en cada acción de negocio.
 */
class SesionController extends Controller
{
    public function __construct(private readonly DriverService $conductores) {}

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:80'],
            'role' => ['sometimes', 'string'],
        ]);

        $email = mb_strtolower(trim($datos['email']));
        $query = User::query()->where('email', $email);

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $usuarios = $query->get();

        $usuario = $usuarios->first(fn (User $u) => Hash::check($datos['password'], $u->password))
            ?? $usuarios->first();

        if ($usuario === null || ! Hash::check($datos['password'], $usuario->password)) {
            throw ValidationException::withMessages([
                'email' => ['El correo o la contraseña no son correctos.'],
            ]);
        }

        // El auditor entra por el panel web, no por la app.
        if ($usuario->isAuditor()) {
            throw ValidationException::withMessages([
                'email' => ['Esta cuenta es de auditoría. Entra desde el panel web.'],
            ]);
        }

        return response()->json([
            'message' => 'Bienvenido de vuelta.',
            'data' => [
                'token' => $usuario->createToken($datos['device_name'] ?? 'app')->plainTextToken,
                'token_type' => 'Bearer',
                'user' => UserResource::make($usuario->load(
                    'documents',
                    'driverProfile',
                    'city',
                    'driverProfile.city',
                )),
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $usuario = $request->user();

        // Un conductor no puede quedar "en línea" después de cerrar sesión:
        // dejaría de recibir alertas reales y el panel lo mostraría activo.
        if ($usuario->isDriver()) {
            try {
                $this->conductores->actualizarDisponibilidad($usuario, false);
            } catch (RuntimeException) {
                // Sin perfil de conductor no hay nada que apagar.
            }
        }

        // Solo se cierra la sesión del dispositivo actual, no las demás.
        $usuario->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
