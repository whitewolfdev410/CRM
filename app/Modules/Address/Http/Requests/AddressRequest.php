<?php

namespace App\Modules\Address\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Support\Facades\DB;

class AddressRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'address_1'       => ['max:48'],
            'address_2'       => ['max:48'],
            'city'            => ['max:48'],
            'county'          => ['max:28'],
            'zip_code'        => ['max:14'],
            'country'         => [
                'present',
                'max:24',
                'exists:countries,code',
            ],
            'state'           => ['max:24'],
            'address_name'    => [
                'max:100'
            ],
            'latitude'        => ['present', 'max:15'],
            'longitude'       => ['present', 'max:15'],
            'coords_accuracy' => ['max:4'],
            'is_default'      => ['required', 'in:0,1'],
            'person_id'       => [
                'required',
                'integer',
                'exists:person,person_id',
            ],
        ];

        $data = $this->validationData();
        $segments = $this->segments();

        $id = (int) end($segments);
        if ($id != 0) {
            $rules['person_id'][] = 'exists:address,person_id,address_id,' . $id;
        }

        if (isset($data['state'], $data['country'])) {
            $country = DB::table('countries')->where('code', $data['country'])
                ->first();

            if ($country) {
                $rules['state'][]
                    = 'exists:states,code,country_id,' . $country->id;
            }
        }

        return $rules;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatterRules()
    {
        return [
            'state'        => 'trim_upper',
            'country'      => 'trim_upper',
            'is_default'   => 'int',
            'person_id'    => 'int',
        ];
    }
}
