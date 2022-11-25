<?php

namespace App\Modules\Invoice\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class InvalidQuoteWorkOrderException extends ApiException
{
    protected $level = self::LEVEL_ALERT;
    
    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::QUOTE_INVALID_WORK_ORDER;
    }

    public function getApiMessage()
    {
        return $this->trans->get('invoices.clone_quote.work_order_invalid');
    }

    public function getDevMessage()
    {
        return 'There is no work order record for quote that invoice should be cloned from. Possible database invalid data';
    }
}
