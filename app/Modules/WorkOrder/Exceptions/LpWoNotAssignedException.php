<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoNotAssignedException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;
    
    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_PERSON_NOT_ASSIGNED_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_not_assigned_error');
    }

    public function getDevMessage()
    {
        return 'Person is not assigned to work order via link person wo so they cannot take this action';
    }
}
