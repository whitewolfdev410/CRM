<?php

namespace App\Core\Rbac;

use App\Core\LogModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * RBAC permission class
 *
 * @property string display_name
 * @property string name
 */
class Permission extends LogModel
{
    /**
     * Related DB table
     *
     * @var string
     */
    protected $table = 'rbac_permissions';

    // relationships

    /**
     * Many-to-Many relations with Roles
     *
     * @return BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'rbac_permissions_roles'
        );
    }

    // others

    /**
     * Before delete all constrained foreign relations
     *
     * @param bool $forced
     *
     * @return bool true
     *
     * @throws InvalidArgumentException
     */
    public function beforeDelete($forced = false)
    {
        DB::table('rbac_permissions_roles')
            ->where('permission_id', $this->id)
            ->delete();

        return true;
    }

    /**
     * Set permissions for Administrator
     *
     * @param string $type Permission type name
     * @param array  $list List of permissions
     *
     * @return array List of removed/created permissions and attached permissions
     */
    public static function set($type, array $list)
    {
        $result = [];

        $names = self::where('name', 'LIKE', "{$type}.%")->get()->pluck('name')->all();
        $toRemove = array_diff($names, array_keys($list));
        $toAdd = array_diff(array_keys($list), $names);

        if (count($toRemove)) {
//            self::whereIn('name', $toRemove)->delete();
//
//            foreach ($toRemove as $name) {
//                $result[] = "Permission `{$name}` has been removed.";
//            }
        }

        if (count($toAdd)) {
            $perms = [];

            foreach ($list as $name => $display) {
                if (!in_array($name, $toAdd)) {
                    continue;
                }
                $perm = new self();
                $perm->name = $name;
                $perm->display_name = $display;
                $perm->save();
                $perms[] = $perm;

                $result[] = "Permission `{$name}` has been created.";
            }

            $admin = Role::ADMIN();

            if ($admin) {
                $admin->attachPermissions($perms);

                foreach ($perms as $p) {
                    $result[]
                        =
                        "Permission `{$p->name}` has been added to {$admin->name} role.";
                }
            }
        }

        return $result;
    }
}
