<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;

class WorkOrderTemplateRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'work_order_template_id'            => ['numeric'],
            'template_name'                     => ['required'],
            'work_order_number'                 => ['string', 'max:24'],
            'company_person_id'                 => ['numeric'],
            'description'                       => ['string'],
            'acknowledged_person_id'            => ['numeric'],
            'completion_code'                   => ['string'],
            'estimated_time'                    => ['numeric'],
            'request'                           => ['string'],
            'not_to_exceed'                     => ['numeric', 'max:9999999.99'],
            'instructions'                      => ['string'],
            'requested_by'                      => ['string'],
            'crm_priority_type_id'              => ['numeric'],
            'category'                          => ['string'],
            'type'                              => ['string'],
            'fin_loc'                           => ['string'],
            'fac_supv'                          => ['string'],
            'wo_status_type_id'                 => ['numeric'],
            'via_type_id'                       => ['numeric'],
            'pickup_id'                         => ['numeric'],
            'shop_address_id'                   => ['numeric'],
            'acknowledged'                      => ['string'],
            'invoice_status_type_id'            => ['numeric'],
            'bill_status_type_id'               => ['numeric'],
            'project_manager_person_id'         => ['numeric'],
            'received_date_interval'            => ['string'],
            'expected_completion_date_interval' => ['string'],

            'vendor_to_assign'                        => ['present', 'array'],
            'vendor_to_assign.*.id'                   => ['numeric', 'nullable'],
            'vendor_to_assign.*.lpwo_id'              => ['required', 'numeric'],
            'vendor_to_assign.*.person_id'            => ['required', 'numeric', 'exists:person,person_id'],
            'vendor_to_assign.*.status_type_id'       => ['numeric', 'exists:type,type_id'],
            'vendor_to_assign.*.type'                 => ['string', 'in:work,quote,recall', 'nullable'],
            'vendor_to_assign.*.estimated_time'       => ['string', 'nullable'],
            'vendor_to_assign.*.send_past_due_notice' => ['boolean'],
            'vendor_to_assign.*.qb_info'              => ['string', 'nullable'],
            'vendor_to_assign.*.is_deleted'           => ['boolean'],

            'task_to_create'                       => ['present', 'array'],
            'task_to_create.*.id'                  => ['numeric', 'nullable'],
            'task_to_create.*.lpwo_id'             => ['required', 'numeric'],
            'task_to_create.*.person_id'           => ['numeric', 'exists:person,person_id', 'nullable'],
            'task_to_create.*.assigned_to'         => ['numeric', 'exists:person,person_id', 'nullable'],
            'task_to_create.*.time_start_interval' => ['string', 'nullable'],
            'task_to_create.*.duration'            => ['string', 'nullable'],
            'task_to_create.*.topic'               => ['string', 'nullable'],
            'task_to_create.*.description'         => ['string', 'nullable'],
            'task_to_create.*.type'                => ['string', 'in:task,wo_task', 'nullable'],
            'task_to_create.*.is_hot'              => ['boolean'],
            'task_to_create.*.is_deleted'          => ['boolean'],

            'is_recurring'                       => ['boolean'],
            'work_order_repeat'                  => ['required_if:template_recurring,1', 'array'],
            'work_order_repeat.id'               => ['numeric', 'nullable'],
            'work_order_repeat.next_date'        => ['string'],
            'work_order_repeat.interval_value'   => ['numeric'],
            'work_order_repeat.interval_keyword' => ['string'],
            'work_order_repeat.days_in_advance'  => ['string']
        ];

        if (config('app.crm_user') != 'fs') {
            $rules['priority'] = ['string'];
            $rules['trade'] = ['string'];
            $rules['trade_type_id'] = ['numeric'];
            $rules['quote_status_type_id'] = ['numeric'];
            $rules['wo_type_id'] = ['numeric'];
        } else {
            $rules['invoice_number'] = ['string'];
            $rules['phone'] = ['invoice_number'];
        }
        
        return $rules;
    }
}
