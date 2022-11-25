<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Http\Requests\Request;
use InvalidArgumentException;

class InvoiceDescriptionRequest extends Request
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
            'customer_request_description' => [
                'required',
            ],
        ];
    }
}
