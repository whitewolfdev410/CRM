<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Cors middleware
 */
class Cors
{
    /**
     * Handle method
     * @param Request $request
     * @param Closure $next
     */
    public function handle($request, Closure $next)
    {
        return $next($request)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, User-Info, Device-Token, Device-Type, X-Cors')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }
}
