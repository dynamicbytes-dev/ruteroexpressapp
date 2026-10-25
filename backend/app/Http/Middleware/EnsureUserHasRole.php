<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a los roles indicados.
 *
 *   Route::middleware('role:auditor')
 *   Route::middleware('role:driver,passenger')
 *
 * El rol vive en la columna users.role y es la única fuente de verdad.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No autenticado.'], 401)
                : redirect()->guest(route('login'));
        }

        if (! in_array($user->role->value, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No tienes permiso para esta acción.'], 403);
            }

            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
