<?php

namespace App\Core\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\LogglyHandler as BaseHandler;
use Exception;

class LogglyHandler extends BaseHandler
{
    /**
     * {@inheritdoc}
     *
     * Ignore exceptions thrown during sending to loggly
     */
    protected function send(string $data, string $endpoint): void
    {
        try {
            parent::send($data, $endpoint);
        } catch (Exception $e) {
        }
    }
    
    /**
     * {@inheritdoc}
     *
     * Use custom formatter
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LogglyFormatter();
    }
}
