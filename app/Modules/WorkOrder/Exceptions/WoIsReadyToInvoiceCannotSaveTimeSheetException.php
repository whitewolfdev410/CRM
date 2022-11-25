<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class WoIsReadyToInvoiceCannotSaveTimeSheetException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::WO_IS_READY_TO_INVOICE_CANNOT_SAVE_TIMESHEETS;
    }

    public function getApiMessage()
    {
        return $this->trans->get('work_order.is_ready_to_invoice_cannot_save_time_sheets');
    }

    public function getDevMessage()
    {
        return 'Work order is ready to invoice. No time sheets can be saved';
    }
}
