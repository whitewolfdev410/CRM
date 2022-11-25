<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Http\Requests\Request;

class InvoiceLobSendRequest extends Request
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
        return $this->getRules();
    }

    public function getRules()
    {
        return [
           'invoicesIds' => 'required'
        ];
    }
}
