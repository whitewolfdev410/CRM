<?php

namespace App\Modules\Type\Http\Requests;

use App\Http\Requests\Request;

class TypeListRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        return [
            'type' => ['required', 'max:128'],
            'children' => ['in:0,1'],
        ];
    }
}
