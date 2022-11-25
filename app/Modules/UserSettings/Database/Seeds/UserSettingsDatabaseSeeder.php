<?php

namespace App\Modules\UserSettings\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Permission;

class UserSettingsDatabaseSeeder extends Seeder
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

        $result = Permission::set('user-settings', $list);

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
            'user-settings.index'   => 'User Settings index',
            'user-settings.show'    => 'User Settings show',
            'user-settings.store'   => 'User Settings store',
            'user-settings.update'  => 'User Settings update',
            'user-settings.destroy' => 'User Settings destroy',
        ];
    }
}
