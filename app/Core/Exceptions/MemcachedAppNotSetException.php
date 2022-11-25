<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class MemcachedAppNotSetException extends ApiException
{
    protected $level = self::LEVEL_ALERT;

    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::MEMCACHED_TRAIT_APP_NOT_SET;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        return 'App is not set in class using MemcachedTrait. It needs to be set in code';
    }
}
