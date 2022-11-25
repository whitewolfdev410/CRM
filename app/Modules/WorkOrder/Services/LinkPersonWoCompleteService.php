<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Exceptions\NotImplementedException;
use App\Core\Trans;
use App\Modules\Activity\Models\Activity;
use App\Modules\Activity\Repositories\ActivityRepository;
use App\Modules\CustomerSettings\Models\CustomerSettings;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsRepository;
use App\Modules\Person\Models\Person;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\PushNotification\Services\PushNotificationAdderService;
use App\Modules\PushNotification\Services\PushNotificationSenderService;
use App\Modules\WorkOrder\Exceptions\LpWoAllNotCompletedException;
use App\Modules\WorkOrder\Exceptions\LpWoCannotCompleteNoCompletionCodeException;
use App\Modules\WorkOrder\Exceptions\LpWoCannotCompleteNoCompletionDateException;
use App\Modules\WorkOrder\Exceptions\LpWoCurrentlyCompletedException;
use App\Modules\WorkOrder\Exceptions\LpWoNotAssignedException;
use App\Modules\WorkOrder\Exceptions\LpWoSupplierCannotCompleteException;
use App\Modules\WorkOrder\Exceptions\LpWoTechnicianCannotCompleteException;
use App\Modules\WorkOrder\Exceptions\LpWoVendorCannotCompleteException;
use App\Modules\WorkOrder\Http\Requests\LinkPersonWoBulkCompleteRequest;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LinkPersonWoCompleteService
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
     * @var WorkOrderRepository
     */
    protected $woRepo;

    /**
     * @var Trans
     */
    protected $trans;

    /**
     * Person technician type
     */
    const PERSON_TECHNICIAN = 'Technician';

    /**
     * Person supplier type
     */
    const PERSON_SUPPLIER = 'Supplier';

    /**
     * Person vendor type
     */
    const PERSON_VENDOR = 'Vendor';

    /**
     * Initialize class
     *
     * @param  Container  $app
     * @param  LinkPersonWoRepository  $lpWoRepo
     * @param  WorkOrderRepository  $woRepo
     * @param  Trans  $trans
     */
    public function __construct(
        Container $app,
        LinkPersonWoRepository $lpWoRepo,
        WorkOrderRepository $woRepo,
        Trans $trans
    ) {
        $this->app = $app;
        $this->lpWoRepo = $lpWoRepo;
        $this->trans = $trans;
        $this->woRepo = $woRepo;
    }

    /**
     * Complete given link person wo.
     *
     * @param  int  $lpWoId
     * @param  string  $via
     * @param  string  $completionCode
     * @param  string  $isMobile
     * @param  boolean  $force
     * @param  boolean  $updateWorkOrderStatus
     *
     * @return LinkPersonWo
     *
     * @throws Exception
     * @throws LpWoCannotCompleteNoCompletionCodeException
     * @throws LpWoNotAssignedException
     * @throws ModelNotFoundException
     */
    public function complete(
        $lpWoId,
        $via,
        $completionCode,
        $isMobile,
        $force = false,
        $updateWorkOrderStatus = true
    ) {
        $lpWoId = (int)$lpWoId;
        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpWoRepo->find($lpWoId);

        if ($lpWo->getPersonId() != getCurrentPersonId()) {
            $exp = $this->app->make(LpWoNotAssignedException::class);
            $exp->setData([
                'link_person_wo_id' => $lpWoId,
                'person_id'         => getCurrentPersonId(),
            ]);
            throw $exp;
        }

        return $this->makeCompletion(
            $lpWo,
            $completionCode,
            $isMobile,
            $updateWorkOrderStatus,
            $force,
            $via
        );
    }

    /**
     * @param  LinkPersonWoBulkCompleteRequest  $linkPersonWoBulkCompleteRequest
     *
     * @return array
     * @throws LpWoCannotCompleteNoCompletionDateException
     * @throws NotImplementedException
     */
    public function bulkComplete(LinkPersonWoBulkCompleteRequest $linkPersonWoBulkCompleteRequest)
    {
        $result = [];

        $vendorIds = $linkPersonWoBulkCompleteRequest->get('vendors_ids');

        DB::beginTransaction();

        try {
            foreach ($vendorIds as $vendorId) {
                $lpWo = LinkPersonWo::findOrFail($vendorId);

                $result[$vendorId] = $this->handleUserCompletion($lpWo, false, true, false, null);
            }

            DB::commit();

            return $result;
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Makes completion for given link person wo. Depending on $force it
     * launch either force method or standard method
     *
     * @param  LinkPersonWo|int  $lpWo
     * @param  string  $completionCode
     * @param  int  $isMobile
     * @param  bool|false  $updateWoStatus
     * @param  bool|false  $force
     * @param  bool|false  $via
     * @param  string|null  $updateStatusReason
     *
     * @return LinkPersonWo
     *
     * @throws Exception
     * @throws LpWoCannotCompleteNoCompletionCodeException
     * @throws ModelNotFoundException
     */
    public function makeCompletion(
        $lpWo,
        $completionCode,
        $isMobile,
        $updateWoStatus = true,
        $force = false,
        $via = false,
        $updateStatusReason = null
    ) {
        // find link person wo in case we get id otherwise we already have it
        if (!$lpWo instanceof LinkPersonWo) {
            /** @var LinkPersonWo $lpWo */
            $lpWo = $this->lpWoRepo->find($lpWo);
        }

        // verify if completion code is required
        list($codeRequired, $workOrder) =
            $this->isCompletionCodeRequired($lpWo, true);

        /* if code required and no code throw exception (this should be already
           handled by Request class but in case we run this as internal method
           we should be sure the completion code has been send in this case */
        if ($codeRequired && !$completionCode) {
            throw $this->app->make(LpWoCannotCompleteNoCompletionCodeException::class);
        }

        /** @var WorkOrder $workOrder */

        // start transaction
        DB::beginTransaction();
        try {
            if ($codeRequired) {
                /** @var WorkOrderRepository $woRepo */
                $woRepo = $this->app->make(WorkOrderRepository::class);
                $woRepo->updateCompletionCode($workOrder, $completionCode);
            }

            // @TODO this transaction seems not to work CRM-301


            // depending if it's forced or not we launch different way of completion
            if ($force) {
                $lpWo =
                    $this->handleForcedCompletion(
                        $lpWo,
                        $updateWoStatus,
                        $via,
                        $updateStatusReason
                    );
            } else {
                $lpWo =
                    $this->handleUserCompletion(
                        $lpWo,
                        $isMobile,
                        $updateWoStatus,
                        $via,
                        $updateStatusReason
                    );
            }

            /* verify if actual completion date is not empty and in case it is
               try to update it or log error (only for mobile via) */
            if ($this->isMobile($isMobile)) {
                $this->updateActualCompletionDate($workOrder);
            }

            // commit all changes
            DB::commit();

            // return modified link person wo
            return $lpWo;
        } catch (Exception $e) {
            // rollback transaction
            DB::rollback();

            // rethrow exception
            throw $e;
        }
    }

    /**
     * Update work order actual completion date (or log if it's not possible)
     *
     * @param  WorkOrder  $workOrder
     *
     * @throws InvalidArgumentException
     */
    protected function updateActualCompletionDate(WorkOrder $workOrder)
    {
        // get completed vendors count and total vendors count
        $woId = $workOrder->getId();
        $vendorCompletedCount = $this->lpWoRepo
            ->getAssignedVendorsCount(
                $woId,
                false,
                [getTypeIdByKey('wo_vendor_status.completed')]
            );
        $totalVendorsCount = $this->lpWoRepo->getAssignedVendorsCount($woId);

        if (isEmptyDateTime($workOrder->getActualCompletionDate()) &&
            ($vendorCompletedCount > 0) &&
            ($vendorCompletedCount == $totalVendorsCount)
        ) {
            // if actual completion date is empty and all vendors completed the job
            // we update ACD
            $this->woRepo->updateActualCompletionDate($workOrder);
        }
        /*
        // Commented at 22.02.2016 due to multiple error emails
        // as logs are monitored by runscope and rollbar
                else {
                    // update was not possible (vendors may still work of ACD was not empty)
                    $this->app->log->error("Actual completion date for Work order couldn't be updated",
                        [
                            'vendor_completed_count' => $vendorCompletedCount,
                            'total_vendors_count' => $totalVendorsCount,
                            'work_order_id' => $woId,
                            'actual_completion_date' => $workOrder->getActualCompletionDate(),
                        ]);
                }
        */
    }

    /**
     * Update lpwo status to completed in case if it's forced
     *
     * @param  LinkPersonWo  $lpWo
     * @param  bool  $updateWoStatus
     * @param  bool|string  $via
     * @param  string|null  $updateStatusReason
     *
     * @return LinkPersonWo
     *
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    protected function handleForcedCompletion(
        LinkPersonWo $lpWo,
        $updateWoStatus,
        $via,
        $updateStatusReason
    ) {
        // if forced we only complete this lpwo - we don't verify if it might be
        // completed and what's current status of this lpwo before completion

        // complete this lpwo
        return $this
            ->completeLpWo($lpWo, $updateWoStatus, $via, $updateStatusReason);
    }

    /**
     * Update lpwo status to completed in case if it's user completion (not
     * forced)
     *
     * @param  LinkPersonWo  $lpWo
     * @param  int  $isMobile
     * @param  bool  $updateWoStatus
     * @param  bool|string  $via
     * @param  string|null  $updateStatusReason
     *
     * @return LinkPersonWo
     *
     * @throws InvalidArgumentException
     * @throws LpWoCannotCompleteNoCompletionDateException
     * @throws NotImplementedException
     */
    protected function handleUserCompletion(
        LinkPersonWo $lpWo,
        $isMobile,
        $updateWoStatus,
        $via,
        $updateStatusReason
    ) {
        // verify if should update
        $canComplete = $this->verifyIfLpWoCanBeCompleted($lpWo);

        // if should not update throw exception
        if (!$canComplete) {
            $statusTypeId = $lpWo->getStatusTypeId();

            $errorClass = null;
            $personType = null;

            list($completedStatus, ) = $this->getCompletedVendorStatus($lpWo);

            if ($statusTypeId == $completedStatus) {
                // has at the moment COMPLETED status
                $errorClass = LpWoCurrentlyCompletedException::class;
            } else {
                // @todo probably for type=quote other exception should be displayed

                // cannot be completed because of current status and person type
                $personType = $this->getPersonType($lpWo->getPersonId());

                if ($personType == self::PERSON_TECHNICIAN) {
                    $errorClass = LpWoTechnicianCannotCompleteException::class;
                } elseif ($personType == self::PERSON_SUPPLIER) {
                    $errorClass = LpWoSupplierCannotCompleteException::class;
                } else {
                    $errorClass = LpWoVendorCannotCompleteException::class;
                }
            }

            $exp = $this->app->make($errorClass);

            // set exception data to details and throw exception
            $expData = [
                'link_person_wo_id' => $lpWo->getId(),
                'person_id'         => getCurrentPersonId(),
                'status_type_id'    => $statusTypeId,
                'status_type_name'  => getTypeValueById($statusTypeId),
            ];
            if ($personType !== null) {
                $expData['person_type'] = $personType;
            }
            $exp->setData($expData);
            throw $exp;
        }

        // for some cases we need to verify if actual completion date is not empty
        $this->verifyActualCompletionDate($lpWo, $isMobile);

        // complete this lpwo
        return $this
            ->completeLpWo($lpWo, $updateWoStatus, $via, $updateStatusReason);
    }

    /**
     * Verify if actual completion date is not empty (in some cases).
     * In case it is, it will return exception to prevent completing
     * link person wo
     *
     * @param  LinkPersonWo  $lpWo
     * @param  int  $isMobile
     *
     * @throws InvalidArgumentException
     * @throws LpWoCannotCompleteNoCompletionDateException
     */
    protected function verifyActualCompletionDate(LinkPersonWo $lpWo, $isMobile)
    {
        if (!$this->isMobile($isMobile) &&
            config('system_settings.work_order_completion_date_on_status_change') !=
            1
        ) {
            $uncompletedVendorsLeft =
                $this->lpWoRepo->getAssignedVendorsCount(
                    $lpWo->getWorkOrderId(),
                    false,
                    [
                        getTypeIdByKey('wo_vendor_status.assigned'),
                        getTypeIdByKey('wo_vendor_status.issued'),
                        getTypeIdByKey('wo_vendor_status.confirmed'),
                        getTypeIdByKey('wo_vendor_status.in_progress'),
                        getTypeIdByKey('wo_vendor_status.in_progress_and_hold'),
                    ]
                );

            if ($uncompletedVendorsLeft == 1) {
                $workOrder = $this->getWorkOrder($lpWo);
                $actualCompletionDate =
                    $workOrder ? $workOrder->getActualCompletionDate() : null;
                /* if current person is last uncompleted vendor and actual
                   completion date is not filled we don't allow to mark as
                   complete (ACD should be filled first) */
                if (isEmptyDateTime($actualCompletionDate)) {
                    $exp =
                        $this->app->make(LpWoCannotCompleteNoCompletionDateException::class);
                    $exp->setData([
                        'link_person_wo_id'      => $lpWo->getId(),
                        'person_id'              => getCurrentPersonId(),
                        'actual_completion_date' => $actualCompletionDate,
                    ]);

                    throw $exp;
                }
            }
        }
    }

    /**
     * Verify if request is for mobile device
     *
     * @param  int  $isMobile
     *
     * @return bool
     */
    protected function isMobile($isMobile)
    {
        return ($isMobile == 1);
    }

    /**
     * Complete link person wo and update work order (if needed)
     *
     * @param  LinkPersonWo  $lpWo
     * @param  bool  $updateWoStatus
     * @param  bool|string  $via
     * @param  string  $updateStatusReason
     *
     * @return LinkPersonWo
     *
     * @throws InvalidArgumentException
     * @throws NotImplementedException
     */
    protected function completeLpWo(
        LinkPersonWo $lpWo,
        $updateWoStatus,
        $via,
        $updateStatusReason
    ) {
        // set valid status depending of type
        list($statusId, $updateWoStatus) =
            $this->getCompletedVendorStatus($lpWo, $updateWoStatus);
        $lpWo->status_type_id = $statusId;
        $lpWo->priority = 0;

        // save modified link person wo
        $lpWo->save();

        /** @var ActivityRepository $activityRepo */
        $activityRepo = $this->app->make(ActivityRepository::class);

        // add activity
        $activityRepo->add(
            'work_order',
            $lpWo->getWorkOrderId(),
            $this->trans->get(
                'lpwo_complete.changed_to_completed',
                [
                    'person' => $this->getPersonName($lpWo->getPersonId()),
                    'type'   => empty(getCurrentPersonId()) ? '' : ' manually',
                    'via'    => $via == false
                        ? ''
                        : (' '.
                            $this->trans->get('via').
                            ' '.$via),
                    'reason' => $this->getUpdateStatusReason($updateStatusReason),
                ]
            ),
            '',
            getCurrentPersonId(),
            0,
            ($via == false)
                ? Activity::DIRECTION_INT
                : Activity::DIRECTION_OUT
        );

        // if required, update also work order status
        if ($updateWoStatus) {
            /** @var WorkOrderStatusService $woStatusService */
            $woStatusService = $this->app->make(WorkOrderStatusService::class);

            if ($woStatusService->update($lpWo->getWorkOrderId())) {
                /** @var PushNotificationAdderService $pushNotificationAdderService */
                $pushNotificationAdderService = app(PushNotificationAdderService::class);
                $pushNotificationAdderService->technicianCompletedWorkOrder(
                    getCurrentPersonId(),
                    $lpWo->getWorkOrderId()
                );
            }
        }

        return $lpWo;
    }

    /**
     * Get person name
     *
     * @param  int  $personId
     *
     * @return null|string
     *
     * @throws InvalidArgumentException
     */
    protected function getPersonName($personId)
    {
        /** @var PersonRepository $personRepo */
        $personRepo = $this->app->make(PersonRepository::class);

        return $personRepo->getPersonName($personId);
    }

    /**
     * Verify if link person wo should be updated to completed
     *
     * @param  LinkPersonWo  $lpWo
     *
     * @return bool
     */
    protected function verifyIfLpWoCanBeCompleted(LinkPersonWo $lpWo)
    {
        if ($lpWo->getStatusTypeId() ==
            $this->getCompletedVendorStatus($lpWo)[0]
        ) {
            return false;
        }

        $personType = $this->getPersonType($lpWo->getPersonId());
        $status = $lpWo->getStatusTypeId();

        // @codingStandardsIgnoreStart
        $canBeCompleted = false;
        if ($lpWo->getType() == 'quote') {
            if ($lpWo->getStatusTypeId() ==
                getTypeIdByKey('wo_quote_status.rfq_confirmed')
            ) {
                $canBeCompleted = true;
            }
        } elseif (
            /* @todo those are probably CLIENT comments - do we want to use
             * same logic for all clients or maybe it should be only for
             * specific client?
             */

            /* For SUPPLIERS only - I would like to be able to go from ASSIGNED
            to COMPLETED directly, however this would be for SUPPLIERS only
            and not TECHS or VENDORS */
            ($personType == self::PERSON_SUPPLIER &&
                $status == getTypeIdByKey('wo_vendor_status.assigned')) ||

            /*For TECHS only - I would like to follow all the steps in order to
            complete. A TECH should go from ASSIGNED, CONFIRMED, ISSUED and
            then COMPLETE. */
            ($personType == self::PERSON_TECHNICIAN &&
                in_array($status, [
                    getTypeIdByKey('wo_vendor_status.confirmed'),
                    getTypeIdByKey('wo_vendor_status.in_progress'),
                    getTypeIdByKey('wo_vendor_status.in_progress_and_hold'),
                ])) ||

            /* For VENDORS only - I would like the assignment to be in ISSUED
            1st in order to COMPLETE. It's ok to skip Confirmed for VENDORS
            only, NOT TECHS */
            ($personType == self::PERSON_VENDOR &&
                in_array($status, [
                    getTypeIdByKey('wo_vendor_status.issued'),
                    getTypeIdByKey('wo_vendor_status.confirmed'),
                    getTypeIdByKey('wo_vendor_status.in_progress'),
                    getTypeIdByKey('wo_vendor_status.in_progress_and_hold'),
                ]))
        ) {
            $canBeCompleted = true;
        }

        // @codingStandardsIgnoreEnd

        return $canBeCompleted;
    }

    /**
     * Get person type
     *
     * @param  int  $personId  Person Id
     *
     * @return string
     *
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     */
    protected function getPersonType($personId)
    {
        /** @var PersonRepository $personRepo */
        $personRepo = $this->app->make(PersonRepository::class);
        /** @var Person $person */
        $person = $personRepo->find($personId, ['type_id'], false);

        $personTypeId = $person->getTypeId();

        $type = self::PERSON_VENDOR;

        if (in_array($personTypeId, getTypeIdByKey('person.employee', true))) {
            $type = self::PERSON_TECHNICIAN;
        } elseif ($personTypeId == getTypeIdByKey('company.supplier')) {
            $type = self::PERSON_SUPPLIER;
        }

        return $type;
    }

    /**
     * Get completed vendor status and whether work order status should be
     * updated
     *
     * @param  LinkPersonWo  $lpWo
     * @param  null|bool  $updateWoStatus  If not passed you should not use
     *                                     $updateWoStatus from return array
     *
     * @return array
     */
    protected function getCompletedVendorStatus(
        LinkPersonWo $lpWo,
        $updateWoStatus = null
    ) {
        if ($lpWo->getType() == 'quote') {
            $statusId = getTypeIdByKey('wo_quote_status.rfq_received');
            $updateWoStatus = false;
        } else {
            $statusId =
                getTypeIdByKey('wo_vendor_status.completed');
        }

        return [$statusId, $updateWoStatus];
    }

    /**
     * Get update status reason
     *
     * @param  string  $updateStatusReason
     *
     * @return string
     */
    protected function getUpdateStatusReason($updateStatusReason)
    {
        return ($updateStatusReason === null ? '' :
            "\n{$this->trans->get('reason')}: ".$updateStatusReason);
    }

    /**
     * Verify if completion code is required
     *
     * @param  int|LinkPersonWo  $lpWo
     * @param  bool|false  $getWorkOrder  If set to true return result
     *                                       will be array
     *
     * @return array|bool
     */
    public function isCompletionCodeRequired($lpWo, $getWorkOrder = false)
    {
        $wo = null;
        $required = false;

        if (!$lpWo instanceof LinkPersonWo) {
            $lpWo = $this->lpWoRepo->findSoft($lpWo);
        }

        if ($lpWo) {
            $wo = $this->getWorkOrder($lpWo);

            if ($wo) {
                /** @var CustomerSettingsRepository $csRepo */
                $csRepo = $this->app->make(CustomerSettingsRepository::class);
                if ($wo->getCustomerSettingId()) {
                    $cs = $csRepo->findSoft($wo->getCustomerSettingId());
                } else {
                    $cs = $csRepo->getForPerson($wo->getCompanyPersonId());
                }

                /** @var CustomerSettings $cs */
                if ($cs && $cs->getRequiredCompletionCode()) {
                    $required = true;
                }
            }
        }

        if ($getWorkOrder) {
            return [$required, $wo];
        }

        return $required;
    }

    /**
     * Get WorkOrder from link person wo
     *
     * @param  LinkPersonWo  $lpWo
     *
     * @return WorkOrder|Model
     */
    protected function getWorkOrder(LinkPersonWo $lpWo)
    {
        return $this->woRepo->findSoft($lpWo->getWorkOrderId());
    }

    /**
     * @param $tableName
     * @param $recordId
     */
    public function checkIfAllVendorsHaveCompleteStatuses($tableName, $recordId)
    {
        $workOrderId = null;
        if ($tableName === 'link_person_wo') {
            $workOrderId = LinkPersonWo::find($recordId)->getWorkOrderId();
        } elseif ($tableName === 'work_order') {
            $workOrderId = $recordId;
        }
        
        if ($workOrderId) {
            /** @var LinkPersonWoRepository $linkPersonWoRepository */
            $linkPersonWoRepository = app(LinkPersonWoRepository::class);
            
            // active vendor count
            $totalActVendCount = $linkPersonWoRepository->getAssignedVendorsCount($workOrderId, false);

            $vendStatusCount = $linkPersonWoRepository->getVendorStatusesCount($workOrderId, 'work,recall', false);
            $completedCount = $vendStatusCount['wo_vendor_status.completed'] ?? 0;
            
            if ($completedCount < $totalActVendCount) {
                throw $this->app->make(LpWoAllNotCompletedException::class);
            }
        }
    }
}
