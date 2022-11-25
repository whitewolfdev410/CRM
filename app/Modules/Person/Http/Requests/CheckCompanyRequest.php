<?php

namespace App\Modules\Person\Http\Requests;

use App\Http\Requests\Request;

class CheckCompanyRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'billing_company_id' => ['required','numeric'],
            'company_id' => ['required','numeric'],
        ];

        return $rules;
    }
}
