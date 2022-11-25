<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class NotImplementedException extends ApiException
{
    protected $level = self::LEVEL_ALERT;
    
    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::NOT_IMPLEMENTED_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('not_implemented_error');
    }

    public function getDevMessage()
    {
        return 'This functionality is not implemented yet. Please contact CRM team';
    }
}
