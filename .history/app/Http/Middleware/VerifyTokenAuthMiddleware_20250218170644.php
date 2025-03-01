<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;

class ClerkAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        try {
            $publicKey = file_get_contents("https://clerk.your-clerk-domain.com/v1/.well-known/jwks.json");
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            // يمكنك التحقق من `decoded` لمعرفة بيانات المستخدم
            $request->attributes->set('user', $decoded);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => composer require firebase/php-jwt'Invalid token'], 401);
        }

        return $next($request);
    }
}
