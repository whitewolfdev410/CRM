<?php

namespace App\Modules\WorkOrder\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use App\Core\Rbac\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class WorkOrderDatabaseSeeder extends Seeder
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

        $result = Permission::set('workorder', $list);

        foreach ($result as $info) {
            $this->command->info($info);
        }

        $this->call(__NAMESPACE__.'\\WorkOrderExtensionDatabaseSeeder');
        $this->call(__NAMESPACE__.'\\WorkOrderTemplateDatabaseSeeder');
        $this->call(__NAMESPACE__.'\\LinkPersonWoDatabaseSeeder');
    }

    /**
     * Get permissions list
     */
    public function getPermissions()
    {
        return [
            'workorder.activities'              => 'WorkOrder activities',
            'workorder.activities-self-only'    => 'WorkOrder activities only for themselves',
            'workorder.assign-vendors'          => 'WorkOrder - assign vendors',
            'workorder.basic-update'            => 'WorkOrder basic update',
            'workorder.cancel'                  => 'WorkOrder cancel',
            'workorder.client_ivr'              => 'WorkOrder client ivr',
            'workorder.client_note'             => 'WorkOrder client note',
            'workorder.completion-grid'         => 'WorkOrder completion grid',
            'workorder.edit-sales-person'       => 'WorkOrder edit sales person',
            'workorder.destroy'                 => 'WorkOrder destroy',
            'workorder.index'                   => 'WorkOrder index',
            'workorder.labors'                  => 'WorkOrder labors',
            'workorder.locations'               => 'WorkOrder locations',
            'workorder.locations-photos'        => 'WorkOrder locations photos',
            'workorder.locations-vendors'       => 'WorkOrder locations vendors',
            'workorder.mobile-index'            => 'WorkOrder index mobile',
            'workorder.mobile-show'             => 'WorkOrder mobile show',
            'workorder.non_closed_list'         => 'WorkOrder non-closed list',
            'workorder.note-update'             => 'WorkOrder note update',
            'workorder.personforwo'             => 'WorkOrder person for WO',
            'workorder.pickup'                  => 'WorkOrder pickup',
            'workorder.regions'                 => 'WorkOrder regions',
            'workorder.show'                    => 'WorkOrder show',
            'workorder.store'                   => 'WorkOrder store',
            'workorder.trades'                  => 'WorkOrder trades',
            'workorder.unlock'                  => 'WorkOrder unlock',
            'workorder.unlock_force'            => 'WorkOrder unlock force',
            'workorder.unresolved-index'        => 'Unresolved WorkOrder index',
            'workorder.unresolved-update'       => 'Unresolved WorkOrder update',
            'workorder.update'                  => 'WorkOrder update',
            'workorder.vendor_details'          => 'WorkOrder vendor details',
            'workorder.vendor_summary'          => 'WorkOrder vendor summary',
            'workorder.vendors-to-assign'       => 'WorkOrder vendors to assign',
            'workorder.locked_work_orders_list' => 'Locked work orders list',

            'workorder.customer_details'                     => 'Work order customer details',
            'workorder.edit_assigned_tech'                   => 'Work order edit assigned tech',
            'workorder.edit_call_notes'                      => 'Work order edit call notes',
            'workorder.edit_customer_notes'                  => 'Work order edit customer notes',
            'workorder.edit_customer_po'                     => 'Work order edit customer po',
            'workorder.edit_problem_notes'                   => 'Work order edit problem notes',
            'workorder.edit_schedule_date'                   => 'Work order edit schedule date',
            'workorder.edit_site_notes'                      => 'Work order edit site notes',
            'workorder.edit_processed_by'                    => 'Work order edit edit processed by',
            'workorder.edit_sl_tech_status_assigned'         => 'Work order edit sl tech status assigned',
            'workorder.edit_sl_tech_status_completed'        => 'Work order edit sl tech status completed',
            'workorder.edit_sl_tech_status_incomplete'       => 'Work order edit sl tech status incomplete',
            'workorder.edit_sl_tech_status_in_route'         => 'Work order edit sl tech status in route',
            'workorder.edit_sl_tech_status_ready_to_cancel'  => 'Work order edit sl tech status ready to cancel',
            'workorder.edit_sl_tech_status_ready_to_invoice' => 'Work order edit sl tech status ready to invoice',
            'workorder.edit_sl_tech_status_work_in_progress' => 'Work order edit sl tech status work in progress',

            'workorder.labors-to-accept' => 'Labors list to accept',
            'workorder.labors-accept'    => 'Accept labors'
        ];
    }
}
