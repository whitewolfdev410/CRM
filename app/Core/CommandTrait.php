<?php

namespace App\Core;

use Illuminate\Console\Command;

/**
 * Class CommandTrait - used to display data on command line
 *
 * @package App\Core
 */
trait CommandTrait
{
    /**
     * @var Command|null
     */
    protected $terminal = null;

    /**
     * Set terminal to display formatted messages
     *
     * @param Command $terminal
     */
    public function setTerminal(Command $terminal)
    {
        $this->terminal = $terminal;
    }

    /**
     * Log message (only if terminal has been set)
     *
     * @param string $message Type of log
     * @param string $type
     */
    protected function log($message, $type = 'line')
    {
        if ($this->terminal instanceof Command) {
            $this->terminal->{$type}($message);
        }
    }
}
