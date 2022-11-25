<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;

class LinkPersonWoStoreBulkRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        return [
            'work_order_id' => [
                'required',
                'exists:work_order,work_order_id',
            ],
            'type' => ['required', 'in:work,quote,recall'],
            'vendor' => ['required'],
            'recall_link_person_wo_id' => ['present'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getFormatterRules()
    {
        return [
            'work_order_id' => 'int',
            'recall_link_person_wo_id' => 'int',
        ];
    }
}
