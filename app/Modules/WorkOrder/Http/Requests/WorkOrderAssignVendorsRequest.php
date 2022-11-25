<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Support\Facades\App;

class WorkOrderAssignVendorsRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'vendors' => ['required', 'array'],
            'recall_link_person_wo_id' => [
                'exists:link_person_wo,link_person_wo_id',
            ]
        ];
        
        if (!isCrmUser('fs')) {
            $rules['job_type'] = [
                'required',
                'in:' . implode(',', ['work', 'quote', 'recall'])
            ];
        }
        
        return $rules;
    }
}
