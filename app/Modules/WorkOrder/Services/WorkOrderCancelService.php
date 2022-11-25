<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Trans;
use App\Modules\Activity\Repositories\ActivityRepository;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;

class WorkOrderCancelService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var WorkOrderRepository
     */
    protected $workOrderRepository;

    /**
     * @var ActivityRepository
     */
    protected $activityRepository;

    /**
     * @var Trans
     */
    protected $trans;

    /**
     * @var LinkPersonWoRepository
     */
    protected $linkPersonWoRepository;

    /**
     * WorkOrderCancelService constructor.
     *
     * @param Container $app
     * @param WorkOrderRepository $workOrderRepository
     * @param LinkPersonWoRepository $linkPersonWoRepository
     * @param ActivityRepository $activityRepository
     * @param Trans $trans
     */
    public function __construct(
        Container $app,
        WorkOrderRepository $workOrderRepository,
        LinkPersonWoRepository $linkPersonWoRepository,
        ActivityRepository $activityRepository,
        Trans $trans
    ) {
        $this->app = $app;
        $this->workOrderRepository = $workOrderRepository;
        $this->linkPersonWoRepository = $linkPersonWoRepository;
        $this->activityRepository = $activityRepository;
        $this->trans = $trans;
    }

    /**
     * Cancel work order identified by $id
     *
     * @param int $id
     * @param int $invoiceStatusTypeId
     * @param int $billStatusTypeId
     * @param int $cancelReasonTypeId
     * @param string $additionalInformation
     *
     * @return WorkOrder
     */
    public function cancel(
        $id,
        $invoiceStatusTypeId,
        $billStatusTypeId,
        $cancelReasonTypeId,
        $additionalInformation
    ) {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->workOrderRepository->find($id);

        DB::transaction(function () use (
            $workOrder,
            $invoiceStatusTypeId,
            $billStatusTypeId,
            $cancelReasonTypeId,
            $additionalInformation
        ) {
            // get vendors assigned to this work order (active only)
            $vendors = $this->linkPersonWoRepository
                ->getAssignedVendors($workOrder->getId());

            /** @var LinkPersonWoCancelService $service */
            $service = $this->app->make(LinkPersonWoCancelService::class);

            // @todo why we set here fixed cancel reason as we might have one
            // from request ($cancelReasonTypeId) ?
            $lpWoCancelReasonId =
                getTypeIdByKey('vendor_cancel_reason.customer_canceled');

            // cancel each vendor
            /** @var LinkPersonWo $vendor */
            foreach ($vendors as $vendor) {
                $service->cancel($vendor, $lpWoCancelReasonId, false);
            }

            // now cancel work order
            $this->workOrderRepository->cancel(
                $workOrder,
                $invoiceStatusTypeId,
                $billStatusTypeId,
                $cancelReasonTypeId
            );

            // set activity data and save it
            $description =
                $this->trans->get(
                    'work_order.canceled.activity_description',
                    ['reason' => getTypeValueById($cancelReasonTypeId)]
                );

            if (trim($additionalInformation) != '') {
                $description .= '<br/>' .
                    $this->trans->get('additional_information') . ': ' .
                    trim($additionalInformation);
            }

            $this->activityRepository->add(
                'work_order',
                $workOrder->getId(),
                $description
            );
        });

        return $this->workOrderRepository->find($id);
    }
}
