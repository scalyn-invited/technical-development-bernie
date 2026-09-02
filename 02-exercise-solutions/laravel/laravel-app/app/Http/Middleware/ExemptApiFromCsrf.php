<?php

namespace App\Http\Middleware;

use Closure;

class ExemptApiFromCsrf
{
    public function handle($request, Closure $next)
    {
        if ($request->is('api/*')) {
            return $next($request);
        }
        return $next($request);
    }
}