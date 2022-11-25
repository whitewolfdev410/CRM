<?php

namespace App\Modules\Person\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Permission;

class CompanyDatabaseSeeder extends Seeder
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

        $result = Permission::set('company', $list);

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
            'company.index' => 'Company index',
            'company.show' => 'Company show',
            'company.store' => 'Company store',
            'company.store.complex' => 'Company store complex',
            'company.update' => 'Company update',
            'company.destroy' => 'Company destroy',
            'company.address-vendor-index' => 'Company address vendor index',
            'company.address-vendor-store' => 'Company address vendor store',
            'company.address-vendor-destroy' => 'Company address vendor destroy',
            'company.alert-note-index' => 'Company alert note index',
            'company.alert-note-store' => 'Company alert note store',
            'company.alert-note-destroy' => 'Company alert note destroy',
            'company.owner' => 'Company owners',
            'company.owner-update' => 'Company owner update',
        ];
    }
}
