<?php

namespace App\Modules\Invoice\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class InvoiceMissingDataException extends ApiException
{
    protected $level = self::LEVEL_ALERT;
    
    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::INVOICE_MISSING_DATA;
    }

    public function getApiMessage()
    {
        //Todo: added message
        return null;
    }

    public function getDevMessage()
    {
        //Todo: added message
        return null;
    }
}
