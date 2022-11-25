<?php

namespace App\Modules\Address\Http\Requests;

use App\Http\Requests\Request;

class StateRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $data = $this->validationData();

        $rules = [
            'code' => ['required', 'max:5'],
            'name' => ['required', 'max:100'],
            'country_id' => ['required', 'exists:countries,id'],
        ];

        $segments = $this->segments();
        $id = intval(end($segments));

        if (isset($data['country_id'])) {
            $countryId = (int)$data['country_id'];

            $rule = 'unique:states,';

            if ($id != 0) {
                $rule .= 'code,' . $id;
            } else {
                $rule .= 'code,null';
            }
            $rule .= ',id,country_id,' . $countryId;
            $rules['code'][] = $rule;

            if ($id != 0) {
                $rules['country_id'] = 'state_not_used:' . $id;
            }
        }

        if (isset($data['code']) && $id != 0) {
            $rules['code'][] = 'state_not_used:' . $id;
        }


        return $rules;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        return [
            'code' => 'trim_upper',
            'country_id' => 'int',
        ];
    }
}
