<?php

namespace App\Modules\CustomerSettings\Http\Requests;

use App\Http\Requests\Request;

class AssetRequiredRequest extends Request
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
            'asset_system_type_id' => ['min:0'],
            'asset_required_type_id' => ['required','numeric'],
            'color' => ['string'],
        ];

        return $rules;
    }
}
