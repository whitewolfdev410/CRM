<?php

namespace App\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class CorsServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot(Request $request, Kernel $kernel)
    {
        $this->app['router']->aliasMiddleware('cors', \App\Http\Middleware\Cors::class);

        if ($request->isMethod('OPTIONS')) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, User-Info, Device-Token, Device-Type, X-Cors');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            die('OK');
        }
    }
}
