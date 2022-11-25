<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\NoGroupPermissionException;
use App\Core\Exceptions\NoPermissionException;
use App\Core\User;
use App\Modules\Mobile\Exceptions\NoMoreDiskSpaceException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use ValidatesRequests, DispatchesJobs;

    /**
     * Checks permission for current user
     *
     * @param  array $permissions
     *
     * @return void
     *
     * @throws NoPermissionException
     */
    public function checkPermissions(array $permissions)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user || !$user->hasPermissions($permissions)) {
            /** @var NoPermissionException $exp */
            $exp = App::make(NoPermissionException::class);
            $exp->setData(['permissions' => $permissions]);
            throw $exp;
        }
    }

    /**
     * Checks if user has permissions to any action from $group
     *
     * @param  string $group
     *
     * @return void
     *
     * @throws NoPermissionException
     */
    public function checkGroupPermissions($group)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user || !$user->hasGroupPermissions($group)) {
            /** @var NoPermissionException $exp */
            $exp = App::make(NoGroupPermissionException::class);
            $exp->setData(['group' => $group]);
            throw $exp;
        }
    }


    /**
     * Gets user permission statuses by their names
     *
     * @param array $permissions
     *
     * @return array
     */
    public function getPermissionsStatus(array $permissions)
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->verifyPermissions($permissions);
    }
    
    /**
     * Check if is free space on disk if no then return 500 code
     */
    protected function checkDiskFreeSpace()
    {
        if (config('mobile.check_disk_free_space_on_server', true)) {
            $diskPartition = config('mobile.disk_partition_where_is_crm', '/');
            $minAllowedFreeSpace = (int)config('mobile.min_allowed_disk_free_space_in_mb', 50000000000000000);
            $freeSpace = disk_free_space($diskPartition);

            if ($freeSpace < $minAllowedFreeSpace * 1048576) {
                throw App::make(NoMoreDiskSpaceException::class);
            }
        }
    }
}
