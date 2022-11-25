<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoChangeStatusInvalidStatusException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_STATUS_CHANGE_INVALID_STATUS_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_status_change.invalid_status');
    }

    public function getDevMessage()
    {
        return 'Invalid status given. Valid type (?) for wo_quote_status.? status should be given for lpwo that is of quote type OR force parameter is not set to 1 OR given link person wo has different type than quote';
    }
}
