<?php

namespace App\Http\Requests;

class DirectLoginRequest extends SimpleRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'token' => [
                'required',
            ],
        ];
    }
}
