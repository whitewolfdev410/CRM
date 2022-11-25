<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\WorkOrder\Exceptions\WoInvoicedCannotAddVendorsException;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;

class WorkOrderSearchVendorsService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var WorkOrderRepository
     */
    protected $woRepo;

    /**
     * @var PersonRepository
     */
    protected $personRepo;

    /**
     * Initialize class
     *
     * @param Container $app
     * @param WorkOrderRepository $woRepo
     * @param PersonRepository $personRepo
     */
    public function __construct(
        Container $app,
        WorkOrderRepository $woRepo,
        PersonRepository $personRepo
    ) {
        $this->app = $app;
        $this->woRepo = $woRepo;
        $this->personRepo = $personRepo;
    }

    /**
     * Search vendors that might be assigned to given work order
     *
     * @param int $workOrderId
     * @param int $onPage
     * @param string|null $type
     * @param string|null $name
     * @param int $regionId
     * @param int $tradeId
     * @param string $jobType
     *
     * @return LengthAwarePaginator
     * @throws WoInvoicedCannotAddVendorsException
     */
    public function search(
        $workOrderId,
        $onPage,
        $type,
        $name,
        $regionId,
        $tradeId,
        $jobType
    ) {
        /** @var WorkOrder $wo */
        $wo = $this->woRepo->find($workOrderId);

        // work order already invoiced - throw exception
        if ($wo->getInvoiceStatusTypeId() ==
            getTypeIdByKey('wo_billing_status.invoiced')
        ) {
            /** @var WoInvoicedCannotAddVendorsException $exp */
            $exp = $this->app->make(WoInvoicedCannotAddVendorsException::class);
            throw $exp;
        }

        // get vendors
        return $this->personRepo->getVendorsToAssignForWo(
            $workOrderId,
            $onPage,
            $type,
            $name,
            $regionId,
            $tradeId,
            $jobType
        );
    }
}
