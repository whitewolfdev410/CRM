<?php

namespace App\Modules\Address\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Permission;

class AddressDatabaseSeeder extends Seeder
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

        $result = Permission::set('address', $list);

        foreach ($result as $info) {
            $this->command->info($info);
        }
        $this->call(__NAMESPACE__ . '\\CurrencyDatabaseSeeder');
        $this->call(__NAMESPACE__ . '\\CountryDatabaseSeeder');
        $this->call(__NAMESPACE__ . '\\StateDatabaseSeeder');
        $this->call(AddressVerifyDatabaseSeeder::class);
        $this->call(AddressIssueDatabaseSeeder::class);
    }

    /**
     * Get permissions list
     */
    public function getPermissions()
    {
        return [
            'address.index' => 'Address index',
            'address.show' => 'Address show',
            'address.verify' => 'Address verify',
            'address.store' => 'Address store',
            'address.update' => 'Address update',
            'address.destroy' => 'Address destroy',
            'address.envelope' => 'Address envelope',
        ];
    }
}
