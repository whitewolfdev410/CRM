<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\MsDynamics\Services\MsDynamicsService;
use App\Modules\WorkOrder\Http\Requests\LaborPricingRequest;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkLaborFileRequiredRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Contracts\Container\Container;

class LinkLaborWoService
{
    /**
     * @var Container
     */
    protected $app;
    /**
     * @var LinkLaborFileRequiredRepository
     */
    protected $linkLaborFileRequiredRepository;

    /**
     * @var WorkOrderRepository
     */
    protected $workOrderRepository;

    /**
     * Initialize class
     *
     * @param Container $app
     * @param LinkLaborFileRequiredRepository $linkLaborFileRequiredRepository
     * @param WorkOrderRepository $workOrderRepository
     */
    public function __construct(
        Container $app,
        LinkLaborFileRequiredRepository $linkLaborFileRequiredRepository,
        WorkOrderRepository $workOrderRepository
    ) {
        $this->app = $app;
        $this->linkLaborFileRequiredRepository = $linkLaborFileRequiredRepository;
        $this->workOrderRepository = $workOrderRepository;
    }

    /**
     * @param LaborPricingRequest $laborPricingRequest
     *
     * @return array
     */
    public function getPricing(LaborPricingRequest $laborPricingRequest)
    {
        /** @var MsDynamicsService $msDynamicsService */
        $msDynamicsService = app(MsDynamicsService::class);

        $pricing = [];
        $laborTypes = $msDynamicsService->getLaborTypes();
        
        /** @var WorkOrder $workOrder */
        $workOrder = $this->workOrderRepository->findSoft($laborPricingRequest->work_order_id);
        
        if ($workOrder) {
            $siteId = $workOrder->fin_loc;
            $pricing = $msDynamicsService->getPricingBySiteIdsAndInventoryIds([$siteId], array_keys($laborTypes));

            if (isset($pricing[$siteId])) {
                $pricing = $pricing[$siteId];
                $pricing = array_map('floatval', $pricing);
            }
        }

        foreach ($laborTypes as $inventoryId => $name) {
            $inventoryId = trim($inventoryId);
            
            if (!isset($pricing[$inventoryId])) {
                $pricing[$inventoryId] = 0;
            }
        }
        
        return $pricing;
    }
}
