<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Http\Requests\Request;

class InvoiceEntryRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function rules()
    {
        return [
            'qty'   => [
                'numeric',
                'required',
                'min:0.0000001',
            ],
            'price' => [
                'numeric',
                'required',
                'min:0.0000001',
            ],
            'total' => [
                'numeric',
                'required',
                'min:0.0000001',
            ],
        ];
    }
}
