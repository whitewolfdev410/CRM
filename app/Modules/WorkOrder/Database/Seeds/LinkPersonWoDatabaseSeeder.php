<?php

namespace App\Modules\WorkOrder\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Permission;

class LinkPersonWoDatabaseSeeder extends Seeder
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

        $result = Permission::set('link-person-wo', $list);

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
            'link-person-wo.index' => 'Link Person Wo index',
            'link-person-wo.show' => 'Link Person Wo show',
            'link-person-wo.mobile-show' => 'Link Person Wo mobile show',
            'link-person-wo.store' => 'Link Person Wo store',
            'link-person-wo.update' => 'Link Person Wo update',
            'link-person-wo.destroy' => 'Link Person Wo destroy',
            'link-person-wo.count-alerts' => 'Link Person Wo count alerts',
            'link-person-wo.confirm-wo' => 'Link Person Wo confirm work order',
            'link-person-wo.complete-wo' => 'Link Person Wo complete work order',
            'link-person-wo.bulk-complete-wo' => 'Link Person Wo bulk complete work order',
            'link-person-wo.status' => 'Link Person Wo status change',
            'link-person-wo.get-job-description' => 'Link Person Wo get job description',
            'link-person-wo.update-job-description' => 'Link Person Wo get job description',
            'link-person-wo.print-choose' => 'Link Person Wo - print - choose (files and actions)',
            'link-person-wo.print-pdf-generate' => 'Link Person Wo - print - generate PDF',
            'link-person-wo.print-email-info' => 'Link Person Wo - print - e-mail info',
            'link-person-wo.print-fax-info' => 'Link Person Wo - print - fax info',
            'link-person-wo.print-email-send' => 'Link Person Wo - print - e-mail send',
            'link-person-wo.print-fax-send' => 'Link Person Wo - print - fax send',
            'link-person-wo.print-download' => 'Link Person Wo - print - download',
        ];
    }
}
