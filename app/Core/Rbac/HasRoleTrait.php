<?php

namespace App\Core\Rbac;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Trait HasRoleTrait
 *
 * @package App\Core\Rbac
 *
 * @property Role[] $roles
 */
trait HasRoleTrait
{
    /**
     * Many-to-Many relations with Role
     *
     * @return BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'rbac_assigned_roles',
            'user_id',
            'role_id'
        );
    }

    /**
     * Checks if user has a Role by its name
     *
     * @param  string $roleName
     *
     * @return bool
     */
    public function hasRole($roleName)
    {
        foreach ($this->roles as $role) {
            if ($role->name === $roleName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has permissions by their names
     *
     * @param  array $permissions
     *
     * @return bool
     */
    public function hasPermissions(array $permissions)
    {
        $checkedPermissions = [];

        foreach ($permissions as $permission) {
            $checkedPermissions[$permission] = $this->can($permission);
        }

        return !in_array(false, $checkedPermissions);
    }

    /**
     * Check if user has any permission to $group
     *
     * @param string $group
     *
     * @return bool
     */
    public function hasGroupPermissions($group)
    {
        foreach ($this->roles as $role) {
            foreach ($role->perms as $perm) {
                if (Str::startsWith($perm->name, $group)) {
                    $suffix = substr($perm->name, mb_strlen($group));
                    if (mb_strpos($suffix, '.') === false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get user permissions statuses to permissions by their names
     *
     * @param array $permissions
     *
     * @return bool[]
     */
    public function verifyPermissions(array $permissions)
    {
        $checkedPermissions = [];

        foreach ($permissions as $permission => $url) {
            $checkedPermissions[$permission] = $this->can($permission);
        }

        return $checkedPermissions;
    }

    /**
     * Check if user has a permission by its name
     *
     * @param  string $permissionName
     *
     * @return bool
     */
    public function can($permissionName)
    {
        foreach ($this->roles as $role) {
            foreach ($role->perms as $perm) {
                if ($perm->name === $permissionName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks role(s) and permission(s)
     *
     * @param  string|array $roles       Array of roles or comma separated string
     * @param  string|array $permissions Array of permissions or comma
     *                                   separated string
     * @param  bool         $validateAll
     *
     * @return bool
     */
    public function ability($roles, $permissions, $validateAll = false)
    {
        if (!is_array($roles)) {
            $roles = explode(',', $roles);
        }

        if (!is_array($permissions)) {
            $permissions = explode(',', $permissions);
        }

        $checkedRoles = [];
        $checkedPermissions = [];

        foreach ($roles as $role) {
            $checkedRoles[$role] = $this->hasRole($role);
        }

        foreach ($permissions as $permission) {
            $checkedPermissions[$permission] = $this->can($permission);
        }

        if (($validateAll
                && !(in_array(false, $checkedRoles)
                    || in_array(false, $checkedPermissions)))
            || (!$validateAll
                && (in_array(true, $checkedRoles)
                    || in_array(true, $checkedPermissions)))
        ) {
            $validateAll = true;
        } else {
            $validateAll = false;
        }

        return $validateAll;
    }

    /**
     * Alias to eloquent many-to-many relation's attach method
     *
     * @param  mixed $role
     *
     * @return void
     */
    public function attachRole($role)
    {
        if (is_object($role)) {
            /** @var Role $role */
            $role = $role->getKey();
        } else {
            if (is_array($role)) {
                $role = $role['id'];
            }
        }

        $this->roles()->attach($role);
    }

    /**
     * Alias to eloquent many-to-many relation's detach method
     *
     * @param mixed $role
     *
     * @return void
     */
    public function detachRole($role)
    {
        if (is_object($role)) {
            /** @var Role $role */
            $role = $role->getKey();
        } else {
            if (is_array($role)) {
                $role = $role['id'];
            }
        }

        $this->roles()->detach($role);
    }

    /**
     * Attach multiple roles to a user
     *
     * @param mixed $roles
     *
     * @return void
     */
    public function attachRoles($roles)
    {
        /** @var Role[] $roles */
        foreach ($roles as $role) {
            $this->attachRole($role);
        }
    }

    /**
     * Detach multiple roles from a user
     *
     * @param mixed $roles
     *
     * @return void
     */
    public function detachRoles($roles)
    {
        /** @var Role[] $roles */
        foreach ($roles as $role) {
            $this->detachRole($role);
        }
    }
}
