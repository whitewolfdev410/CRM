<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Http\Requests\Request;
use InvalidArgumentException;

class InvoiceStatusRequest extends Request
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
            'status_type_id' => [
                'required',
            ],
        ];
    }
}
