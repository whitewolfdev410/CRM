<?php

namespace App\Modules\User\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Oauth2\AccessToken;
use App\Core\User;
use App\Core\Rbac\Role;
use App\Core\Rbac\Permission;

class UserDeviceTokenDatabaseSeeder extends Seeder
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

        $result = Permission::set('user-device-token', $list);

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
            'user-device-token.store' => 'User devices token store',
        ];
    }
}
