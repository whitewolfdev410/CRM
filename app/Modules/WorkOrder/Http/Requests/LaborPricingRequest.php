<?php

namespace App\Modules\WorkOrder\Http\Requests;

use Illuminate\Support\Facades\App;
use App\Http\Requests\Request;

/**
 * @property mixed work_order_id
 */
class LaborPricingRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'work_order_id' => ['required']
        ];

        return $rules;
    }
}
