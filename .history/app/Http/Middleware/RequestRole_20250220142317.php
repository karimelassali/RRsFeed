<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Response;

class RequestRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get allowed origins from environment configuration
        $allowedOrigins = Config::get('cors.allowed_origins', [
            'http://localhost:3000',
            // Add your production URLs here
        ]);

        $origin = $request->headers->get('Origin');

        // Check if it's a preflight request
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($request, $allowedOrigins);
        }

        // Verify origin and API token
        if (!$this->isValidOrigin($origin, $allowedOrigins)) {
            return response()->json(['error' => 'Unauthorized origin'], 401);
        }

        // Verify API token
        if (!$this->isValidToken($request)) {
            return response()->json(['error' => 'Invalid or missing API token'], 401);
        }

        $response = $next($request);

        // Add CORS headers to response
        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * Handle preflight requests
     */
    private function handlePreflightRequest(Request $request, array $allowedOrigins): Response
    {
        $origin = $request->headers->get('Origin');

        if (!$this->isValidOrigin($origin, $allowedOrigins)) {
            return response()->json(['error' => 'Unauthorized origin'], 401);
        }

        return response()->json(['message' => 'Preflight OK'], 200)
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->header('Access-Control-Allow-Credentials', 'true')
            ->header('Access-Control-Max-Age', '86400');
    }

    /**
     * Validate the origin
     */
    private function isValidOrigin(?string $origin, array $allowedOrigins): bool
    {
        if (empty($origin)) {
            return false;
        }

        return in_array($origin, $allowedOrigins) ||
               in_array('*', $allowedOrigins);
    }

    /**
     * Validate the API token
     */
    private function isValidToken(Request $request): bool
    {
        $token = $request->bearerToken();

        if (!$token) {
            return false;
        }

        // Add your token validation logic here
        // Example: verify JWT token or check against database
        return true; // Replace with actual validation
    }

    /**
     * Add CORS headers to response
     */
    private function addCorsHeaders(Response $response, string $origin): Response
    {
        return $response
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->header('Access-Control-Allow-Credentials', 'true');
    }
}
