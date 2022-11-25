<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class NoSomePermissionException extends ApiException
{
    public function getStatusCode()
    {
        return 403;
    }

    public function getApiCode()
    {
        return ErrorCodes::NO_SOME_PERMISSION_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('no_access_permission');
    }

    public function getDevMessage()
    {
        return 'User has no permission for given action. User should have permission to any of given permissions (see `permissions` data)';
    }
}
