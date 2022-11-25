<?php

namespace App\Core\Extended;

use Illuminate\Events\EventServiceProvider;
use Illuminate\Log\LogServiceProvider;
use Closure;

class ExtendedApplication extends \Illuminate\Foundation\Application
{

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    protected function registerBaseServiceProviders()
    {
        $this->register(new EventServiceProvider($this));

        $this->register(new LogServiceProvider($this));

        $this->register(new RoutingServiceProvider($this));
    }

    /**
     * Register a callback to run after loading the environment.
     *
     * @param  \Closure $callback
     *
     * @return void
     */
    public function afterLoadingEnvironment(Closure $callback)
    {
        return $this->afterBootstrapping(
            \App\Core\Extended\LoadEnvironmentVariables::class,
            $callback
        );
    }

    /**
     * Get the path to the configuration cache file.
     *
     * @return string
     */
    public function getCachedConfigPath()
    {
        return $this->bootstrapPath() . '/cache/'
            . $this->environment() . '/config.php';
    }

    /**
     * Get the path to the routes cache file.
     *
     * @return string
     */

    public function getCachedRoutesPath()
    {
        return $this->bootstrapPath() . '/cache/' . $this->environment() .
        '/routes.php';
    }
}
