<?php

namespace App\Http\Requests;

use App\Modules\User\Http\Requests\UserUpdateRequest;

class PasswordResetRequest extends UserUpdateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = $this->getRules();

        unset($rules['locale']);
        $rules['token'] = 'required';

        unset($rules['roles']);

        return $rules;
    }
}
