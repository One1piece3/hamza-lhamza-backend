<?php

namespace App\Http\Middleware;

use App\Support\AdminTokenStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Authentification admin requise'], 401);
        }

        $userId = AdminTokenStore::get($token);

        if (!$userId) {
            return response()->json(['message' => 'Session admin invalide ou expirée'], 401);
        }

        $request->attributes->set('admin_token', $token);
        $request->attributes->set('admin_user_id', $userId);

        return $next($request);
    }

    public static function cacheKey(string $token): string
    {
        return AdminTokenStore::cacheKey($token);
    }
}
