<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Http\Requests\Request;

class InvoiceStoreForPmRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return $this->getRules();
    }

    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function getRules()
    {
        return [
            'work_order_id' => ['required'],
        ];
    }
}
