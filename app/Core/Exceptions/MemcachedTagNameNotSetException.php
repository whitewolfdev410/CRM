<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class MemcachedTagNameNotSetException extends ApiException
{
    protected $level = self::LEVEL_ALERT;

    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::MEMCACHED_TRAIT_TAG_NOT_SET;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        return 'Tag name is not set in class using MemcachedTrait. It needs to be set in code';
    }
}
