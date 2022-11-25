<?php

namespace App\Modules\Person\Http\Requests;

class CompanyRequest extends PersonRequest
{
    protected $selectedKind = 'company';
}
