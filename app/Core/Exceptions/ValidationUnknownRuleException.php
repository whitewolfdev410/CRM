<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class ValidationUnknownRuleException extends ApiException
{
    protected $level = self::LEVEL_ALERT;
    
    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::VALIDATOR_UNKNOWN_RULE;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        return 'Validation rule is not implemented. Its implementation should be added to API';
    }
}
