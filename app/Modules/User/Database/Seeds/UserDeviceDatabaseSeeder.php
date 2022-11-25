<?php

namespace App\Modules\User\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use App\Core\Rbac\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class UserDeviceDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $list = $this->getPermissions();

        $result = Permission::set('user-device', $list);

        foreach ($result as $info) {
            $this->command->info($info);
        }
    }

    /**
     * Get permissions list
     */
    public function getPermissions()
    {
        return [
            'user-device.index'   => 'User device index',
            'user-device.store'   => 'User device store',
            'user-device.update'  => 'User device update',
            'user-device.destroy' => 'User device destroy',
        ];
    }
}
