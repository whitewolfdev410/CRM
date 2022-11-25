<?php

namespace App\Modules\Invoice\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Permission;

class InvoiceDatabaseSeeder extends Seeder
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

        $result = Permission::set('invoice', $list);

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
            'invoice.index' => 'Invoice index',
            'invoice.show' => 'Invoice show',
            'invoice.store' => 'Invoice store',
            'invoice.update' => 'Invoice update',
            'invoice.destroy' => 'Invoice destroy',
            'invoice.store-from-quote' => 'Invoice store from quote',
            'invoice.store-many-from-quote' => 'Invoice store multiple from quote',
            'invoice.store-for-pm' => 'Invoice store for pm',
            'invoice.store-from-import' => 'Invoice store from import',
            'invoice.send' => 'Invoice send',
            'invoice.group' => 'Group invoices',
            'invoice.batches-list' => 'Invoices batches',
            'invoice.batches-get' => 'Get batch',
            'invoice.activities' => 'Invoice activities',
            'invoice.activities-self-only' => 'Invoice activities only for themselves',
            'invoice.pdf' => 'Invoice pdf generator',
            
            'invoice.index-lob' => 'Invoice index LOB',
            'invoice.send-lob' => 'Invoice send LOB',
            'invoice.show-lob' => 'Invoice show LOB',
            'invoice.letter-events' => 'Invoice letter events LOB',
            
            'invoice.template-index' => 'Invoice template index',
            'invoice.template-show' => 'Invoice template show',
            'invoice.template-store' => 'Invoice template store',
            'invoice.template-update' => 'Invoice template update',
            'invoice.template-destroy' => 'Invoice template destroy',

            'invoice.repeat-index' => 'Invoice repeat index',
            'invoice.repeat-show' => 'Invoice repeat show',
            'invoice.repeat-store' => 'Invoice repeat store',
            'invoice.repeat-update' => 'Invoice repeat update',
            'invoice.repeat-destroy' => 'Invoice repeat destroy',
        ];
    }
}
