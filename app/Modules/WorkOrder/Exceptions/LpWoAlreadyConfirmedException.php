<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoAlreadyConfirmedException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_ALREADY_CONFIRMED_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_confirm.already_confirmed');
    }

    public function getDevMessage()
    {
        return 'This lpwo has different status but it had to be already confirmed earlier';
    }
}
