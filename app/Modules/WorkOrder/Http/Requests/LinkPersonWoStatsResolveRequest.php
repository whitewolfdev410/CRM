<?php

namespace App\Modules\WorkOrder\Http\Requests;

use App\Http\Requests\Request;

class LinkPersonWoStatsResolveRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'resolution_type_id' => ['required'],
            'resolution_memo'    => [],
        ];

        $data = $this->all();
        if (!empty($data['resolution_type_id'])) {
            $otherType = getTypeIdByKey('link_stats_resolution.other');
            if ((int)$data['resolution_type_id'] === (int)$otherType) {
                $rules['resolution_memo'] = 'required';
            }
        }

        return $rules;
    }
}
