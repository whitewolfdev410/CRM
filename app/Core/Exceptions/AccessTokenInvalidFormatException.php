<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class AccessTokenInvalidFormatException extends ApiException
{
    public function getStatusCode()
    {
        return 401;
    }

    public function getApiCode()
    {
        return ErrorCodes::ACCESS_TOKEN_INVALID_FORMAT;
    }

    public function getApiMessage()
    {
        return $this->trans->get('access_token.invalid_format');
    }

    public function getDevMessage()
    {
        return 'Access token sent in invalid format!';
    }
}
