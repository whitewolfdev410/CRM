<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoVendorCannotCompleteException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_CANT_COMPLETE_FOR_VENDOR_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_complete.cant_complete_for_vendor');
    }

    public function getDevMessage()
    {
        return 'Cannot complete. Vendor must first issue work order and then they will be able to complete it.';
    }
}
