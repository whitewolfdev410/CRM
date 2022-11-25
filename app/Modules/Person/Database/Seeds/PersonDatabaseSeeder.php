<?php

namespace App\Modules\Person\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Permission;

class PersonDatabaseSeeder extends Seeder
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

        $result = Permission::set('person', $list);

        foreach ($result as $info) {
            $this->command->info($info);
        }

        $this->call(__NAMESPACE__ . '\\CompanyDatabaseSeeder');
        $this->call(__NAMESPACE__ . '\\LinkPersonCompanyDatabaseSeeder');
    }

    /**
     * Get permissions list
     */
    public function getPermissions()
    {
        return [
            'person.index' => 'Person index',
            'person.list' => 'Person list',
            'person.show' => 'Person show',
            'person.store' => 'Person store',
            'person.store.complex' => 'Person store complex',
            'person.update' => 'Person update',
            'person.destroy' => 'Person destroy',
            'person.export' => 'Person export',
            'person.config-mobile' => 'Get Person mobile configuration',
            'person.employees-list' => 'Employee list'
        ];
    }
}
