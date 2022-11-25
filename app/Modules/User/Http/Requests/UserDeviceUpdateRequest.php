<?php

namespace App\Modules\User\Http\Requests;

use App\Http\Requests\Request;

class UserDeviceUpdateRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        $userDeviceId = $this->input('id', 'NULL');

        return [
            'id'          => [
                'required',
                'exists:user_devices,id',
            ],
            'number'      => [
                'unique_device_number:' . $userDeviceId,
                'regex:/^\+\d{7,}$/',
                'min:8',
                'max:30',
            ],
            'device_imei' => [
                'regex:/^\d{15}$/',
                'unique:user_devices,device_imei,' . $userDeviceId . ',id',
            ],
            'device_id' => [
                'unique:user_devices,device_id,' . $userDeviceId . ',id',
            ],
            'active'      => [
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
            'user_id'     => 'int',
            'active'      => 'int',
        ];
    }
}
