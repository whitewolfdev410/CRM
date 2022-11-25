<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;

class WorkOrderBasicUpdateRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = $this->getRules();

        return $rules;
    }

    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function getRules()
    {
        return [
            'description' => ['present'],
            'request' => ['present', 'max:255'],
            'instructions' => ['present'],
        ];
    }
}
