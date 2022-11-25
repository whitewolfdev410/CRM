<?php

namespace App\Modules\WorkOrder\Http\Requests\PrintPdf;

use App\Http\Requests\Request;

class GeneratePdfRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        return [
            'is_completed' => ['in:0,1'],
            'record_id' => ['required_with:table_name', 'integer'],
            'table_name' => ['required_with:record_id'],
            'attach_files' => ['array'],
            'show_footer' => ['in:0,-1,1'],
            'show_footer_2' => ['in:0,1,2'], // @TODO remove when replaced with document_sections on client pdf-s methods
            'document_sections' => ['array'],
            'print' => ['in:0,1'],
            'download' => ['in:0,1'],
        ];
    }
}
