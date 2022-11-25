<?php

namespace App\Core\Extended;

class RoutingServiceProvider extends \Illuminate\Routing\RoutingServiceProvider
{
    /**
     * {@inheritdoc}
     */
    protected function registerResponseFactory()
    {
        $this->app->singleton(
            'Illuminate\Contracts\Routing\ResponseFactory',
            function ($app) {
                return new ResponseFactory(
                    $app['Illuminate\Contracts\View\Factory'],
                    $app['redirect']
                );
            }
        );
    }
}
