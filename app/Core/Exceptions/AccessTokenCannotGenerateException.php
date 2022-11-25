<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class AccessTokenCannotGenerateException extends ApiException
{
    protected $level = self::LEVEL_EMERGENCY;
    
    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::ACCESS_TOKEN_CANNOT_BE_CREATED;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        return "Access token couldn't be created. Please contact Support team.";
    }
}
