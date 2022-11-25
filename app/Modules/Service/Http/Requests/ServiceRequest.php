<?php

namespace App\Modules\Service\Http\Requests;

use App\Http\Requests\Request;

class ServiceRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'service_name' => [
                'required',
                'max:48',
            ],
            'enabled' => [
                'present',
                'in:0,1',
            ],
            'short_description' => [
                'present',
                'max:100',
            ],
            'long_description' => [
                'present',
                'max:255',
            ],
            'unit' => [
                'present',
                'max:100',
            ],
            'category_type_id' => [
                'required',
                'exists:type,type_id',
            ],
            'price' => [
                'present',
                'array',
            ],
            'has_function' => [
                'present',
                'array',
            ],
            // new column - at the moment present, in future maybe required
            'msrp' => [
                'present',
            ],
        ];

        $data = $this->validationData();

        if (isset($data['unit']) && $data['unit']) {
            $rules['unit'][] = 'exists:type,type_id,type,unit';
        }

        return $rules;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        return [
            'enabled' => 'int',
            'unit' => 'int',
            'category_type_id' => 'int',
            'price' => 'safe_float',
            'msrp' => 'safe_float',
        ];
    }
}
