<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Trans;
use App\Modules\Activity\Repositories\ActivityRepository;
use App\Modules\Person\Models\Person;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\WorkOrder\Exceptions\LpWoCurrentlyCanceledException;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

class LinkPersonWoCancelService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var LinkPersonWoRepository
     */
    protected $lpWoRepo;

    /**
     * @var Trans
     */
    protected $trans;

    /**
     * @var ActivityRepository
     */
    protected $activityRepository;

    /**
     * @var PersonRepository
     */
    protected $personRepository;

    /**
     * @var WorkOrderRepository
     */
    protected $woRepo;

    /**
     * LinkPersonWoCancelService constructor.
     *
     * @param Container $app
     * @param LinkPersonWoRepository $lpWoRepo
     * @param Trans $trans
     * @param ActivityRepository $activityRepository
     * @param PersonRepository $personRepository
     * @param WorkOrderRepository $woRepository
     */
    public function __construct(
        Container $app,
        LinkPersonWoRepository $lpWoRepo,
        Trans $trans,
        ActivityRepository $activityRepository,
        PersonRepository $personRepository,
        WorkOrderRepository $woRepository
    ) {
        $this->app = $app;
        $this->lpWoRepo = $lpWoRepo;
        $this->trans = $trans;
        $this->activityRepository = $activityRepository;
        $this->personRepository = $personRepository;
        $this->woRepo = $woRepository;
    }

    /**
     * Run whole procedure of lpwo cancel
     *
     * @param LinkPersonWo $lpWo
     * @param int $cancelReasonTypeId
     * @param int $noInvoiceCertify
     * @param int $assignToPersonId
     * @param string $jobType
     * @param int $recallLinkPersonWoId
     * @param string $additionalInformation
     *
     * @return LinkPersonWo
     */
    public function run(
        LinkPersonWo $lpWo,
        $cancelReasonTypeId,
        $noInvoiceCertify,
        $assignToPersonId,
        $jobType,
        $recallLinkPersonWoId,
        $additionalInformation
    ) {
        DB::transaction(function () use (
            &$lpWo,
            $cancelReasonTypeId,
            $noInvoiceCertify,
            $assignToPersonId,
            $jobType,
            $recallLinkPersonWoId,
            $additionalInformation
        ) {

            // cancel link person wo
            $lpWo = $this->cancel($lpWo, $cancelReasonTypeId);

            // change work order invoice status
            if ($noInvoiceCertify) {
                $this->woRepo->updateInvoiceStatus(
                    $lpWo->getWorkOrderId(),
                    getTypeIdByKey('wo_billing_status.no_charge_no_work')
                );
            }

            // set activity description
            $description =
                $this->trans->get(
                    'lpwo.canceled.activity_description',
                    [
                        'name' => $this->personRepository->getPersonName($lpWo->getPersonId()),
                        'reason' => getTypeValueById($cancelReasonTypeId),
                    ]
                );

            // if other person should be assigned to work order
            if ($assignToPersonId) {
                $recallLinkPersonWoId = (int)$recallLinkPersonWoId;
                /** @var Person $assignedPerson */
                $assignedPerson =
                    $this->personRepository->findSoft(
                        $assignToPersonId,
                        ['kind']
                    );

                // assign this person to work order
                $this->lpWoRepo->addSingleVendorToWorkOrder(
                    $lpWo->getWorkOrderId(),
                    $assignToPersonId,
                    $assignedPerson->getKind(),
                    $jobType,
                    $recallLinkPersonWoId
                );

                // append info to activity description
                // @todo shouldn't whitespace be added at the beginning?
                $description .= $this->trans->get('lpwo_assign_vendor_to_wo.success');
            }

            // add to activity description additional information
            if (trim($additionalInformation) != '') {
                $description .= '<br/>' .
                    $this->trans->get('additional_information') . ': ' .
                    trim($additionalInformation);
            }

            // add activity
            $this->activityRepository->add(
                'work_order',
                $lpWo->getWorkOrderId(),
                $description
            );
        });
        
        return $lpWo;
    }

    /**
     * Cancel link person wo
     *
     * @param LinkPersonWo $lpWo
     * @param int $cancelReasonTypeId
     * @param bool $updateWoStatus
     * @param int|null $updateStatusReason
     * @return LinkPersonWo
     * @throws mixed|object
     */
    public function cancel(
        LinkPersonWo $lpWo,
        $cancelReasonTypeId,
        $updateWoStatus = true,
        $updateStatusReason = null
    ) {
        // calculate valid cancel status
        $canceledStatusTypeId = ($lpWo->getType() == 'quote') ?
            getTypeIdByKey('wo_quote_status.canceled') :
            getTypeIdByKey('wo_vendor_status.canceled');

        // if already canceled we won't change again
        if ($canceledStatusTypeId == $lpWo->getStatusTypeId()) {
            throw $this->app->make(LpWoCurrentlyCanceledException::class);
        }

        // change status to cancel
        $lpWo = $this->lpWoRepo->cancel(
            $lpWo,
            $canceledStatusTypeId,
            $cancelReasonTypeId
        );

        // if work order should be updated, we update it
        if ($updateWoStatus) {
            /** @var WorkOrderStatusService $woService */
            $woService = $this->app->make(WorkOrderStatusService::class);
            $woService->update($lpWo->getWorkOrderId());
        }

        // @todo in old CRM there was sending mail/fax here but for conditions
        // && false was added so code was not executed - if it's needed here
        // it should be implemented (class.link_person_wo.php@cancel_work_order)
        // @todo if implementing in new CRM we could also think of sending
        // PUSH notification in this place to notify it was canceled

        // set activity data and add new activity
        $currentPersonId = getCurrentPersonId();

        $message =
            $this->personRepository->getPersonName($lpWo->getPersonId()) .
            ' - status updated ' .
            ($currentPersonId ? 'manually ' : '')
            . 'to "Cancelled"' . $updateStatusReason;

        $this->activityRepository->add(
            'work_order',
            $lpWo->getWorkOrderId(),
            $message,
            '',
            $currentPersonId
        );

        return $lpWo;
    }
}
