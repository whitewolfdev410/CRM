<?php

namespace App\Modules\WorkOrder\Services;

/**
 * Class WorkOrderDataServiceContract
 *
 * Get necessary data for WorkOrder module (used in selects or displaying colour
 * boxes)
 *
 * @package App\Modules\WorkOrder\Services
 */
interface WorkOrderDataServiceContract
{
    /**
     * Get all data
     *
     * @return array
     */
    public function getAll();

    /**
     * Get data only needed for displaying work order
     *
     * @return array
     */
    public function getValues();
}
