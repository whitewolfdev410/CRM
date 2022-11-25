<?php

namespace App\Modules\Service\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Permission;

class ServiceDatabaseSeeder extends Seeder
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

        $result = Permission::set('service', $list);

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
            'service.index' => 'Service index',
            'service.show' => 'Service show',
            'service.store' => 'Service store',
            'service.update' => 'Service update',
            'service.destroy' => 'Service destroy',
        ];
    }
}
