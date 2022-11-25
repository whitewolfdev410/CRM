<?php

namespace App\Modules\Invoice\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class InvoiceMissingServicesException extends ApiException
{
    protected $level = self::LEVEL_ALERT;
    
    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::INVOICE_MISSING_SERVICES;
    }

    public function getApiMessage()
    {
        return $this->trans->get('invoices.clone_quote.missing_services_with_names');
    }

    public function getDevMessage()
    {
        return "There are missing services with some names (look at 'missing_services_names' to get their names). Those services should be added to database";
    }
}
