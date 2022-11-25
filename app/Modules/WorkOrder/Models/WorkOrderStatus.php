<?php

namespace App\Modules\WorkOrder\Models;

/**
 * Class WorkOrderStatus
 *
 * This class holds status for vendor_status (type_id column in work_order
 * table)
 *
 * @package App\Modules\WorkOrder\Models
 */
class WorkOrderStatus
{
    const LOCKED = 1;
    const ISSUED = 2;
    const CONFIRMED = 3;
    const IN_PROGRESS = 4;
    const IN_PROGRESS_AND_HOLD = 5;
    const COMPLETED = 6;
    const CANCELED = 7;
    const ASSIGNED = 8;
}
