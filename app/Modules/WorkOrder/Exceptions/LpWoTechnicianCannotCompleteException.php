<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoTechnicianCannotCompleteException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_CANT_COMPLETE_FOR_TECHNICIAN_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_complete.cant_complete_for_technician');
    }

    public function getDevMessage()
    {
        return 'Cannot complete. Technician must first confirm work order and then they will be able to complete it.';
    }
}
