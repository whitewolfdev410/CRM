<?php

namespace App\Modules\Person\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Permission;

class LinkPersonCompanyDatabaseSeeder extends Seeder
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

        $result = Permission::set('link-person-company', $list);

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
            'link-person-company.index' => 'Link person-company index',
            'link-person-company.show' => 'Link person-company show',
            'link-person-company.store' => 'Link person-company store',
            'link-person-company.update' => 'Link person-company update',
            'link-person-company.destroy' => 'Link person-company destroy',
            'link-person-company.link-client-portal-users' => ' Link person company link and unlink client portal users'
        ];
    }
}
