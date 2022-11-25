<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoAssignedTimeSheetsException extends ApiException
{
    protected $level = self::LEVEL_ERROR;
    
    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_ASSIGNED_TIMESHEETS_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo.assigned_time_sheets_error');
    }

    public function getDevMessage()
    {
        return 'Person is attached time sheets so they cannot take this action';
    }
}
