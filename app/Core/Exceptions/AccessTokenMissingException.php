<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class AccessTokenMissingException extends ApiException
{
    public function getStatusCode()
    {
        return 401;
    }

    public function getApiCode()
    {
        return ErrorCodes::ACCESS_TOKEN_MISSING;
    }

    public function getApiMessage()
    {
        return $this->trans->get('access_token.missing');
    }

    public function getDevMessage()
    {
        return 'No access token sent in request';
    }
}
