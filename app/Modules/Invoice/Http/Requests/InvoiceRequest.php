<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Http\Requests\Request;

class InvoiceRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        return [
            'work_order_id'  => ['required', 'exists:work_order,work_order_id'],
            'invoice_number' => ['string', 'nullable'],
            'date_due'       => ['required', 'date'],
            'date_invoice'   => ['required', 'date'],

            'customer_request_description' => ['string', 'nullable'],
            'job_description'              => ['string', 'nullable'],

            'entries'               => ['array'],
            'entries.*.item_id'     => ['numeric', 'nullable', 'exists:item,item_id'],
            'entries.*.service_id'  => ['numeric', 'nullable', 'exists:service,service_id'],
            'entries.*.description' => ['string', 'nullable'],
            'entries.*.qty'         => ['required', 'numeric'],
            'entries.*.price'       => ['required', 'numeric'],
            'entries.*.total'       => ['required', 'numeric'],
            'entries.*.taxable'     => ['boolean', 'nullable']
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        return [
        ];
    }

    /**
     * Get the input that should be fed to the validator.
     *
     * @return array
     */
    public function validationData()
    {
        return $this->all();
    }
}
