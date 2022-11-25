<?php

namespace App\Modules\History\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Permission;

class HistoryDatabaseSeeder extends Seeder
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

        $result = Permission::set('history', $list);

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
            'history.index' => 'History index',
            /*'history.show'    => 'History show',
            'history.store'   => 'History store',
            'history.update'  => 'History update',
            'history.destroy' => 'History destroy',*/
        ];
    }
}
