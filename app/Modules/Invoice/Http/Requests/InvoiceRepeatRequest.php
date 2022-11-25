<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Http\Requests\Request;
use InvalidArgumentException;

class InvoiceRepeatRequest extends Request
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
            'invoice_id'          => ['numeric', 'nullable'],
            'interval'            => ['numeric', 'nullable'],
            'interval_keyword'    => ['in:day,week,month,', 'nullable'],
            'reminder'            => ['boolean', 'nullable'],
            'next_date'           => ['date', 'nullable'],
            'days_in_advance'     => ['numeric', 'nullable'],
            'number_remaining'    => ['numeric', 'nullable'],
            'invoice_template_id' => ['numeric', 'nullable']
        ];
    }
}
