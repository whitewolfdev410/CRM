<?php

namespace App\Modules\Person\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Support\Facades\Config;

class PersonRequest extends Request
{
    protected $selectedKind = 'person';

    protected $action;

    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        $rules = [];

        $configArray = 'modconfig.' . $this->selectedKind;

        $columns = config($configArray . '.columns.' . $this->action);

        $fields = config($configArray . '.fields');

        foreach ($columns as $column) {
            if (!isset($fields[$column])
                || !is_array($fields[$column])
                || !isset($fields[$column]['rules'])
            ) {
                $rules[$column][] = 'present';
                continue;
            }

            $rules[$column] = array_merge($fields[$column]['rules'], ['present']);
        }

        $data = $this->all();
        $personId = $this->input('id', 'NULL');
        $custom_1 = array_key_exists('custom_1', $data) ? $data['custom_1'] : 0;
        $custom_2 = array_key_exists('custom_2', $data) ? $data['custom_2'] : 0;
        $custom_3 = array_key_exists('custom_3', $data) ? $data['custom_3'] : 0;
        $rules['custom_1'] = array_merge(
            isset($rules['custom_1']) ? $rules['custom_1'] : [],
            [
                'unique:person,custom_1,' . $personId . ',person_id,custom_2,"' . $custom_2 . '",custom_3,"' . $custom_3. '"',
            ]
        );
        $rules['custom_2'] = array_merge(
            isset($rules['custom_2']) ? $rules['custom_2'] : [],
            [
                'unique:person,custom_2,' . $personId . ',person_id,custom_1,"' . $custom_1 . '",custom_3,"' . $custom_3 . '"',
            ]
        );
        $rules['custom_3'] = array_merge(
            isset($rules['custom_3']) ? $rules['custom_3'] : [],
            [
                'unique:person,custom_3,' . $personId . ',person_id,custom_1,"' . $custom_1 . '",custom_2,"' . $custom_2 . '"' ,
            ]
        );

        $rules['groups'] = ['present', 'array'];

        return $rules;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatterRules()
    {
        $fields = config('modconfig.' . $this->selectedKind . '.fields');

        $rules = [];
        foreach ($fields as $field => $config) {
            if (isset($config['filter'])) {
                $rules[$field] = $config['filter'];
            }
        }

        return $rules;
    }

    public function attributes()
    {
        return [
            'custom_1' => 'First name',
            'custom_2' => 'Middle name',
            'custom_3' => 'Last name',
        ];
    }
}
