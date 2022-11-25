<?php

namespace App\Modules\CustomerSettings\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Permission;

class CustomerSettingsDatabaseSeeder extends Seeder
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

        $result = Permission::set('customersettings', $list);

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
            'customersettings.index' => 'CustomerSettings index',
            'customersettings.show' => 'CustomerSettings show',
            'customersettings.store' => 'CustomerSettings store',
            'customersettings.update' => 'CustomerSettings update',
            'customersettings.destroy' => 'CustomerSettings destroy',
        ];
    }
}
