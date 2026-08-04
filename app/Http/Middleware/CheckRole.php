<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a una lista de roles: `role:SOCIO,ADMINISTRADOR`.
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'No has iniciado sesión o tu sesión expiró.',
            ], 401);
        }

        // $user->role es un UserRole (cast); las rutas declaran los roles como string.
        if (! in_array($user->role->value, $roles, true)) {
            return response()->json([
                'error' => 'FORBIDDEN',
                'message' => 'No tienes permiso para realizar esta acción.',
            ], 403);
        }

        return $next($request);
    }
}
