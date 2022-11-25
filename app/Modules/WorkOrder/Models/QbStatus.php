<?php

namespace App\Modules\WorkOrder\Models;

class QbStatus
{
    const MISSING = 0; // qb_info is not filled
    const NOT_SENT = 1; // qb_info is filled but qb_ref == ''
    const SENT = 2; // qb_ref != ''
}
