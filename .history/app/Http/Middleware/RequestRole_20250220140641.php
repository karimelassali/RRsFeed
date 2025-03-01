<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // List of allowed origins
        $allowedOrigins = [
            'https://'

        ]

        // Retrieve the Origin header from the request
        $origin = $request->headers->get('Origin');

        // Check if the Origin header is present and allowed
        if ($origin && !in_array($origin, $allowedOrigins)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
