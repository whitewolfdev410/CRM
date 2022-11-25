<?php

namespace App\Modules\WorkOrder\Http\Requests\PrintPdf;

class SendFaxRequest extends GeneratePdfRequest
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        $rules = parent::rules();

        return array_merge($rules, [
            'fax_number' => ['required', 'digits:11'],
        ]);
    }
}
