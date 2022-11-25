<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class WoInvoicedCannotAddVendorsException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::WO_ALREADY_INVOICED_CANNOT_ADD_VENDORS;
    }

    public function getApiMessage()
    {
        return $this->trans->get('work_order.invoiced_cannot_add_vendors');
    }

    public function getDevMessage()
    {
        return 'Work order is already invoiced. No vendors can be added';
    }
}
