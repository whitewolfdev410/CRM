<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsRepository;
use Illuminate\Support\Facades\Config;

class WorkOrderRackMaintenanceRequest extends Request
{
    public function rules()
    {
        return $this->getRules();
    }

    public function getRules()
    {
        $workOrderRackMaintenanceCollectionName = 'work_order_rack_maintenance';
        $workOrderRackMaintenanceRules = [
            'uuid' => ['required'],
            'work_order_id' => ['required'],
            'link_person_wo_id' => ['required']
        ];

        $workOrderRackMaintenanceItemCollectionName = 'work_order_rack_maintenance_items';
        $workOrderRackMaintenanceItemRules = [
            'uuid' => ['required'],
            'work_order_id' => ['present'],
            'link_person_wo_id' => ['required'],
            'name' => ['required'],
        ];

        $rules = [
            $workOrderRackMaintenanceCollectionName => ['array'],
            $workOrderRackMaintenanceItemCollectionName => ['array'],
        ];

        if ($this->has($workOrderRackMaintenanceCollectionName)) {
            foreach ($this->input($workOrderRackMaintenanceCollectionName) as $key => $value) {
                foreach ($workOrderRackMaintenanceRules as $field => $validators) {
                    $rules[$workOrderRackMaintenanceCollectionName.'.' . $key .'.'.$field] = $validators;
                }
            }
        }

        if ($this->has($workOrderRackMaintenanceItemCollectionName)) {
            foreach ($this->input($workOrderRackMaintenanceItemCollectionName) as $key => $value) {
                foreach ($workOrderRackMaintenanceItemRules as $field => $validators) {
                    $rules[$workOrderRackMaintenanceItemCollectionName.'.' . $key .'.'.$field] = $validators;
                }
            }
        }

        return $rules;
    }
}
