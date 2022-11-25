<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Support\Facades\App;
use App\Modules\WorkOrder\Services\LinkPersonWoCompleteService;

class LinkPersonWoCompleteRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules =  [
            'is_mobile' => ['in:0,1'],
            'completion_code' => ['max:24'],
        ];

        // if id is filled (and not 0) we verify if completion code is required
        $lpWoId = $this->id;
        if ($lpWoId) {
            /** @var LinkPersonWoCompleteService $service */
            $service = App::make(LinkPersonWoCompleteService::class);
            if ($service->isCompletionCodeRequired($lpWoId)) {
                $rules['completion_code'][] = 'required';
            }
        }

        return $rules;
    }
}
