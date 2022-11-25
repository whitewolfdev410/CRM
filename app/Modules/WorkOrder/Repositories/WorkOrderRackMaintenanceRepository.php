<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\WorkOrder\Models\WorkOrderRackMaintenance;
use Illuminate\Container\Container;

/**
 * MonthlyInventory repository class
 */
class WorkOrderRackMaintenanceRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [

    ];

    /**
     * Repository constructor
     *
     * @param Container                $app
     * @param WorkOrderRackMaintenance $workOrderRackMaintenance
     */
    public function __construct(Container $app, WorkOrderRackMaintenance $workOrderRackMaintenance)
    {
        parent::__construct($app, $workOrderRackMaintenance);
    }
}
