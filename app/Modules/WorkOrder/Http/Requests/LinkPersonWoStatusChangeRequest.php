<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;
use App\Modules\WorkOrder\Services\LinkPersonWoCompleteService;
use Illuminate\Support\Facades\App;

class LinkPersonWoStatusChangeRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = $this->getRules();

//        // if id is filled (and not 0) we verify if completion code is required
//        $lpWoId = $this->id;
//        if ($lpWoId) {
//            /** @var LinkPersonWoCompleteService $service */
//            $service = App::make(LinkPersonWoCompleteService::class);
//            if ($service->isCompletionCodeRequired($lpWoId)) {
//                $rules['completion_code'][] = 'required';
//            }
//        }

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
            'status_id' => [
                'required_without:status_label',
                'exists:type,type_id',
            ],
            'status_label' => [
                'required_without:status_id'
            ],
            'reason_type_id' => [
                'required_if:status_label,canceled',
                'required_if:status_id,' . getTypeIdByKey('wo_vendor_status.canceled'),
                'exists:type,type_id',
            ],
            'no_invoice_certify' => [
                'in:0,1',
            ],
            'assign_to_person_id' => [
                'exists:person,person_id',
            ],
            'job_type' => [
                'required_with:assign_to_person_id',
                'in:' . implode(',', ['work', 'quote', 'recall']),
            ],
            'recall_link_person_wo_id' => [
                'exists:link_person_wo,link_person_wo_id',
            ],
            'additional_information' => [
                
            ],
            'completion_code' => ['max:24'],
            'force' => ['in:0,1'],
        ];
    }
}
