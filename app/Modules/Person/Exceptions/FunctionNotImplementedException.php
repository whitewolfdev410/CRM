<?php

namespace App\Modules\Person\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class FunctionNotImplementedException extends ApiException
{
    protected $level = self::LEVEL_ALERT;
    
    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::PERSON_FUNCTION_NOT_IMPLEMENTED;
    }

    public function getApiMessage()
    {
        return $this->trans->get('person.function_not_implemented_error');
    }

    public function getDevMessage()
    {
        return 'Function in Person module is not implemented. It should be implemented or configuration file for Person module should be altered';
    }
}
