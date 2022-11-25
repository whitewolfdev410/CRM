<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class AccessTokenExpiredException extends ApiException
{
    public function getStatusCode()
    {
        return 498;
    }

    public function getApiCode()
    {
        return ErrorCodes::ACCESS_TOKEN_EXPIRED;
    }

    public function getApiMessage()
    {
        return $this->trans->get('access_token.expired');
    }

    public function getDevMessage()
    {
        return 'Given access token is expired. User should log in again';
    }
}
