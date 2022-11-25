<?php

namespace App\Core;

use Illuminate\Contracts\Auth\Guard;

/**
 * Class LoggedUser - holds logged user data
 *
 * @package App\Core
 */
class LoggedUser
{
    /**
     * Logged user object
     *
     * @var User
     */
    protected $user;

    /**
     * Repository constructor
     *
     * @param Guard $user
     */
    public function __construct(Guard $user)
    {
        $this->user = $user->user();
    }

    /**
     * Get person id
     *
     * @return int
     */
    public function getPersonId()
    {
        return $this->user->getPersonId();
    }

    /**
     * Get first role assigned to user
     *
     * @return mixed
     */
    public function role()
    {
        return $this->user->roles()->first();
    }

    /**
     * Get all User roles
     *
     * @return mixed
     */
    public function roles()
    {
        return $this->user->roles();
    }

    /**
     * Get all User permission ids
     *
     * @return mixed
     */
    public function permissionIds()
    {
        $permissions = [];

        $this->user->roles()->get()->each(function ($user) use (&$permissions) {
            /** @var Role $user */

            $perms = $user->perms()->pluck('permission_id')->all();

            $permissions = array_merge($permissions, $perms);
        });

        return array_unique($permissions);
    }
}
