<?php

namespace App\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use RuntimeException;

/**
 * Base class for commands that can be run in one instance only
 */
abstract class SingleInstanceCommand extends Command
{
    // default process timeout of 12 hours
    // it can be changed by adding --process-timeout command option (seconds)
    protected $defaultProcessTimeout = 43200;

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->checkRunning();
    }

    /**
     * Get process timeout
     * @return void
     */
    private function getProcessTimeout()
    {
        if ($this->hasOption('process-timeout')) {
            $optionValue = $this->option('process-timeout');

            if (is_numeric($optionValue)) {
                return (int) $optionValue;
            }
        }

        return $this->defaultProcessTimeout;
    }

    /**
     * Check currently running processes and throw exception if another instance of command is running
     *
     * @return void
     */
    protected function checkRunning()
    {
        // skip if not running in command line (e.g. called by a controller)
        if (empty($_SERVER['argv'])) {
            return;
        }

        if ($this->shouldAllowMulti()) {
            return;
        }

        $processTimeout = $this->getProcessTimeout();

        // find other running processes of this command
        $processes = $this->findRunningProcesses();

        // clean up processes exceeding the timeout
        foreach ($processes as $pid => $etime) {
            if ($processTimeout && $etime > $processTimeout) {
                $this->killProcess($pid);

                $this->warn("Killed process running for too long ({$etime} seconds)");

                // remove process from the list after it's killed
                unset($processes[$pid]);
            }
        }

        // check if another process is still running
        if (count($processes) > 0) {
            $command = $this->getRunningCommandString();

            $this->alreadyRunning($command);
        }

        return true;
    }

    /**
     * Find running processes of this command
     * @return array
     */
    private function findRunningProcesses()
    {
        $pattern = $this->getRunningCommandPattern();

        // find matching process IDs
        exec("pgrep -f '{$pattern}'", $pids);

        if (!$pids) {
            return [];
        }

        // get processes data
        exec('ps -o pid= -o etimes= -p ' . implode(' ', $pids), $output);

        $processes = [];

        $currentPid = getmypid();

        foreach ($output as $line) {
            [$pid, $etime] = preg_split('/\s+/', trim($line), 2);

            if ($pid != $currentPid) {
                $processes[$pid] = $etime;
            }
        }

        return $processes;
    }

    /**
     * Kill running process
     * @param  int $pid
     * @return void
     */
    private function killProcess($pid)
    {
        @exec("kill -9 {$pid}");
    }

    /**
     * Whether command called with --allow-multi option
     * which means that multiple instances should be allowed
     * @return bool
     */
    private function shouldAllowMulti()
    {
        return in_array('--allow-multi', $_SERVER['argv']);
    }

    /**
     * Get running command pattern to be used for pgrep
     * @return string
     */
    protected function getRunningCommandPattern()
    {
        $artisanPattern = '^\S*\s?\.?\/?artisan\s+';

        $argv = $_SERVER['argv'];
        // remove 'artisan' element from argv
        array_shift($argv);

        return $artisanPattern . implode('\s+', array_map('preg_quote', $argv));
    }

    /**
     * Get running command string
     * @return string
     */
    protected function getRunningCommandString()
    {
        return implode(' ', $_SERVER['argv']);
    }

    /**
     * Hook called if command is already running
     * @param  string $command
     * @return void
     */
    protected function alreadyRunning($command)
    {
        $this->error("Command \"{$command}\" is already running. Please kill it or wait for the end");

        exit();
    }
}
