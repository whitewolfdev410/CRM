<?php

namespace App\Modules\WorkOrder\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Permission;

class WorkOrderTemplateDatabaseSeeder extends Seeder
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

        $result = Permission::set('workorder-template', $list);

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
            'workorder-template.index'   => 'WorkOrder Template index',
            'workorder-template.show'    => 'WorkOrder Template show',
            'workorder-template.store' => 'WorkOrder Template store',
            'workorder-template.update'  => 'WorkOrder Template update',
            'workorder-template.destroy' => 'WorkOrder Template destroy',
        ];
    }
}
