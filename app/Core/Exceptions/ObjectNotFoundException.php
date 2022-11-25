<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class ObjectNotFoundException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 404;
    }

    public function getApiCode()
    {
        return ErrorCodes::OBJECT_NOT_FOUND_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('resource_not_found');
    }

    public function getDevMessage()
    {
        return 'No resource found (no object or no related object)';
    }
}
