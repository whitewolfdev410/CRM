<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Support\Facades\App;

class WorkOrderVendorsToAssignRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        return [
            'type' => ['in:company,employee,supplier'],
            'region_id' => ['exists:region,region_id'],
            'trade_id' => ['exists:type,type_id,type,company_trade'],
            'job_type' => ['in:' . implode(',', ['work', 'quote', 'recall']),],
        ];
    }
}
