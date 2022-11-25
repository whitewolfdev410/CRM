<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoCurrentlyAssignedException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_CURRENTLY_ASSIGNED_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_status_change.currently_assigned');
    }

    public function getDevMessage()
    {
        return 'There is already status set for this lpwo as assigned';
    }
}
