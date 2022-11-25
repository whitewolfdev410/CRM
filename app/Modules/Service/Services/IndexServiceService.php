<?php

namespace App\Modules\Service\Services;

use App\Modules\Service\Exceptions\NotAllowedParametersMixException;
use App\Modules\Service\Repositories\ServiceRepository;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class IndexServiceService
{
    /**
     * @var Container
     */
    private $app;
    /**
     * @var ServiceRepository
     */
    private $serviceRepo;

    /**
     * Initialize class
     *
     * @param Container         $app
     * @param ServiceRepository $serviceRepo
     */
    public function __construct(Container $app, ServiceRepository $serviceRepo)
    {
        $this->app = $app;
        $this->serviceRepo = $serviceRepo;
    }

    /**
     * Get paginated services
     *
     * @param int     $onPage
     * @param Request $request
     *
     * @return LengthAwarePaginator
     * @throws NotAllowedParametersMixException
     * @throws \App\Core\Exceptions\InvalidTypeKeyException
     */
    public function get($onPage, $request)
    {
        $billingCompanyPersonId = $request->input('billing_company_person_id', null);
        $companyPersonId = $request->input('company_person_id', null);
        $workOrderId = $request->input('work_order_id', null) ?? $request->input('msrp_work_order_id', null);
        $linkPersonWoId = $request->input('msrp_link_person_wo_id', null);

        $data = $this->serviceRepo->paginate($onPage);

        if (is_array($data)) {
            /* If data is array detailed parameter is used. At the moment we don't allow to do this, so let's throw exception */
            $exception = $this->app->make(NotAllowedParametersMixException::class);
            $exception->setData([
                'parameters' => [
                    'detailed',
                    'msrp_link_person_wo_id',
                ],
            ]);
            throw $exception;
        }

        if (!empty($billingCompanyPersonId) || !empty($companyPersonId)) {
            $this->app->make(ServiceMsrpChangerService::class)->modifyMultipleForCompany(
                $data->items(),
                $billingCompanyPersonId,
                $companyPersonId,
                Carbon::now()
            );
        } elseif (!empty($workOrderId) || !empty($linkPersonWoId)) {
            if (!empty($linkPersonWoId)) {
                $workOrder = $this->getWorkOrder($linkPersonWoId);
            } else {
                $workOrder = $this->app->make(WorkOrderRepository::class)->findSoft($workOrderId);
            }

            if ($workOrder) {
                // @todo shouldn't we set prices to 0 if no wo found as in IndexServiceService ?

                $this->app->make(ServiceMsrpChangerService::class)->modifyMultiple(
                    $data->items(),
                    $workOrder,
                    Carbon::now()
                );
            }
        } else {
        }

        return $data;
    }

    /**
     * Get work order
     *
     * @param int $lpWoId
     *
     * @return WorkOrder
     */
    public function getWorkOrder($lpWoId)
    {
        /** @var  LinkPersonWoRepository $lpwoRepo */
        $lpWoRepo = $this->app->make(LinkPersonWoRepository::class);
        /** @var LinkPersonWo $lpWo */
        $lpWo = $lpWoRepo->findSoft($lpWoId);

        if (!$lpWo) {
            return null;
        }

        /** @var WorkOrderRepository $workOrderRepo */
        $workOrderRepo = $this->app->make(WorkOrderRepository::class);

        return $workOrderRepo->findSoft($lpWo->getWorkOrderId());
    }
}
