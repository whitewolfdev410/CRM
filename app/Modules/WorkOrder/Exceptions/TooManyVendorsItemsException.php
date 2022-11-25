<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class TooManyVendorsItemsException extends ApiException
{
    protected $level = self::LEVEL_WARNING;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::WO_TO_MANY_VENDORS_TO_ASSIGN;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        return 'Too many vendors have been sent';
    }
}
