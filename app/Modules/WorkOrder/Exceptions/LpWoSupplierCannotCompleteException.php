<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoSupplierCannotCompleteException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_CANT_COMPLETE_FOR_SUPPLIER_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_complete.cant_complete_for_supplier');
    }

    public function getDevMessage()
    {
        return 'Cannot complete. Supplier must first be assigned to work order and then they will be able to complete it.';
    }
}
