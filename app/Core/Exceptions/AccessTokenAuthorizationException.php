<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class AccessTokenAuthorizationException extends ApiException
{
    public function getStatusCode()
    {
        return 498;
    }

    public function getApiCode()
    {
        return ErrorCodes::ACCESS_TOKEN_UNAUTHORIZED;
    }

    public function getApiMessage()
    {
        return $this->trans->get('access_token.unauthorized');
    }

    public function getDevMessage()
    {
        return 'Given access token does not exist! It might be removed. User should log in again';
    }
}
