<?php

namespace App\Http\Middleware;

use Closure;

class Api
{
    protected $except = [
        'mobile/*',
        'mobile-auth/*'
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        foreach ($this->except as $except) {
            if ($request->is($except)) {
                config()->set('session.driver', 'array');
            }
        }
        return $next($request);
    }
}
