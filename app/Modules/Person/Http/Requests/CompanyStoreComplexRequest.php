<?php

namespace App\Modules\Person\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

class CompanyStoreComplexRequest extends CompanyStoreRequest
{
    /**
     * Validation errors
     *
     * @var array
     */
    protected $validationErrors;


    /**
     * Set validation errors to $validationErrors property
     *
     * @param Validator $validator
     *
     * @return void
     */
    protected function failedValidation(Validator $validator)
    {
        $this->validationErrors = $this->formatErrors($validator);
    }

    /**
     * Get validation errors array
     *
     * @return mixed
     */
    public function getValidationErrors()
    {
        return $this->validationErrors;
    }
}
