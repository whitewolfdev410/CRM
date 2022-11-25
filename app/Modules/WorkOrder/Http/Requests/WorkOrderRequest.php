<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsRepository;
use Illuminate\Support\Facades\Config;

class WorkOrderRequest extends Request
{
    /**
     * Set rules that will be used for validation
     *
     * @param array $rules
     * @param array $data
     * @param array $valid
     *
     * @return array
     */
    public function setRules(array $rules, array $data, array $valid)
    {
        $cs = \App::make(CustomerSettingsRepository::class);

        // work_order_number
        $workOrderNumberRequiredAdd = false;

        if (isset($data['customer_setting_id'])
            && $data['customer_setting_id']
        ) {
            $csData = $cs->findSoft($data['customer_setting_id']);
            if ($csData) {
                $workOrderNumberRequiredAdd
                    = $csData->getWorkOrderNumberRequired();
            }
        }

        if ($workOrderNumberRequiredAdd) {
            // work order edit - exclude own id
            if (isset($data['id'])) {
                $rules['work_order_number'][]
                    = 'unique:work_order,work_order_number,' . $data['id']
                    . ',work_order_id';
            } else {
                $rules['work_order_number'][]
                    = 'unique:work_order,work_order_number';
            }
        }

        // company_person_id
        $ids = array_merge(
            array_keys($valid['company_person_id']['data']['companies']),
            array_keys($valid['company_person_id']['data']['persons'])
        );
        $rules['company_person_id'][]
            = 'in:' . implode(',', array_values($ids));

        // shop_address_id
        if (isset($data['company_person_id']) && $data['company_person_id']) {
            $id = (int)$data['company_person_id'];
            $rules['shop_address_id'][]
                = 'exists:address,address_id,person_id,' . $id;
        }

        if (config('app.crm_user') != 'bfc') {
            // via_type_id
            $rules = $this->addArrayRule('via_type_id', $rules, $valid);
        }
        // crm_priority_type_id
        $rules = $this->addArrayRuleIfSet(
            'crm_priority_type_id',
            $data,
            $rules,
            $valid
        );

        // invoice_status_type_id
        $rules = $this->addArrayRule('invoice_status_type_id', $rules, $valid);

        if (config('app.crm_user') != 'fs') {
            // trade_type_id
            $rules = $this->addArrayRuleIfSet(
                'trade_type_id',
                $data,
                $rules,
                $valid
            );
        }

        // estimated_time
        $rules = $this->addArrayRule('estimated_time', $rules, $valid, [0]);

        // quote_status_type_id
        $rules = $this->addArrayRuleIfSet(
            'quote_status_type_id',
            $data,
            $rules,
            $valid
        );

        // project_manager_person_id
        $rules = $this->addArrayRuleIfSet(
            'project_manager_person_id',
            $data,
            $rules,
            $valid
        );

        // scheduled_date
        if (isset($data['scheduled_date']) && $data['scheduled_date']) {
            $rules['scheduled_date'][] = 'date_format:Y-m-d H:i:s';
        }

        if (config('app.crm_user') != 'bfc') {
            // billing_company_person_id
            $rules = $this->addArrayRuleIfSet(
                'billing_company_person_id',
                $data,
                $rules,
                $valid
            );
        }

        if (config('app.crm_user') != 'fs') {
            // supplier_person_id
            $rules = $this->addArrayRuleIfSet(
                'supplier_person_id',
                $data,
                $rules,
                $valid
            );
        }

        // parts_status_type_id
        $rules = $this->addArrayRuleIfSet(
            'parts_status_type_id',
            $data,
            $rules,
            $valid
        );

        //  customer_setting_id
        if (isset($data['customer_setting_id'])
            && $data['customer_setting_id']
            && isset($data['company_person_id'])
            && $data['company_person_id']
        ) {
            $rules['customer_setting_id'][]
                = 'in:' . implode(',', $cs->getIds($data['company_person_id']));
        }

        return $rules;
    }


    /**
     * Add in array rule only if $key is set and is > 0
     *
     * @param string $key
     * @param array $data
     * @param array $rules
     * @param array $valid
     *
     * @return array
     */
    protected function addArrayRuleIfSet(
        $key,
        array $data,
        array $rules,
        array $valid
    ) {
        if (isset($data[$key]) && $data[$key]) {
            return $this->addArrayRule($key, $rules, $valid);
        }

        return $rules;
    }


    /**
     * Add in array rule for $key
     *
     * @param string $key
     * @param array $rules
     * @param array $valid
     * @param array $add
     *
     * @return array
     */
    protected function addArrayRule(
        $key,
        array $rules,
        array $valid,
        array $add = []
    ) {
        $rules[$key][] = 'in:' . implode(
            ',',
            array_merge($add, array_keys($valid[$key]['data']))
        );

        return $rules;
    }

    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function getRules()
    {
        $rules = [
            'company_person_id' => ['required'],
            'crm_priority_type_id' => ['present'],
            'description' => ['present'],
            'expected_completion_date' => ['required', 'date_format:Y-m-d'],
            'instructions' => ['present'],
            'invoice_number' => ['present', 'max:30'],
            'invoice_status_type_id' => ['present'],
            'not_to_exceed' => ['required', 'numeric', 'max:9999999.99'],
            'project_manager_person_id' => ['present'],
            'received_date' => ['required', 'date_format:Y-m-d H:i:s',],
            'request' => ['present', 'max:255'],
            'requested_by' => ['present', 'max:100'],
            'shop_address_id' => ['required'],
            'via_type_id' => ['required'],
            'work_order_number' => ['present', 'max:24'],
        ];

        if (config('app.crm_user') === 'fs') {
            $rules['acknowledged'] = ['required', 'in:0,1'];
            //$rules['bill_status_type_id'] = ['present'];
            $rules['customer_setting_id'] = ['present'];
            $rules['estimated_time'] = ['required'];
            $rules['fac_supv'] = ['present', 'max:255'];
            $rules['phone'] = ['present', 'max:100'];
            $rules['pickup_and_assign'] = ['present'];
            $rules['assign_to_person_ids'] = ['array'];
            $rules['assign_to_person_ids.*'] = ['exists:person,person_id'];
        } elseif (config('app.crm_user') === 'clm') {
            $rules['actual_completion_date'] = ['present'];
            $rules['equipment_needed'] = ['present'];
            $rules['equipment_needed_text'] = ['present'];
            $rules['mapped_trade_id'] = ['present'];
            $rules['owner_person_id'] = ['present'];
            $rules['parts_status_type_id'] = ['present'];
            $rules['priority'] = ['present', 'max:30'];
            $rules['quote_status_type_id'] = ['present'];
            $rules['region_id'] = ['present'];
            $rules['sales_person_id'] = ['present'];
            $rules['sc_check_in'] = ['present'];
            $rules['sc_check_out'] = ['present'];
            $rules['wo_type_id'] = ['required'];
        } else {
            $rules['actual_completion_date'] = ['present'];
            $rules['authorization_code'] = ['present', 'max:24'];
            $rules['category'] = ['present'];
            $rules['completion_code'] = ['present', 'max:24'];
            $rules['fin_loc'] = ['required', 'max:255'];
            $rules['scheduled_date'] = ['present'];
            $rules['store_hours'] = ['present', 'max:40'];
            $rules['supplier_person_id'] = ['present'];
            $rules['trade'] = ['present', 'max:255'];
            $rules['trade_type_id'] = ['present'];
        }

        if ((bool)config('system_settings.workorder_number_required')) {
            $rules['work_order_number'][]
                = 'unique:work_order,work_order_number';
        }

        return $rules;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        return [
            'acknowledged' => 'int',
            'crm_priority_type_id' => 'int',
            'estimated_time' => 'int',
            'customer_setting_id' => 'int',
        ];
    }
}
