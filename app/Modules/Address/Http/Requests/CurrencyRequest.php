<?php

namespace App\Modules\Address\Http\Requests;

use App\Http\Requests\Request;

class CurrencyRequest extends Request
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
        ];

        $rule = 'unique:currencies';

        $segments = $this->segments();
        $id = intval(end($segments));
        if ($id != 0) {
            $rule .= ',code,' . $id;
        }

        $rules['code'][] = $rule;

        if (isset($data['code']) && $id != 0) {
            $rules['code'][] = 'currency_not_used:' . $id;
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
        ];
    }
}
