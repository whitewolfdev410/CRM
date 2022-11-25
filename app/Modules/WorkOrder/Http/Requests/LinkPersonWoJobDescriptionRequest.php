<?php

namespace App\Modules\WorkOrder\Http\Requests;

use Illuminate\Support\Facades\App;
use App\Core\Crm;
use App\Http\Requests\Request;

class LinkPersonWoJobDescriptionRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'qb_info'              => ['present'],
            'special_type'         => ['required', 'in:2hr_min,none'],
            'estimated_time'       => ['present', 'date_format:H:i:s'],
            'send_past_due_notice' => ['required', 'in:0,1'],
            'issue'                => ['required', 'in:0,1'],
        ];

        /** @var Crm $crm */
        $crm = app(Crm::class);

        // for GFS extra fields will be also used
        if ($crm->is('gfs')) {
            $rules['qb_nte'] = ['present', 'numeric', 'max:999999.99'];
            $rules['qb_ecd'] = ['present', 'date_format:Y-m-d'];
            $rules['completed_pictures_received'] = ['required', 'in:yes,no'];
            $rules['completed_pictures_required'] = ['required', 'in:yes,no'];
        }

        if ($crm->is('bfc')) {
            $rules['assigned_person_id'] = ['present', 'numeric'];
            $rules['tech_status_type_id'] = ['present', 'numeric'];
            $rules['qb_nte'] = ['present', 'numeric', 'max:999999.99'];
            $rules['qb_ecd'] = ['present', 'date_format:Y-m-d'];
            $rules['scheduled_date_simple'] = ['present', 'date'];
            $rules['is_ghost'] = ['in:0,1'];
        }

        if ($crm->is('mighty')) {
            $rules['assets_enabled'] = ['present'];
        }

        return $rules;
    }
}
