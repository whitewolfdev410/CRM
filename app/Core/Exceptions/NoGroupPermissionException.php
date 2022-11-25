<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class NoGroupPermissionException extends ApiException
{
    public function getStatusCode()
    {
        return 403;
    }

    public function getApiCode()
    {
        return ErrorCodes::NO_GROUP_PERMISSION_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('no_access_permission');
    }

    public function getDevMessage()
    {
        return 'User has no permission for any action for given group';
    }
}
