<?php

namespace App\Modules\Person\Http\Requests;

class PersonNoteStoreRequest extends PersonRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'note' => 'required'
        ];
    }
}
