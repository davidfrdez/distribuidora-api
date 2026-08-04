<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Flujo de inicio de sesión. Login simple de un solo negocio: correo,
 * contraseña y rol. No hay sedes, impersonación ni cambio de contexto.
 *
 * Funciona en modo dual:
 *   - SPA con sesión (SANCTUM_STATEFUL_DOMAINS): cookie httpOnly + sesión única.
 *   - Cliente sin sesión (Postman, scripts, n8n): Personal Access Token.
 */
class AuthLoginService
{
    /** Completa el login de un usuario ya autenticado y arma la respuesta. */
    public function completeLogin(User $user, ?string $ip = null): JsonResponse
    {
        if (! $user->active) {
            Log::warning('Login bloqueado — usuario inactivo.', ['email' => $user->email]);

            return response()->json([
                'error' => 'USER_INACTIVE',
                'message' => 'Tu usuario está desactivado. Contacta al administrador.',
            ], 403);
        }

        $user->forceFill(['lastLoginAt' => now()])->saveQuietly();

        Log::info('Login exitoso', ['email' => $user->email, 'id' => $user->id, 'ip' => $ip]);

        $payload = ['user' => $this->buildUserPayload($user)];
        $request = request();

        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            // Sesión única: cierra las demás sesiones del mismo usuario.
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        } else {
            $token = $user->createToken($this->tokenNameFor($request), ['*'], now()->addDays(30));
            $payload['token'] = $token->plainTextToken;
            $payload['token_type'] = 'Bearer';
            $payload['expires_at'] = $token->accessToken->expires_at?->toIso8601String();
        }

        return response()->json($payload);
    }

    /**
     * Shape del objeto `user` que consume el frontend. Compartido con GET /auth/user.
     * Incluye los datos del negocio porque la SPA los usa en el encabezado.
     *
     * @return array<string, mixed>
     */
    public function buildUserPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'roleLabel' => $user->role->label(),
            'company' => Company::current()->only([
                'name', 'businessName', 'nit', 'city', 'phone', 'currency',
                'brandColor', 'tagline', 'logoUrl', 'minOrderAmount',
            ]),
        ];
    }

    /** Nombre legible del token según el cliente, para poder revocarlo después. */
    private function tokenNameFor(Request $request): string
    {
        $agent = (string) $request->userAgent();

        $short = match (true) {
            str_contains($agent, 'PostmanRuntime') => 'Postman',
            str_contains($agent, 'insomnia') => 'Insomnia',
            str_contains($agent, 'axios') => 'axios',
            str_contains($agent, 'curl') => 'curl',
            str_contains($agent, 'okhttp') => 'Mobile (Android)',
            str_contains($agent, 'CFNetwork') => 'Mobile (iOS)',
            default => 'api-client',
        };

        return "{$short} @ {$request->ip()}";
    }
}
