<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class HttpNotFoundException extends ApiException
{
    public function getStatusCode()
    {
        return 404;
    }

    public function getApiCode()
    {
        return ErrorCodes::HTTP_NOT_FOUND_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('404_not_found');
    }

    public function getDevMessage()
    {
        return 'Invalid URL or HTTP method. This is not a valid API action. Make sure both URL and HTTP method (POST/GET,...) is valid';
    }
}
