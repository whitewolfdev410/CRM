<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoInvalidVendorKindException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_INVALID_VENDOR_KIND;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo.assign_vendor_to_wo.invalid_kind');
    }

    public function getDevMessage()
    {
        return 'Invalid person kind given when assigning vendor to work order';
    }
}
