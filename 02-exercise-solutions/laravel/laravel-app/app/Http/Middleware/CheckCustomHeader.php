<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCustomHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip API routes
        if ($request->is('api/*')) {
            return $next($request);
        }

        if (!$request->hasHeader('X-Custom-Header')) {
            return response()->json([
                'error' => 'Missing required header: X-Custom-Header'
            ], 400);
        }

        return $next($request);
    }
}
