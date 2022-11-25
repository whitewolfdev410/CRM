<?php

namespace App\Modules\WorkOrder\Http\Requests;

/**
 * @property int work_order_type_id
 * @property int crm_priority_type_id
 * @property string description
 */
class WorkOrderMobileStoreRequest extends WorkOrderRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "work_order_type_id"   => ['required', 'exists:type,type_id'],
            "crm_priority_type_id" => ['required', 'exists:type,type_id'],
            "description"          => ['required']
        ];
    }
}
