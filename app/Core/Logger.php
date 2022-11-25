<?php

namespace App\Core;

use Illuminate\Support\Str;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Container\Container;

class Logger
{
    /**
     * @var Guard
     */
    protected $auth;

    /**
     * @var Container
     */
    protected $app;

    /**
     * Initialize class properties
     *
     * @param Guard $auth
     * @param Container $app
     */
    public function __construct(Guard $auth, Container $app)
    {
        $this->auth = $auth;
        $this->app = $app;
    }

    /**
     * Log message to file.
     *
     * @param string $message
     * @param string $type
     */
    public function log($message, $type = 'general_log')
    {
        $logFile = $this->app->config->get('app_logs.log_path');
        if (!Str::endsWith($logFile, DIRECTORY_SEPARATOR)) {
            $logFile .= DIRECTORY_SEPARATOR;
        }

        $logFile .= $this->app->config->get('app_logs.' . $type, 'general.log');

        $user = $this->auth->check() ? $this->auth->user()->getPersonId()
            : 'SYS';

        $content = date('Y-m-d H:i:s') . ' ' . $user . ' ' . $message . "\n";
        file_put_contents($logFile, $content, FILE_APPEND);
    }
}
