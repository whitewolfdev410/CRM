<?php

namespace App\Modules\WorkOrder\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Permission;

class WorkOrderExtensionDatabaseSeeder extends Seeder
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

        $result = Permission::set('workorder-extension', $list);

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
            'workorder-extension.index'   => 'WorkOrder Extension index',
            'workorder-extension.show'    => 'WorkOrder Extension show',
            'workorder-extension.store' => 'WorkOrder Extension store',
            'workorder-extension.update'  => 'WorkOrder Extension update',
            'workorder-extension.destroy' => 'WorkOrder Extension destroy',
        ];
    }
}
