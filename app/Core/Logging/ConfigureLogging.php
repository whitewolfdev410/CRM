<?php

namespace App\Core\Logging;

use Illuminate\Support\Str;
use Illuminate\Foundation\Bootstrap\ConfigureLogging as BaseConfigureLogging;
use Illuminate\Log\Writer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Request;

/**
 * Extends the standard log with the following options:
 *
 * - "loggly" - use loggly_and_single
 * - "loggly_and_single" - use loggly and single file (e.g. as a backup)
 * - "loggly_and_daily" - use loggly and daily files (e.g. as a backup)
 */
class ConfigureLogging extends BaseConfigureLogging
{
    /**
     * {@inheritdoc}
     *
     * Uses camel case methods naming.
     */
    protected function configureHandlers(Application $app, Writer $log)
    {
        $this->configureLogHandler($log);

        $method =
            'configure' . Str::studly($app['config']['app.log']) . 'Handler';

        $this->{$method}($app, $log);
    }

    /**
     * Configure log handler to add additional data
     *
     * @param Writer $log
     */
    protected function configureLogHandler(Writer $log)
    {
        $monolog = $log->getMonolog();

        // add extra data to Loggly
        $monolog->pushProcessor(function ($record) {
            $command = Request::server('argv', null);
            if (is_array($command)) {
                $command = implode(' ', $command);
            }

            $record['extra'] =
                [
                    'user' => [
                        'id' => getCurrentUserId(),
                        'person_id' => getCurrentPersonId(),
                        'ip' => Request::getClientIp(),
                    ],
                ];

            // if artisan command - include it in log
            if ($command !== null) {
                $record['extra']['command'] = $command;
            } else {
                // if via HTTP - add HTTP data
                $record['extra']['request'] = [
                    'full_url' => Request::fullUrl(),
                    'method' => Request::method(),
                    'input' => Request::all(),
                ];
            }

            return $record;
        });
    }

    /**
     * Use loggly handler.
     *
     * Configure the Monolog handlers for the application.
     *
     * @param  Application $app
     * @param  Writer $log
     * @return void
     */
    protected function configureLogglyHandler(Application $app, Writer $log)
    {
        $monolog = $log->getMonolog();

        $handler = $this->makeLogglyHandler($app);

        $monolog->pushHandler($handler);
    }

    /**
     * Use loggly and single file handler.
     *
     * Configure the Monolog handlers for the application.
     *
     * @param  Application $app
     * @param  Writer $log
     * @return void
     */
    protected function configureLogglyAndSingleHandler(
        Application $app,
        Writer $log
    ) {
        $this->configureSingleHandler($app, $log);
        $this->configureLogglyHandler($app, $log);
    }

    /**
     * Use loggly and daily files handler.
     *
     * Configure the Monolog handlers for the application.
     *
     * @param  Application $app
     * @param  Writer $log
     * @return void
     */
    protected function configureLogglyAndDailyHandler(
        Application $app,
        Writer $log
    ) {
        $this->configureDailyHandler($app, $log);
        $this->configureLogglyHandler($app, $log);
    }

    /**
     * Make loggly handler instance.
     *
     * @param  Application $app
     * @return LogglyHandler
     */
    private function makeLogglyHandler(Application $app)
    {
        $token = $app['config']['services.loggly.token'];
        $tag = $app['config']['services.loggly.tag'];

        $handler = new LogglyHandler($token);
        $handler->setTag($tag);

        $formatter = new LogglyFormatter();
        $handler->setFormatter($formatter);

        return $handler;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureDailyHandler(Application $app, Writer $log)
    {
        // let's set it to environment log folder + more default days
        $log->useDailyFiles(
            $app->storagePath() . '/logs/' . $app->environment() .
            '/laravel.log',
            $app->make('config')->get('app.log_max_files', 31)
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function configureSingleHandler(Application $app, Writer $log)
    {
        // let's set it to environment log folder + more default days

        $log->useFiles($app->storagePath() . '/logs/' . $app->environment() .
            '/laravel.log');
    }
}
