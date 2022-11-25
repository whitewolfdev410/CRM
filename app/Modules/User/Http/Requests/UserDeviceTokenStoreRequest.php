<?php

namespace App\Modules\User\Http\Requests;

use App\Http\Requests\Request;

class UserDeviceTokenStoreRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        return [
            'device_type' => ['required', 'in:android,ios'],
            'device_token'  => ['required', 'min:1'],
        ];
    }
}
