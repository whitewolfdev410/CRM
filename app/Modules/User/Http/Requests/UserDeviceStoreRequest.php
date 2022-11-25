<?php

namespace App\Modules\User\Http\Requests;

use App\Http\Requests\Request;

class UserDeviceStoreRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        return [
            'user_id'     => ['required', 'integer', 'exists:users,id'],
            'number'      => [
                'required',
                'unique_device_number',
                'regex:/^\+\d{7,}$/',
                'min:8',
                'max:30',
            ],
            'device_imei' => [
                'unique:user_devices,device_imei',
            ],
            'device_id' => [
                'unique:user_devices,device_id',
            ],
            'active'      => [
                'required',
                'in:0,1',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        return [
            'user_id' => 'int',
            'active'  => 'int',
        ];
    }
}
