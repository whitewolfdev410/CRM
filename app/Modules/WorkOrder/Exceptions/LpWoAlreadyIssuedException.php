<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoAlreadyIssuedException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_ALREADY_ISSUED_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_status_change.already_issued');
    }

    public function getDevMessage()
    {
        return 'This lpwo has different status but it had to be already issued earlier';
    }
}
