<?php

namespace App\Modules\User\Http\Requests;

use App\Http\Requests\Request;

class ClientPortalUserUploadLogoRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        return [
            'company_person_id' => 'numeric|required',
            'image'             => 'image',
        ];
    }
}
