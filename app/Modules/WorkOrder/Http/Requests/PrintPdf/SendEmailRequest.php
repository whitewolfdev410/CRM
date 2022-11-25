<?php

namespace App\Modules\WorkOrder\Http\Requests\PrintPdf;

class SendEmailRequest extends GeneratePdfRequest
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
            'email_to' => ['required', 'email'],
            'email_subject' => ['required'],
            'email_description' => ['required'],
        ]);
    }
}
