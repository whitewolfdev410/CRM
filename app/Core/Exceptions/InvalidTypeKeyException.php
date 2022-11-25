<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class InvalidTypeKeyException extends ApiException
{
    protected $level = self::LEVEL_ALERT;
    
    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::INVALID_TYPE_KEY_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        return 'Invalid type key sent in request. Make sure type key you use does exist or ask administrator to add it to database.';
    }
}
