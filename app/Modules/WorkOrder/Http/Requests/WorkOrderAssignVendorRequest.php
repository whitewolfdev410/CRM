<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Support\Facades\App;

class WorkOrderAssignVendorRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        return [
            'person_id' => ['required', 'exists:person,person_id'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatterRules()
    {
        return [
            'person_id' => 'int',
        ];
    }
}
