<?php

namespace App\Modules\WorkOrder\Http\Requests;

use Illuminate\Support\Facades\App;
use App\Http\Requests\Request;

class AcceptLaborsRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'work_order_id'           => ['required'],
            'work_order_number'       => ['required'],
            'person_id'               => ['required'],
            'labors'                  => ['required', 'array'],
            'labors.*.quantity_after' => ['required'],
            'labors.*.inventory_id'   => ['required'],
            'labors.*.name'           => ['required'],
        ];

        return $rules;
    }
}
