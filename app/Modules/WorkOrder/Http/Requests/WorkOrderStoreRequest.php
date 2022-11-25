<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Modules\WorkOrder\Services\WorkOrderDataServiceContract;

class WorkOrderStoreRequest extends WorkOrderRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = $this->getRules();
        $data = $this->validationData();

        $ds = \App::make(WorkOrderDataServiceContract::class);
        $valid = $ds->getRecordCreateData();

        $rules = $this->setRules($rules, $data, $valid);

        return $rules;
    }

    /**
     * {@inheritdoc}
     */
    public function getRules()
    {
        $rules = parent::getRules();
        $rules['expected_completion_date'] = ['required', 'date_format:Y-m-d'];
        $rules['pickup_and_assign'] = ['required', 'in:0,1'];

        return $rules;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        $rules = parent::getFormatterRules();
        $rules['pickup_and_assign'] = 'int';

        return $rules;
    }
}
