<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoResolveFailedException extends ApiException
{
    protected $level = self::LEVEL_ERROR;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_RESOLVE_FAILED_EXCEPTION;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_resolve.failed');
    }

    public function getDevMessage()
    {
        return 'Unable to resolve work order exception';
    }
}
