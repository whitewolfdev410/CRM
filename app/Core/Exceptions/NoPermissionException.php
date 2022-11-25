<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Rbac\Permission;

class NoPermissionException extends ApiException
{
    public function getStatusCode()
    {
        return 403;
    }

    public function getApiCode()
    {
        return ErrorCodes::NO_PERMISSION_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('no_access_permission');
    }

    public function getDevMessage()
    {
        $data = $this->getData();
        if (isset($data['permissions'])) {
            $displayPermissions = [];
            
            try {
                $permissions = Permission::whereIn('name', $data['permissions'])
                    ->pluck('display_name', 'name');

                foreach ($permissions as $name => $displayName) {
                    $nameParts = explode('.', $name);
                    $prefix = array_shift($nameParts);

                    $displayPermissions[] = $prefix.'->'.$displayName;
                }
            } catch (\Exception $e) {
                $displayPermissions = $data['permissions'];
            }
            
            if(!empty($data['message'])) {
                return $data['message'];
            } else {
                return 'User should have access to all given permissions: '.implode(',', $displayPermissions);
            }
        } else {
            return 'User has no permission for given action. User should have access to all given permissions (see `permissions` data)';
        }
    }
}
