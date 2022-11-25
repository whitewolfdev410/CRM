<?php

namespace App\Modules\Person\Http\Requests;

use App\Http\Requests\Request;

class PersonDataRequest extends Request
{
    protected $action = null;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return $this->getRules();
    }

    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function getRules()
    {
        $data = $this->all();
        $personDataId = $this->input('id', 'NULL');
        $personId = array_key_exists('person_id', $data) ? $data['person_id'] : 0;

        return [
            'data_key'   => [
                'required',
                'string',
                'max:128',
                'unique:person_data,data_key,' . $personDataId . ',person_data_id,person_id,' . $personId,
            ],
            'data_value' => [
                'required',
                'string',
                'max:128',
            ],
        ];
    }
}
