<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;

class WorkOrderTruckStockRequest extends Request
{
    public function rules()
    {
        return [
            'truck-stock' => ['present', 'array'],
            'truck-stock.*.link_person_wo_id' => ['required'],
            'truck-stock.*.work_order_id'     => ['required'],
            'truck-stock.*.question_type_id'  => ['present'],
            'truck-stock.*.quantity'          => ['present'],
            //'truck-stock.*.description'       => ['present'],
        ];
    }
}
