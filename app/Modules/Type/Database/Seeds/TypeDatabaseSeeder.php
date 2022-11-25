<?php

namespace App\Modules\Type\Database\Seeds;

use Database\Seeders\DatabaseSeeder;
use App\Modules\Type\Models\Type;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Core\Rbac\Role;
use App\Core\Rbac\Permission;

class TypeDatabaseSeeder extends Seeder
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

        $result = Permission::set('type', $list);

        foreach ($result as $info) {
            $this->command->info($info);
        }

        $requiredTypes = [
            [
                'type' => 'item_season',
                'type_value' => 'Default',
                'type_key' => 'item_season.default',
            ],
            [
                'type' => 'item_group',
                'type_value' => 'Default',
                'type_key' => 'item_group.default',
            ],

            // quote
            [
                'type' => 'quote_attachement',
                'type_value' => 'Supplier pricing',
                'type_key' => 'quote_attachement.supplier_pricing',
            ],

            // wo_link_reason
            [
                'type' => 'wo_link_reason',
                'type_value' => 'Quoted work',
                'type_key' => 'wo_link_reason.quoted',
            ],
            [
                'type' => 'wo_link_reason',
                'type_value' => 'Callback/return trip',
                'type_key' => 'wo_link_reason.callback',
            ],
            [
                'type' => 'wo_link_reason',
                'type_value' => 'PM/PMX',
                'type_key' => 'wo_link_reason.pm',
            ],
            [
                'type' => 'wo_link_reason',
                'type_value' => 'Same site/Different jobs',
                'type_key' => 'wo_link_reason.same_site',
            ],
            [
                'type' => 'wo_link_reason',
                'type_value' => 'Other',
                'type_key' => 'wo_link_reason.other',
            ],
        ];

        $changed = false;

        foreach ($requiredTypes as $requiredType) {
            $type = Type::where('type_key', trim($requiredType['type_key']))
                ->first();
            if ($type) {
                continue;
            }
            $type = Type::where('type', trim($requiredType['type']))
                ->where('type_value', trim($requiredType['type_value']))
                ->first();

            if ($type) {
                $type->type_key = trim($requiredType['type_key']);
                $type->update();
                $changed = true;

                $this->command->info("Type - key `{$requiredType['type_key']}` was filled");
                continue;
            }

            $type = new Type();
            $type->type = trim($requiredType['type']);
            $type->type_value = trim($requiredType['type_value']);
            $type->type_key = trim($requiredType['type_key']);
            $type->save();
            $changed = true;

            $this->command->info("New type with key `{$requiredType['type_key']}` was created");
        }

        if ($changed) {
            $tRepo = \App::make(\App\Modules\Type\Repositories\TypeRepository::class);
            if (method_exists($tRepo, 'clearCache')) {
                $tRepo->clearCache();
            }
        }
    }

    /**
     * Get permissions list
     */
    public function getPermissions()
    {
        return [
            'type.index' => 'Type index',
            'type.list' => 'Type list',
            'type.show' => 'Type show',
            'type.store' => 'Type store',
            'type.update' => 'Type update',
            'type.destroy' => 'Type destroy',
        ];
    }
}
