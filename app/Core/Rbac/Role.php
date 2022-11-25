<?php

namespace App\Core\Rbac;

use App\Core\LogModel;
use App\Core\User;
use App\Modules\Mainmenu\Models\Mainmenu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * RBAC permission role class
 *
 * @property string       name
 * @property Permission[] perms
 */
class Role extends LogModel
{
    /**
     * Related DB table
     *
     * @var string
     */
    protected $table = 'rbac_roles';

    protected $fillable = ['name'];

    protected $hidden = ['pivot'];

    // relationships

    /**
     * Many-to-Many relations with Users
     *
     * @return BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'rbac_assigned_roles',
            ''
        );
    }

    /**
     * Get users assigned to role (only id)
     *
     * return BelongsToMany
     */
    public function usersSimple()
    {
        return $this->belongsToMany(User::class, 'rbac_assigned_roles')
            ->select('users.id', 'rbac_assigned_roles.role_id');
    }

    /**
     * Many-to-Many relations with Permission
     *
     * @return BelongsToMany
     */
    public function perms()
    {
        return $this->belongsToMany(
            Permission::class,
            'rbac_permissions_roles',
            'role_id',
            'permission_id'
        );
    }

    /**
     * Many-to-Many relations with Mainmenu
     *
     * @return BelongsToMany
     */
    public function mainmenus()
    {
        return $this->belongsToMany(
            Mainmenu::class,
            'link_mainmenu_perm_group',
            'perm_group_id',
            'mainmenu_id'
        );
    }

    // others

    public function setPermissionsAttribute($value)
    {
        $this->attribute['permissions'] = json_encode($value);
    }

    public function getPermissionAttribute($value)
    {
        return json_decode($value, true);
    }

    public function beforeDelete($forced = false)
    {
        DB::table('rbac_assigned_roles')
            ->where('role_id', $this->id)
            ->delete();
        DB::table('rbac_permissions_roles')
            ->where('role_id', $this->id)
            ->delete();

        return true;
    }

    /**
     * Save the inputted permissions
     *
     * @param mixed $inputPermissions
     *
     * @return mixed
     */
    public function savePermissions($inputPermissions)
    {
        if (!empty($inputPermissions)) {
            $return = $this->perms()->sync($inputPermissions);
        } else {
            $return = $this->perms()->detach();
        }
        // when changing permissions always clear users cache
        $env = \App::environment();
        Cache::tags($env . '_users')->flush();

        return $return;
    }

    /**
     * Attach permission to current role
     *
     * @param mixed $permission
     *
     * @return void
     */
    public function attachPermission($permission)
    {
        if (is_object($permission)) {
            $permission = $permission->getKey();
        } else {
            if (is_array($permission)) {
                $permission = $permission['id'];
            }
        }

        $this->perms()->attach($permission);
    }

    /**
     * Detach permission from current role
     *
     * @param mixed $permission
     *
     * @return void
     */
    public function detachPermission($permission)
    {
        if (is_object($permission)) {
            $permission = $permission->getKey();
        } else {
            if (is_array($permission)) {
                $permission = $permission['id'];
            }
        }

        $this->perms()->detach($permission);
    }

    /**
     * Attach multiple permissions to current role
     *
     * @param mixed $permissions
     *
     * @return void
     */
    public function attachPermissions($permissions)
    {
        /** @var Permission[] $permissions */
        foreach ($permissions as $permission) {
            $this->attachPermission($permission);
        }
    }

    /**
     * Detach multiple permissions from current role
     *
     * @param mixed $permissions
     *
     * @return void
     */
    public function detachPermissions($permissions)
    {
        /** @var Permission[] $permissions */
        foreach ($permissions as $permission) {
            $this->detachPermission($permission);
        }
    }

    /**
     * Returns ADMIN role if available
     *
     * @return Role
     */
    public static function ADMIN()
    {
        return self::where('name', '=', 'Admin')->first();
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $options = [])
    {
        $return = parent::save($options);

        /* when saving always clear users cache - in future it probably could
           be run only for update
        */
        $env = \App::environment();
        Cache::tags($env . '_users')->flush();

        return $return;
    }

    /**
     * Get name data
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
