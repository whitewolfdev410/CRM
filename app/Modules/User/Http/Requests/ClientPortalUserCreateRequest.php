<?php

namespace App\Modules\User\Http\Requests;

use App\Http\Requests\Request;

class ClientPortalUserCreateRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email'       => [
                'required',
                'email',
                'unique:users,email',
            ],
            'password'    => [
                'required_unless:auto_generated,1',
                'min:8',
                'regex:/[a-z]{1,}/',
                'regex:/\d{1,}/',
                'regex:/[A-Z]{1,}/',
            ],
            're_password' => [
                'required_unless:auto_generated,1',
                'same:password',
            ],
            'roles'       => [
                'present',
            ],
            'company_person_id' => 'numeric|required',
            'image' => 'image',
        ];
    }
}
