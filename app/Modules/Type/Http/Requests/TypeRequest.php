<?php

namespace App\Modules\Type\Http\Requests;

use App\Http\Requests\Request;

class TypeRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = $this->getRules();

        $segments = $this->segments();
        $id = intval(end($segments));
        if ($id != 0) {
            $rules['sub_type_id'][] = 'not_in:' . $id;
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
        $id = $this->input('id', 'NULL');
        $type = $this->input('type');

        return [
            'type'        => ['required', 'max:128'],
            'type_key'    => ['required', 'max:255'],
            'type_value'  => [
                'required',
                'max:32',
                'unique:type,type_value,' . $id . ',type_id,type,' . $type,
            ],
            'sub_type_id' => [
                'present',
                'integer',
                'exists:type,type_id',
            ],
            'color'       => ['present', 'regex:/#[0-9A-F]{6}/i'],
            'orderby'     => ['present', 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        return [
            'sub_type_id' => 'int',
            'orderby'     => 'int_or_null',
            'color'       => 'trim_or_null',
        ];
    }

    /**
     * Get the input that should be fed to the validator.
     *
     * @return array
     */
    public function validationData()
    {
        $data = $this->all();
        if (isset($data['sub_type_id']) && $data['sub_type_id'] == 0) {
            $data['sub_type_id'] = '';
        }

        return $data;
    }
}
