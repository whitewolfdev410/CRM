<?php

namespace App\Modules\Address\Http\Requests;

use App\Http\Requests\Request;

class CountryRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'code' => ['required', 'max:3'],
            'name' => ['required', 'max:100'],
            'orderby' => ['present'],
            'phone_prefix' => ['present', 'max:10'],
            'currency' => ['present', 'max:5'],
        ];

        $data = $this->validationData();

        $rule = 'unique:countries';

        $segments = $this->segments();
        $id = intval(end($segments));
        if ($id != 0) {
            $rule .= ',code,' . $id;
        }

        $rules['code'][] = $rule;

        if (isset($data['currency']) && $data['currency']) {
            $rules['currency'][] = 'exists:currencies,code';
        }

        if (isset($data['code']) && $id != 0) {
            $rules['code'][] = 'country_not_used:' . $id;
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
            'orderby' => 'int',
            'currency' => 'trim_upper',
        ];
    }
}
