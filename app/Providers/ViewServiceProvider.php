<?php

namespace App\Providers;

use Illuminate\View\FileViewFinder;
use Illuminate\View\ViewServiceProvider as ServiceProvider;

/**
 * View service provider class
 * Extends default view provider class
 */
class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register the view finder implementation.
     *
     * @override
     * @return void
     */
    public function registerViewFinder()
    {
        $this->app->bind('view.finder', function ($app) {
            $paths = array_merge(
                [
                    realpath(base_path('resources/views/custom/' .
                        $app['config']['app.crm_user'])),
                ],
                $app['config']['view.paths']
            );

            return new FileViewFinder($app['files'], $paths);
        });
    }
}
