<?php

namespace App\Http\Requests;

class FcmTokenRequest extends SimpleRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'fcm_token' => [
                'required',
            ],
            'device_type' => [
                'in:android,ios,browser',
            ],
        ];
    }
}
