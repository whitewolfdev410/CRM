<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;

class WorkOrderCancelRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        return [
            'invoice_status_type_id' => [
                'required',
                'exists:type,type_id,type,wo_billing_status',
            ],
            'bill_status_type_id' => [
                'required',
                'exists:type,type_id,type,bill_status',
            ],
            'cancel_reason_type_id' => [
                'required',
                'exists:type,type_id,type,wo_cancel_reason',
            ],
            'additional_information' => [
                'present',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        return [
            'invoice_status_type_id' => 'int',
            'bill_status_type_id' => 'int',
            'cancel_reason_type_id' => 'int',
        ];
    }
}
