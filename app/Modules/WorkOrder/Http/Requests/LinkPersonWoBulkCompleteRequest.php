<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Support\Facades\App;
use App\Modules\WorkOrder\Services\LinkPersonWoCompleteService;

class LinkPersonWoBulkCompleteRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'vendors_ids' => ['required', 'array'],
            'vendors_ids.*' => ['exists:link_person_wo,link_person_wo_id'],
        ];
    }
}
