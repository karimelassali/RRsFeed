<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Exception;
use Illuminate\Support\Facades\Log;

class ClerkAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

/*************  ✨ Codeium Command 🌟  *************/
        try {
            $jwks = json_decode(file_get_contents('https://charmed-mule-17.clerk.accounts.dev/.well-known/jwks.json'), true);
            $publicKeys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($token, $publicKeys);
            $request->merge(['user_id' => $decoded->sub]);
            Log::info('User ID: ' . $decoded->sub);
        } catch (Exception $e) {
/******  491d7d55-055c-4493-a410-26d2bd73e0b4  *******/
            return response()->json(['error' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}
