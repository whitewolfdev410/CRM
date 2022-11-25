<?php

namespace App\Providers;

use App\Core\Crm;
use App\Core\Trans;
use App\Modules\Type\Models\Type;
use App\Modules\Type\Repositories\TypeMemcachedRepository;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use App\Core\Logging\LogglyHandler;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        ini_set('xdebug.max_nesting_level', 200);

        // set modules namespace that will be used in module routes.php
        if (!defined('MODULES_NS')) {
            define('MODULES_NS', '\App\Modules\\');
        }

        Blade::withoutDoubleEncoding();
        Paginator::useBootstrap();
    }

    /**
     * Register any application services.
     *
     * This service provider is a great spot to register your various container
     * bindings with the application. As you can see, we are registering our
     * "Registrar" implementation here. You can add your own bindings too!
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->isLocal()) {
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }
        
        $this->app->bind(
            'Illuminate\Contracts\Auth\Registrar',
            \App\Services\Registrar::class
        );

        // binding Type as singleton - it is used in many places

        $this->app->singleton(
            \App\Modules\Type\Repositories\TypeRepository::class,
            function () {
                //return new TypeRepository($this->app, new Type());
                return new TypeMemcachedRepository($this->app, new Type());
            }
        );

        $this->app->singleton(
            \App\Core\DB\EnumColumn::class,
            \App\Core\DB\EnumColumn::class
        );

        // Contracts implementation - use memcached version or not
        $this->app->bind(
            \App\Modules\WorkOrder\Services\WorkOrderBoxCounterServiceContract::class,
            \App\Modules\WorkOrder\Services\WorkOrderMemcachedBoxCounterService::class
        );

        $this->app->bind(
            \App\Modules\WorkOrder\Services\WorkOrderDataServiceContract::class,
            \App\Modules\WorkOrder\Services\WorkOrderMemcachedDataService::class
        );

        $this->app->bind('logger', \App\Core\Logger::class);

        $this->app->singleton(Trans::class, Trans::class);

        $this->app->singleton(Crm::class);
        $this->app->alias(Crm::class, 'crm');

        // initialize loggly handler with configured data
        $this->app->bind(LogglyHandler::class, function () {
            $token = $this->app->config['services.loggly.token'];
            $tag = $this->app->config['services.loggly.tag'];

            $handler = new LogglyHandler($token);
            $handler->setTag($tag);

            return $handler;
        });
    }
}
