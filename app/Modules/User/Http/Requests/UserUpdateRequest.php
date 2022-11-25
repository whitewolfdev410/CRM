<?php

namespace App\Modules\User\Http\Requests;

use App\Http\Requests\Request;

class UserUpdateRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = $this->getRules();
        $data = $this->validationData();

        $segments = $this->segments();
        $id = intval(end($segments));

        $rules['email'][] = 'unique:users,email,' . $id;

        if ((isset($data['password']) && $data['password'] != '')
            || (isset($data['re_password']) && $data['re_password'] != '')
        ) {
            $rules['password'][] = 'required';
            $rules['re_password'][] = 'required';
        } else {
            $rules['password'] = ['present'];
            $rules['re_password'] = ['present'];
        }

        return $rules;
    }

    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function getRules()
    {
        return [
            'email'       => [
                'required',
                'email',
            ],
            'locale'      => [
                'required',
                'max:5',
            ],
            'password'    => [
                'present',
                'min:8',
                'regex:/[a-z]{1,}/',
                'regex:/\d{1,}/',
                'regex:/[A-Z]{1,}/',
            ],
            're_password' => [
                'present',
                'same:password',
            ],
            'roles'       => [
                'present',
                'array',
            ],

        ];
    }

    public function messages()
    {
        return [
            'password.regex' => 'Password must contain at least one lowercase letter, one uppercase letter and one numeric digit.',
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
