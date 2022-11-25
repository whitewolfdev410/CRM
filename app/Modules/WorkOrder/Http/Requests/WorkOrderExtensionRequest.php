<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Support\Facades\App;

class WorkOrderExtensionRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'reason' => ['required', 'max:255'],
            'extended_date' => ['required', 'date_format:Y-m-d H:i:s'],
            'work_order_id' => ['required', 'add_extension_valid_work_order'],
        ];

        $data = $this->validationData();

        if (isset($data['work_order_id']) && $data['work_order_id']) {
            $woR = App::make(WorkOrderRepository::class);
            $wo = $woR->findSoft($data['work_order_id']);
            $ecd = $wo->getExpectedCompletionDate();
            if ($ecd && $ecd != '0000-00-00 00:00:00') {
                $rules['extended_date'][] = 'after:' . $ecd;
            } else {
                $rules['extended_date'][] = 'after:' . $wo->getCreatedAt();
            }
        }

        return $rules;
    }

    /**
     * {@inheritdoc}
     */
    protected function getValidatorInstance()
    {
        $validator = parent::getValidatorInstance();
        $this->validWorkOrderStatus($validator);

        return $validator;
    }

    /**
     * Register 'present' validator extension
     *
     * @param  \Illuminate\Validation\Validator $validator
     *
     * @return void
     */
    public function validWorkOrderStatus($validator)
    {
        $validator->addImplicitExtension(
            'add_extension_valid_work_order',
            function ($attribute, $value, $parameters, $validator) {
                $wo = App::make(WorkOrderRepository::class);

                return $wo->isValidForAddingExtension($value);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        return [
            'work_order_id' => 'int',
        ];
    }
}
