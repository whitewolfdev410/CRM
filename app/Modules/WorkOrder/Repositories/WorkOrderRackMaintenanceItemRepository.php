<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\WorkOrder\Models\WorkOrderRackMaintenanceItem;
use Illuminate\Container\Container;

/**
 * MonthlyInventoryItem repository class
 */
class WorkOrderRackMaintenanceItemRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [

    ];

    /**
     * Repository constructor
     *
     * @param Container                    $app
     * @param WorkOrderRackMaintenanceItem $workOrderRackMaintenanceItem
     */
    public function __construct(Container $app, WorkOrderRackMaintenanceItem $workOrderRackMaintenanceItem)
    {
        parent::__construct($app, $workOrderRackMaintenanceItem);
    }

    /**
     * @param $workOrderId
     * @param $linkPersonWoId
     *
     * @return mixed
     */
    public function getItems($workOrderId, $linkPersonWoId)
    {
        return $this->model
            ->select([
                'name',
                'start_at',
                'stop_at',
                'notification_sent'
            ])
            ->where('work_order_id', $workOrderId)
            ->where('link_person_wo_id', $linkPersonWoId)
            ->orderBy('name')
            ->get();
    }
}
