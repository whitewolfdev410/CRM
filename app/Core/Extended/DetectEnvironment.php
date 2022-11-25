<?php

namespace App\Core\Extended;

use App\Core\Trans;
use Dotenv;
use Illuminate\Contracts\Foundation\Application;

class DetectEnvironment
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application $app
     *
     * @return void
     */
    public function bootstrap(Application $app)
    {
        // no APP_ENV - use HTTP_HOST to determine
        if (getenv('APP_ENV') === false) {
            $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            $domain = str_replace(':', '', $domain);

            if ($domain == '') {
                $domain = 'testing';
            } else {
                if (!file_exists($app['path.base'] . '/config/' . $domain)) {
                    die('This domain is not configured: ' . $domain);
                }
            }
            Dotenv::setEnvironmentVariable('APP_ENV', $domain);
        }

        // make Application to really detect environment
        $app->detectEnvironment(function () {
            return getenv('APP_ENV') ?: 'production';
        });

        /* here real environment might be different (console arguments) se we
           need to set APP_ENV to exact same value that Application set */
        Dotenv::makeMutable();
        Dotenv::setEnvironmentVariable('APP_ENV', $app->environment());
        Dotenv::makeImmutable();
    }
}
