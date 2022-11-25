<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class GeneralException extends ApiException
{
    protected $level = self::LEVEL_ALERT;

    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::GENERAL_UNKNOWN_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        return 'Unexpected error occurred. Please try again or contact API team';
    }
}
