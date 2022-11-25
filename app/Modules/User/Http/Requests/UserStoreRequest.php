<?php

namespace App\Modules\User\Http\Requests;

use App\Http\Requests\Request;

class UserStoreRequest extends Request
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
            'locale'      => [
                'required',
                'max:5',
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
            'person_id'   => [
                'required',
                'exists:person,person_id',
                'unique:users,person_id',
            ],
            'roles'       => [
                'present',
                'array',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        return [
            'person_id' => 'int',
        ];
    }
}
