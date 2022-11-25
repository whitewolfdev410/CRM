<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Http\Requests\Request;
use InvalidArgumentException;

class InvoiceTemplateRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function rules()
    {
        return [
            'id'                    => ['numeric', 'nullable',],
            'template_name'         => ['required', 'string'],
            'person_id'             => ['required', 'exists:person,person_id'],
            'date_due_interval'     => ['string', 'nullable'],
            'date_invoice_interval' => ['string', 'nullable'],
            'currency'              => ['required', 'string'],

            'entries'               => ['required', 'array'],
            'entries.*.id'          => [
                'numeric', 'nullable', 'exists:invoice_entry_template,invoice_entry_template_id'
            ],
            'entries.*.item_id'     => ['numeric', 'nullable', 'exists:item,item_id'],
            'entries.*.service_id'  => ['numeric', 'nullable', 'exists:service,service_id'],
            'entries.*.description' => ['string', 'nullable'],
            'entries.*.qty'         => ['required', 'numeric'],
            'entries.*.price'       => ['required', 'numeric'],
            'entries.*.total'       => ['required', 'numeric'],
            'entries.*.taxable'     => ['boolean', 'nullable'],
            'entries.*.is_deleted'  => ['boolean', 'nullable'],
        ];
    }
}
