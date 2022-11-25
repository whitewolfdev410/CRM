<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;

class WorkOrderNoteUpdateRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        return [
            'work_performed' => ['required'],
        ];
    }
}
