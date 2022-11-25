<?php

namespace App\Modules\Person\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

class LinkPersonCompanyRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $data = $this->all();

        $rules = $this->getRules();

        if (isset($data['start_date']) && $data['start_date'] != null) {
            $rules['start_date'][] = 'date_format:Y-m-d';
        }

        if (isset($data['end_date']) && $data['end_date'] != null) {
            $rules['end_date'][] = 'date_format:Y-m-d';
        }

        if (isset($data['type_id']) && $data['type_id']) {
            $rules['type_id'][] = 'exists:type,type_id';
        }

        if (isset($data['type_id2']) && $data['type_id2']) {
            $rules['type_id2'][] = 'exists:type,type_id';
        }

        $adRepo = App::make(\App\Modules\Address\Repositories\AddressRepository::class);

        $personId = isset($data['person_id']) ? intval($data['person_id']) : 0;

        $countAddress = $adRepo->getCountForPerson($personId);

        if ($countAddress > 0) {
            $rules['address_id'][]
                = 'exists:address,address_id,person_id,' . $personId;
        } else {
            $rules['address_id'][] = 'in:0';
        }

        $person2Id = isset($data['member_person_id'])
            ? intval($data['member_person_id']) : 0;

        $countAddress2 = $adRepo->getCountForPerson($person2Id);

        if ($countAddress2 > 0) {
            $rules['address_id2'][]
                = 'exists:address,address_id,person_id,' . $person2Id;
        } else {
            $rules['address_id2'][] = 'in:0';
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
        return [
            'person_id' => ['required', 'exists:person,person_id'],
            'member_person_id' => ['required', 'exists:person,person_id'],
            'address_id' => ['present'],
            'address_id2' => ['present'],
            'position' => ['present', 'max:48'],
            'position2' => ['present', 'max:48'],
            'start_date' => ['present'],
            'end_date' => ['present'],
            'type_id' => ['present'],
            'type_id2' => ['present'],
            'is_default' => ['required', 'in:0,1'],
            'is_default2' => ['required', 'in:0,1'],

        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        return [
            'person_id' => 'int',
            'member_person_id' => 'int',
            'address_id' => 'int',
            'address_id2' => 'int',
            'is_default' => 'int',
            'is_default2' => 'int',
            'type_id' => 'int_or_null',
            'type_id2' => 'int_or_null',
            'start_date' => 'trim_or_null',
            'end_date' => 'trim_or_null',
        ];
    }
}
