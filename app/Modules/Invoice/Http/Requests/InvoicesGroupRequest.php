<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Http\Requests\Request;

/**
 * Class InvoicesGroupRequest
 * @package App\Modules\Invoice\Http\Requests
 */
class InvoicesGroupRequest extends Request
{

    /**
     * Request rules
     *
     * @return array
     */
    public function rules()
    {
        return $this->getRules();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function getRules()
    {
        $invoicesCollectionName = 'invoices';

        $rules = [
            $invoicesCollectionName => ['array']
        ];

        if ($this->filled($invoicesCollectionName)) {
            foreach ($this->input($invoicesCollectionName) as $key => $value) {
                $rules[$invoicesCollectionName . '.' . $key] = ['required', 'integer'];
            }
        }

        return $rules;
    }
}
