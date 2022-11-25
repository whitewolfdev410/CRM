<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoCurrentlyCompletedException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_CURRENTLY_COMPLETED_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_complete.currently_completed');
    }

    public function getDevMessage()
    {
        return 'There is already status set for this lpwo as completed';
    }
}
