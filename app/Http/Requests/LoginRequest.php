<?php

namespace App\Http\Requests;

class LoginRequest extends SimpleRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => [
                'required',
            ],
            'password' => [
                'required',
            ],
            'is_mobile' => [
                'in:0,1',
            ],
            'device_id' => [
                'required_if:is_mobile,1',
            ],
        ];
    }
}
