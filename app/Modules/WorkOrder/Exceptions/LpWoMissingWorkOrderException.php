<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoMissingWorkOrderException extends ApiException
{
    protected $level = self::LEVEL_EMERGENCY;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_MISSING_WORKORDER;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo.missing_workorder');
    }

    public function getDevMessage()
    {
        return 'Work order for this lpwo does not exists';
    }
}
