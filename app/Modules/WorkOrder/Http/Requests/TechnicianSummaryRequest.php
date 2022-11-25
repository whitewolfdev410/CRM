<?php

namespace App\Modules\WorkOrder\Http\Requests;

use Illuminate\Support\Facades\App;
use App\Http\Requests\Request;

class TechnicianSummaryRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'person_id' => ['required_without:tech_id', 'integer'],
            'tech_id'   => ['required_without:person_id', 'string'],
        ];

        return $rules;
    }
}
