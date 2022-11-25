<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Trans;
use App\Modules\Activity\Models\Activity;
use App\Modules\Activity\Repositories\ActivityRepository;
use App\Modules\WorkOrder\Exceptions\LpWoNotAssignedException;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\WorkOrder\Exceptions\LpWoAlreadyConfirmedException;
use App\Modules\WorkOrder\Exceptions\LpWoCurrentlyConfirmedException;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

class LinkPersonWoConfirmService
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
     * Initialize class
     *
     * @param Container              $app
     * @param LinkPersonWoRepository $lpWoRepo
     * @param Trans                  $trans
     */
    public function __construct(
        Container $app,
        LinkPersonWoRepository $lpWoRepo,
        Trans $trans
    ) {
        $this->app = $app;
        $this->lpWoRepo = $lpWoRepo;
        $this->trans = $trans;
    }

    /**
     * Confirm given link person wo.
     *
     * @param int           $lpWoId
     * @param string        $via
     * @param Carbon\Carbon $confirmedAt
     * @param boolean $updateWorkOrderStatus
     *
     * @return LinkPersonWo
     * @throws LpWoNotAssignedException
     */
    public function confirm(
        $lpWoId,
        $via,
        $confirmedAt = null,
        $updateWorkOrderStatus = true
    ) {
        $lpWoId = (int) $lpWoId;
        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpWoRepo->find($lpWoId);

        // if person does not match, cannot change status to confirmed
        if ($lpWo->getPersonId() != getCurrentPersonId()) {
            $exp = $this->app->make(LpWoNotAssignedException::class);
            $exp->setData([
                'link_person_wo_id' => $lpWoId,
                'person_id' => getCurrentPersonId(),
            ]);
            throw $exp;
        }

        return $this->makeConfirmation(
            $lpWo,
            $updateWorkOrderStatus,
            false,
            $via,
            null,
            $confirmedAt
        );
    }

    /**
     * Makes confirmation for given link person wo. Depending on $force it
     * launch either force method or standard method
     *
     * @param LinkPersonWo|int   $lpWo
     * @param bool|false         $updateWoStatus
     * @param bool|false         $force
     * @param bool|false         $via
     * @param string|null        $updateStatusReason
     * @param Carbon\Carbon|null $confirmedAt
     *
     * @return LinkPersonWo
     */
    public function makeConfirmation(
        $lpWo,
        $updateWoStatus = true,
        $force = false,
        $via = false,
        $updateStatusReason = null,
        $confirmedAt = null
    ) {
        // find link person wo in case we get id otherwise we already have it
        if (!$lpWo instanceof LinkPersonWo) {
            $lpWo = $this->lpWoRepo->find($lpWo);
        }

        // depending if it's forced or not we launch different way of confirming
        if ($force) {
            $lpWo =
                $this->handleForcedConfirmation(
                    $lpWo,
                    $updateWoStatus,
                    $via,
                    $updateStatusReason,
                    $confirmedAt
                );
        } else {
            $lpWo = $this->handleUserConfirmation(
                $lpWo,
                $updateWoStatus,
                $via,
                $updateStatusReason,
                $confirmedAt
            );
        }

        // return modified link person wo
        return $lpWo;
    }

    /**
     * Update lpwo status to confirmed in case if it's forced
     *
     * @param LinkPersonWo       $lpWo
     * @param bool               $updateWoStatus
     * @param bool|string        $via
     * @param string|null        $updateStatusReason
     * @param Carbon\Carbon|null $confirmedAt
     *
     * @return LinkPersonWo
     */
    protected function handleForcedConfirmation(
        LinkPersonWo $lpWo,
        $updateWoStatus,
        $via,
        $updateStatusReason,
        $confirmedAt = null
    ) {
        // if forced we only confirm this lpwo - we don't verify if it might be
        // confirmed and what's current status of this lpwo before confirming

        // confirm this lpwo
        return $this->confirmLpWo(
            $lpWo,
            $updateWoStatus,
            $via,
            $updateStatusReason,
            $confirmedAt
        );
    }

    /**
     * Update lpwo status to confirmed in case if it's user confirmation (not
     * forced)
     *
     * @param LinkPersonWo       $lpWo
     * @param bool               $updateWoStatus
     * @param bool|string        $via
     * @param string|null        $updateStatusReason
     * @param Carbon\Carbon|null $confirmedAt
     *
     * @return LinkPersonWo
     * @throws mixed
     */
    protected function handleUserConfirmation(
        LinkPersonWo $lpWo,
        $updateWoStatus,
        $via,
        $updateStatusReason,
        $confirmedAt = null
    ) {
        // verify if should update
        $canConfirm = $this->verifyIfLpWoCanBeConfirmed($lpWo);

        // if should not update throw exception
        if (!$canConfirm) {
            $statusTypeId = $lpWo->getStatusTypeId();
            if (in_array($statusTypeId, [
                getTypeIdByKey('wo_vendor_status.confirmed'),
                getTypeIdByKey('wo_quote_status.rfq_confirmed'),
            ])) {
                // has at the moment (RFQ)CONFIRMED status
                $exp = $this->app->make(LpWoCurrentlyConfirmedException::class);
            } else {
                // has other status than (RFQ)CONFIRMED but it means it already
                // had (RFQ)CONFIRMED status in PAST
                $exp = $this->app->make(LpWoAlreadyConfirmedException::class);
            }
            // set exception data to give details
            $exp->setData([
                'link_person_wo_id' => $lpWo->getId(),
                'person_id' => getCurrentPersonId(),
                'status_type_id' => $statusTypeId,
                'status_type_name' => getTypeValueById($statusTypeId),
            ]);
            throw $exp;
        }

        // confirm this lpwo
        return $this->confirmLpWo(
            $lpWo,
            $updateWoStatus,
            $via,
            $updateStatusReason,
            $confirmedAt
        );
    }

    /**
     * Confirm link person wo and update work order (if needed)
     *
     * @param LinkPersonWo       $lpWo
     * @param bool               $updateWoStatus
     * @param bool|string        $via
     * @param string             $updateStatusReason
     * @param Carbon\Carbon|null $confirmedAt
     *
     * @return LinkPersonWo
     */
    protected function confirmLpWo(
        LinkPersonWo $lpWo,
        $updateWoStatus,
        $via,
        $updateStatusReason,
        $confirmedAt = null
    ) {
        // set valid status depending of type
        if ($lpWo->getType() == 'quote') {
            $lpWo->status_type_id =
                getTypeIdByKey('wo_quote_status.rfq_confirmed');
            $updateWoStatus = false;
        } else {
            $lpWo->status_type_id =
                getTypeIdByKey('wo_vendor_status.confirmed');
        }

        // set confirmed date to current date
        $lpWo->confirmed_date = $confirmedAt
            ? $confirmedAt
            : Carbon::now()->format('Y-m-d H:i:s');

        // now run actions in transaction
        DB::transaction(function () use (
            $lpWo,
            $updateStatusReason,
            $updateWoStatus,
            $via
        ) {
            // if no priority, set new priority
            if (empty($lpWo->getPriority())) {
                $lpWo->priority =
                    $this->lpWoRepo->getNewPriorityWithUpdateInProgress($lpWo->getPersonId());
            }

            // save modified link person wo
            $lpWo->save();

            /** @var ActivityRepository $activityRepo */
            $activityRepo = $this->app->make(ActivityRepository::class);

            // add activity
            $activityRepo->add(
                'work_order',
                $lpWo->getWorkOrderId(),
                $this->trans->get('lpwo_confirm.changed_to_confirmed', [
                    'person' => $this->getPersonName($lpWo->getPersonId()),
                    'type' => empty(getCurrentPersonId()) ? '' : ' manually',
                    'via' => ($via == false ? '' : ' '.
                        $this->trans->get('via').
                        ' '.$via),
                    'reason' => $this->getUpdateStatusReason($updateStatusReason),
                ]),
                '',
                getCurrentPersonId(),
                0,
                (($via ==
                false) ? Activity::DIRECTION_INT : Activity::DIRECTION_OUT)
            );

            // if required, update also work order status
            if ($updateWoStatus) {
                /** @var WorkOrderStatusService $woStatusService */
                $woStatusService =
                    $this->app->make(WorkOrderStatusService::class);
                $woStatusService->update($lpWo->getWorkOrderId());
            }
        });

        return $lpWo;
    }

    /**
     * Get person name
     *
     * @param int $personId
     *
     * @return null|string
     */
    protected function getPersonName($personId)
    {
        /** @var PersonRepository $personRepo */
        $personRepo = $this->app->make(PersonRepository::class);

        return $personRepo->getPersonName($personId);
    }

    /**
     * Verify if link person wo should be updated to confirmed
     *
     * @param  LinkPersonWo $lpWo
     * @return bool
     */
    protected function verifyIfLpWoCanBeConfirmed(LinkPersonWo $lpWo)
    {
        $canBeConfirmed = false;
        if ($lpWo->getType() == 'quote') {
            if (in_array($lpWo->getStatusTypeId(), [
                getTypeIdByKey('wo_quote_status.rfq_issued'),
                getTypeIdByKey('wo_quote_status.rfq_assigned'),
            ])) {
                $canBeConfirmed = true;
            }
        } elseif (in_array($lpWo->getStatusTypeId(), [
            getTypeIdByKey('wo_vendor_status.issued'),
            getTypeIdByKey('wo_vendor_status.assigned'),
        ])) {
            $canBeConfirmed = true;
        }

        return $canBeConfirmed;
    }

    /**
     * Get update status reason
     *
     * @param string $updateStatusReason
     *
     * @return string
     */
    protected function getUpdateStatusReason($updateStatusReason)
    {
        return ($updateStatusReason === null ? '' :
            "\n{$this->trans->get('reason')}: ".$updateStatusReason);
    }
}
