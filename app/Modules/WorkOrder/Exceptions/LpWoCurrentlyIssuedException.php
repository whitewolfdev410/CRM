<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoCurrentlyIssuedException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_CURRENTLY_ISSUED_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_status_change.currently_issued');
    }

    public function getDevMessage()
    {
        return 'There is already status set for this lpwo as issued';
    }
}
