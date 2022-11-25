<?php

namespace App\Modules\WorkOrder\Events;

use App\Modules\WorkOrder\Models\WorkOrder;

/**
 * Fired when a work order has been imported from an external service, email, etc.
 */
class WorkOrderImported
{
    public $workOrder;
    public $source;
    public $sourceData;

    /**
     * Constructor
     * @param WorkOrder $workOrder
     * @param string    $source
     * @param array     $sourceData
     */
    public function __construct(WorkOrder $workOrder, $source, $sourceData = [])
    {
        $this->workOrder = $workOrder;
        $this->source = $source;
        $this->sourceData = $sourceData;
    }
}
