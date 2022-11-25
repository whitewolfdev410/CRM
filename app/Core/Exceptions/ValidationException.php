<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class ValidationException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::GENERAL_VALIDATION_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('validation_general_error');
    }

    public function getDevMessage()
    {
        return "Check out 'fields' to get detailed errors for each field.";
    }
}
