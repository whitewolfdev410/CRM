<?php

namespace App\Modules\UserSettings\Http\Requests;

use App\Http\Requests\Request;

class UserSettingsRequest extends Request
{
    public function rules()
    {
        return [
            'type_id'   => 'required|exists:type,type_id',
            'value'     => 'present'
        ];
    }
}
