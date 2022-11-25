<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\WorkOrder\Models\WorkOrderTruckStock;
use Illuminate\Container\Container;

/**
 * MonthlyInventory repository class
 */
class WorkOrderTruckStockRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [

    ];

    /**
     * Repository constructor
     *
     * @param  Container  $app
     * @param  WorkOrderTruckStock  $workOrderTruckStock
     */
    public function __construct(Container $app, WorkOrderTruckStock $workOrderTruckStock)
    {
        parent::__construct($app, $workOrderTruckStock);
    }
}
