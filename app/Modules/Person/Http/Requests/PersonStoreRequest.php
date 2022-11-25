<?php

namespace App\Modules\Person\Http\Requests;

class PersonStoreRequest extends PersonRequest
{
    protected $action = 'store';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = parent::rules();
        $data = $this->validationData();

        // add pricing structure rules
        if (isset($data['pricing_structure_id']) && $data['pricing_structure_id'] > 0) {
            $rules['pricing_structure_id'][]
                = 'exists:pricing_structure,pricing_structure_id';
        }

        return $rules;
    }
}
