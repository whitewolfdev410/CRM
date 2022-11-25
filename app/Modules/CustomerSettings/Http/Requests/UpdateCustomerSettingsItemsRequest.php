<?php

namespace App\Modules\CustomerSettings\Http\Requests;

use App\Http\Requests\Request;

class UpdateCustomerSettingsItemsRequest extends Request
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
        $rules = [
            'settings' => ['required']
        ];
        return $rules;
    }
}
