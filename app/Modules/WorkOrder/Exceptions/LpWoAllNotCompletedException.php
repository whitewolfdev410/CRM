<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoAllNotCompletedException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_ALL_NOT_COMPLETED_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_all_not_completed_error');
    }

    public function getDevMessage()
    {
        return 'To continue, all active technicians need to be in the completed status.';
    }
}
