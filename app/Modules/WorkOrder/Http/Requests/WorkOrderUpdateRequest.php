<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Modules\WorkOrder\Services\WorkOrderDataServiceContract;

class WorkOrderUpdateRequest extends WorkOrderRequest
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
        $valid = $ds->getRecordUpdateData();

        $rules = $this->setRules($rules, $data, $valid);

        // actual_completion_date
        if (isset($data['actual_completion_date'])
            && $data['actual_completion_date']
        ) {
            $rules['actual_completion_date'][] = 'date_format:Y-m-d H:i:s';
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
        $rules = parent::getRules();
        $rules['actual_completion_date'] = ['present'];

        return $rules;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        $rules = parent::getFormatterRules();
        $rules['actual_completion_date'] = 'trimornull';

        return $rules;
    }
}
