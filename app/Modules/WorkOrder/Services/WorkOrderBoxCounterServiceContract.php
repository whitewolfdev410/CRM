<?php

namespace App\Modules\WorkOrder\Services;

/**
 * Class WorkOrderBoxCounterServiceContract
 *
 * Generate count items data for colour boxes
 *
 * @package App\Modules\WorkOrder\Services
 */
interface WorkOrderBoxCounterServiceContract
{
    /**
     * Generate items count for all boxes
     *
     * @return array
     */
    public function generate();
}
