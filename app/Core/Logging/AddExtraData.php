<?php

namespace App\Core\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;
use Illuminate\Support\Facades\Request;

class AddExtraData
{
    /**
     * Add custom process to monolog
     * @param  Logger $logger
     * @return void
     */
    public function __invoke(Logger $logger)
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof Monolog) {
            $this->pushMonologProcessor($monolog);
        }
    }

    /**
     * Configure log handler to add additional data
     *
     * @param Monolog $monolog
     */
    protected function pushMonologProcessor(Monolog $monolog)
    {
        $monolog->pushProcessor(function ($record) {
            $command = Request::server('argv', null);
            if (is_array($command)) {
                $command = implode(' ', $command);
            }

            $userId = 0;
            $personId = 0;

            $record['extra'] =
                [
                    'user' => [
                        'id' => $userId,
                        'person_id' => $personId,
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
                    //Commented out because when uploading files correctly it throws FileNotFoundException. The file \"/tmp/phpUiCtLJ\" does not exist"
                    //'input' => Request::all(),  
                ];
            }

            return $record;
        });
    }
}
