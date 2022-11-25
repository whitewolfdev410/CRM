<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Trans;
use App\Modules\Activity\Repositories\ActivityRepository;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\WorkOrder\Exceptions\LpWoAlreadyIssuedException;
use App\Modules\WorkOrder\Exceptions\LpWoChangeStatusInProgressException;
use App\Modules\WorkOrder\Exceptions\LpWoChangeStatusInvalidStatusException;
use App\Modules\WorkOrder\Exceptions\LpWoCurrentlyAssignedException;
use App\Modules\WorkOrder\Exceptions\LpWoCurrentlyIssuedException;
use App\Modules\WorkOrder\Exceptions\LpWoMissingQbInfoWhenIssueException;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LinkPersonWoStatusService
{
    /**
     * Link Person WO repository
     *
     * @var LinkPersonWoRepository
     */
    protected $lpwoRepo;

    /**
     * App
     *
     * @var Container
     */
    protected $app;

    /**
     * Config
     *
     * @var mixed
     */
    protected $config;

    /**
     * Translation
     *
     * @var Trans
     */
    protected $transService;

    /**
     * Translation key that will be used as prefix for translation string
     *
     * @var string
     */
    protected $transKey = '';

    /**
     * @var WorkOrderRepository
     */
    protected $woRepo;

    /**
     * Initialize fields
     *
     * @param LinkPersonWoRepository $lpwoRepo
     * @param Container $app
     * @param WorkOrderRepository $woRepository
     */
    public function __construct(
        LinkPersonWoRepository $lpwoRepo,
        Container $app,
        WorkOrderRepository $woRepository
    ) {
        $this->lpwoRepo = $lpwoRepo;
        $this->app = $app;
        $this->config = $app->config;
        $this->transService = $this->app->make(Trans::class);
        $this->woRepo = $woRepository;
    }

    /**
     * Change link person wo status
     *
     * @param int $lpWoId
     * @param string $statusLabel
     * @param string $additionalInformation
     * @param int|null $reasonTypeId
     * @param int|null $noInvoiceCertify
     * @param int|null $assignToPersonId
     * @param string|null $jobType
     * @param int|null $recallLinkPersonWoId
     * @param null $completionCode
     * @param int $force
     *
     * @return array
     * @throws LpWoChangeStatusInvalidStatusException
     * @throws object
     */
    public function change(
        $lpWoId,
        $statusLabel,
        $additionalInformation = '',
        $reasonTypeId = null,
        $noInvoiceCertify = null,
        $assignToPersonId = null,
        $jobType = null,
        $recallLinkPersonWoId = null,
        $completionCode = null,
        $force = 0
    ) {
        $lpWoId = (int)$lpWoId;
        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpwoRepo->find($lpWoId);

        $wo = $this->getWorkOrder($lpWo->getWorkOrderId());

        if ($statusLabel == 'canceled') {
            /** @var LinkPersonWoCancelService $service */
            $service = $this->app->make(LinkPersonWoCancelService::class);
            $lpWo = $service->run(
                $lpWo,
                $reasonTypeId,
                $noInvoiceCertify,
                $assignToPersonId,
                $jobType,
                $recallLinkPersonWoId,
                $additionalInformation
            );
        } else {
            // set force status (for issued it will be always false)
            $force = ($statusLabel == 'issued') ? false : (bool)$force;

            // update status
            $this->updateStatus(
                $lpWo,
                $statusLabel,
                true,
                $force,
                $reasonTypeId,
                $completionCode,
                $additionalInformation
            );
        }

//        try {
//            /** @var WorkOrderStatusService $workOrderStatusService */
//            $workOrderStatusService = app(WorkOrderStatusService::class);
//            $workOrderStatusService->update($lpWo->getWorkOrderId());
//        } catch(\Exception $e) {
//            Log::error('Cannot update work order status', $e->getTrace());
//        }
        
        $woMod = $this->getWorkOrder($lpWo->getWorkOrderId());

        return [
            $this->lpwoRepo->find($lpWoId),
            $this->calculateChanges($wo, $woMod),
        ];
    }

    /**
     * Get changes made in work order when canceling link person wo
     *
     * @param WorkOrder|null $wo
     * @param WorkOrder|null $wo2
     *
     * @return array
     */
    public function calculateChanges(
        WorkOrder $wo = null,
        WorkOrder $wo2 = null
    ) {
        if (!$wo || !$wo2) {
            return [];
        }

        $wo = $wo->toArray();
        $wo2 = $wo2->toArray();

        $changes = array_diff_assoc($wo2, $wo);

        // if work order was not updated and only last_edit_delay seems to be
        // changed we will return empty array of changes
        if (count($changes) == 1 &&
            array_key_exists('last_edit_delay', $changes)
        ) {
            $changes = [];
        }

        return ['work_order' => $changes];
    }

    /**
     * Get work order
     *
     * @param int $workOrderId
     *
     * @return WorkOrder
     */
    protected function getWorkOrder($workOrderId)
    {
        return $this->woRepo->find($workOrderId);
    }

    /**
     * Update status of link person wo
     *
     * @param LinkPersonWo $lpWo
     * @param string $statusLabel
     * @param bool $updateWoStatus
     * @param bool $force
     * @param int|null $updateStatusReasonId
     * @param string|null $completionCode
     * @param null $additionalInformation
     *
     * @return LinkPersonWo
     * @throws LpWoAlreadyIssuedException
     * @throws LpWoChangeStatusInvalidStatusException
     * @throws LpWoCurrentlyAssignedException
     * @throws LpWoCurrentlyIssuedException
     * @throws LpWoMissingQbInfoWhenIssueException
     * @throws \Exception
     * @throws object
     */
    public function updateStatus(
        LinkPersonWo $lpWo,
        $statusLabel,
        $updateWoStatus = true,
        $force = false,
        $updateStatusReasonId = null,
        $completionCode = null,
        $additionalInformation = null
    ) {
        if ($lpWo->getStatusTypeId() ==
            getTypeIdByKey('wo_vendor_status.in_progress')
        ) {
            throw $this->app->make(LpWoChangeStatusInProgressException::class);
        }

        $updateStatusReason = null;
        if ($updateStatusReasonId) {
            $updateStatusReason = getTypeValueById($updateStatusReasonId);
        }

        switch ($statusLabel) {
            case 'assigned':
                $this->assign($lpWo, $updateWoStatus, $updateStatusReason);
                break;
            case 'issued':
                $this->issue(
                    $lpWo,
                    $updateWoStatus,
                    $force,
                    $updateStatusReason
                );
                break;
            case 'confirmed':
                /** @var LinkPersonWoConfirmService $service */
                $service = $this->app->make(LinkPersonWoConfirmService::class);
                $service->makeConfirmation(
                    $lpWo,
                    $updateWoStatus,
                    $force,
                    false,
                    $updateStatusReason
                );
                break;
            case 'completed':
                /** @var LinkPersonWoCompleteService $service */
                $service = $this->app->make(LinkPersonWoCompleteService::class);
                $service->makeCompletion(
                    $lpWo,
                    $completionCode,
                    0,
                    $updateWoStatus,
                    $force,
                    false,
                    $updateStatusReason
                );
                break;
            default:
                $statusError = true;
                if ($lpWo->getType() == 'quote' && $force) {
                    $statusId =
                        getTypeIdByKey('wo_quote_status.' . $statusLabel);
                    if ($statusId) {
                        $statusError = false;
                        $this->changeQuoteStatus(
                            $lpWo,
                            $statusId,
                            $updateStatusReason
                        );
                    }
                }

                // if it's not a quote for valid type_key label we throw exception
                if ($statusError) {
                    /** @var LpWoChangeStatusInvalidStatusException $exp */
                    $exp =
                        $this->app->make(LpWoChangeStatusInvalidStatusException::class);
                    throw $exp;
                }
        }

        $this->addChangeStatusActivity(
            $lpWo,
            $statusLabel,
            $updateStatusReason,
            $additionalInformation
        );

        return $lpWo;
    }

    /**
     * Add change status activity for vendor
     *
     * @param LinkPersonWo $lpWo
     * @param string $statusName
     * @param string|null $updateStatusReason
     * @param string|null $additionalInformation
     */
    protected function addChangeStatusActivity(
        LinkPersonWo $lpWo,
        $statusName,
        $updateStatusReason = null,
        $additionalInformation = null
    ) {
        $updateStatusReason = $this->modifyReason($updateStatusReason);

        /** @var PersonRepository $personRepo */
        $personRepo = $this->getRepository('Person');
        $personName = $personRepo->getPersonName($lpWo->getPersonId());

        // set activity description
        $description =
            $this->transService->get(
                'lpwo_status_change.activity_description',
                [
                    'name' => $personName,
                    'status_name' => $statusName,
                    'reason' => $updateStatusReason,
                ]
            );

        // add to activity description additional information
        if (trim($additionalInformation) != '') {
            $description .= '<br/>' .
                $this->transService->get('additional_information') . ': ' .
                trim($additionalInformation);
        }

        /** @var ActivityRepository $act */
        $act = $this->getRepository('Activity');
        $act->add('work_order', $lpWo->getWorkOrderId(), $description);
    }

    /**
     * Change quote status (for Link Person Wo with quote type for other
     * statuses)
     *
     * @param LinkPersonWo $lpWo
     * @param int $statusId
     * @param string|null $updateStatusReason
     */
    protected function changeQuoteStatus(
        LinkPersonWo $lpWo,
        $statusId,
        $updateStatusReason = null
    ) {
        DB::transaction(function () use (
            $lpWo,
            $statusId,
            $updateStatusReason
        ) {
            $this->updateStatusAndPriority($lpWo, $statusId);
            $this->addActivityMessage(
                $lpWo,
                getTypeValueById($statusId),
                $updateStatusReason,
                true
            );
        });
    }

    /**
     * Change status of link person wo to Assigned
     *
     * @param LinkPersonWo $lpWo
     * @param bool $updateWoStatus
     * @param string|null $updateStatusReason
     *
     * @throws LpWoCurrentlyAssignedException
     */
    protected function assign(
        LinkPersonWo $lpWo,
        $updateWoStatus = true,
        $updateStatusReason = null
    ) {
        if ($lpWo->getType() == 'quote') {
            $statusId = getTypeIdByKey('wo_quote_status.rfq_assigned');
            $updateWoStatus = false;
        } else {
            $statusId = getTypeIdByKey('wo_vendor_status.assigned');
        }

        if ($lpWo->getStatusTypeId() == $statusId) {
            /** @var LpWoCurrentlyAssignedException $exp */
            $exp = $this->app->make(LpWoCurrentlyAssignedException::class);
            throw $exp;
        }

        DB::transaction(function () use (
            $lpWo,
            $statusId,
            $updateStatusReason,
            $updateWoStatus
        ) {
            $this->updateStatusAndPriority($lpWo, $statusId);

            $this->addActivityMessage($lpWo, 'Assigned', $updateStatusReason);
            if ($updateWoStatus) {
                $wss = $this->app->make(WorkOrderStatusService::class);
                $wss->update($lpWo->getWorkOrderId());
            }
        });
    }

    /**
     * Change status of link person wo to Issued
     *
     * @param LinkPersonWo $lpWo
     * @param bool $updateWoStatus
     * @param bool $force
     * @param string|null $updateStatusReason
     *
     * @throws LpWoAlreadyIssuedException
     * @throws LpWoCurrentlyIssuedException
     * @throws LpWoMissingQbInfoWhenIssueException
     */
    public function issue(
        LinkPersonWo $lpWo,
        $updateWoStatus = true,
        $force = false,
        $updateStatusReason = null
    ) {
        $lpWoStatusId = $lpWo->getStatusTypeId();

        if ($lpWo->getType() == 'quote') {
            $statusId = getTypeIdByKey('wo_quote_status.rfq_issued');
            $updateWoStatus = false;
        } else {
            $statusId = getTypeIdByKey('wo_vendor_status.issued');
        }

        // force
        if ($force) {
            $this->handleIssue(
                $lpWo,
                $statusId,
                $updateWoStatus,
                $updateStatusReason,
                $force
            );

            return;
        }

        // normal (not force)

        if (in_array($lpWoStatusId, [
            getTypeIdByKey('wo_quote_status.rfq_assigned'),
            getTypeIdByKey('wo_vendor_status.assigned'),
        ])) {
            // @todo from OLD CRM #FIXME: what for is this needed ?
            // check if WO description for vendor is empty.
            if (empty($lpWo->getQbInfo())) {
                if (config('app.crm_user') === 'fs') {
                    /** @var LinkPersonWoQbInfoService $linkPersonWoQbInfoService */
                    $linkPersonWoQbInfoService = app(LinkPersonWoQbInfoService::class);

                    $workOrder = $this->getWorkOrder($lpWo->getWorkOrderId());

                    $lpWo->qb_info = $linkPersonWoQbInfoService->getQbInfo($lpWo, $workOrder);
                    $lpWo->save();
                } else {
                    /** @var LpWoMissingQbInfoWhenIssueException $exp */
                    $exp =
                        $this->app->make(LpWoMissingQbInfoWhenIssueException::class);
                    throw $exp;
                }
            }

            $this->handleIssue(
                $lpWo,
                $statusId,
                $updateWoStatus,
                $updateStatusReason,
                $force
            );
        } else {
            if ($lpWoStatusId == $statusId) {
                /** @var LpWoCurrentlyIssuedException $exp */
                $exp = $this->app->make(LpWoCurrentlyIssuedException::class);
                throw $exp;
            } else {
                /** @var LpWoAlreadyIssuedException $exp */
                $exp = $this->app->make(LpWoAlreadyIssuedException::class);

                // set exception data to give details
                $exp->setData([
                    'link_person_wo_id' => $lpWo->getId(),
                    'status_type_id' => $lpWoStatusId,
                    'status_type_name' => getTypeValueById($lpWoStatusId),
                ]);

                throw $exp;
            }
        }
    }

    /**
     * Handle setting issued
     *
     * @param LinkPersonWo $lpWo
     * @param int $statusId
     * @param bool $updateWoStatus
     * @param string $updateStatusReason
     * @param bool $force
     */
    protected function handleIssue(
        LinkPersonWo $lpWo,
        $statusId,
        $updateWoStatus,
        $updateStatusReason,
        $force
    ) {
        DB::transaction(function () use (
            $lpWo,
            $statusId,
            $updateStatusReason,
            $updateWoStatus,
            $force
        ) {
            $this->updateStatusAndPriority($lpWo, $statusId);

            $this->addActivityMessage($lpWo, 'Issued', $updateStatusReason);
            if ($updateWoStatus) {
                $wss = $this->app->make(WorkOrderStatusService::class);
                $wss->update($lpWo->getWorkOrderId());
            }
        });
    }

    /**
     * Fix status after time sheet change
     *
     * @param int $lpwoId
     *
     * @return bool
     */
    public function fixStatusAfterTimeSheetChange($lpwoId)
    {
        /** @var LinkPersonWo $lpwo */
        $lpwo = $this->lpwoRepo->findSoft($lpwoId);

        if (!$lpwo) {
            return false;
        }

        $type = $this->getRepository('Type');
        $tsRepo = $this->getRepository('TimeSheet');

        if ($lpwo->getStatusTypeId()
            == $type->getIdByKey('wo_vendor_status.in_progress')
            && $tsRepo->isInProgress($lpwoId)
        ) {
            $this->setInProgressAndHold(
                $lpwoId,
                true,
                false,
                ' Edited time sheet start/end date'
            );
        }

        return false;
    }

    /**
     * Adds activity message that status has been updated
     *
     * @param LinkPersonWo $lpWo
     * @param string $statusName
     * @param string|null $updateStatusReason
     * @param bool $useQuoteText
     */
    protected function addActivityMessage(
        LinkPersonWo $lpWo,
        $statusName,
        $updateStatusReason = null,
        $useQuoteText = false
    ) {
        $updateStatusReason = $this->modifyReason($updateStatusReason);

        /** @var PersonRepository $personRepo */
        $personRepo = $this->getRepository('Person');
        $personName = $personRepo->getPersonName($lpWo->getPersonId());
        $currentPersonId = getCurrentPersonId();

        $statusUpdated = ($useQuoteText)
            ? 'lpwo_status_change.quote_status_updated'
            : 'lpwo_status_change.status_updated';
        $statusUpdated = $this->transService->get($statusUpdated);

        $message = $personName . ' - ' . $statusUpdated . ' ' .
            ($currentPersonId ? $this->transService->get('manually') . ' ' : '')
            . $this->transService->get('to') . ' "' . $statusName . '"' .
            $updateStatusReason;

        /** @var ActivityRepository $act */
        $act = $this->getRepository('Activity');
        $act->add(
            'work_order',
            $lpWo->getWorkOrderId(),
            $message,
            '',
            $currentPersonId
        );
    }

    /**
     * Modify reason
     *
     * @param string|null $updateStatusReason
     *
     * @return string
     */
    protected function modifyReason($updateStatusReason)
    {
        $updateStatusReason = ($updateStatusReason === null ? ''
            : "\n" . $this->transService->get('reason') . ': ' .
            $updateStatusReason);

        return $updateStatusReason;
    }

    /**
     * Set in progress and hold status for given link person WO
     *
     * @param int $lpwoId
     * @param bool $updateWorkOrderStatus
     * @param bool $force
     * @param null $updateStatusReason
     */
    public function setInProgressAndHold(
        $lpwoId,
        $updateWorkOrderStatus = true,
        $force = false,
        $updateStatusReason = null
    ) {
        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpwoRepo->findSoft($lpwoId);

        if (!$lpWo) {
            return;
        }

        // force
        if ($force) {
            $this->handleSetInProgressAndHold(
                $lpWo,
                $updateWorkOrderStatus,
                $updateStatusReason,
                $force
            );

            return;
        }

        // normal way (no force)
        if ($lpWo->getStatusTypeId() !=
            getTypeIdByKey('wo_vendor_status.completed')
        ) {
            $this->handleSetInProgressAndHold(
                $lpWo,
                $updateWorkOrderStatus,
                $updateStatusReason,
                $force
            );
        } else {
            // @todo - should we throw exception here ?
        }
    }

    /**
     * Set in progress status for given link person WO
     *
     * @param int $lpwoId
     * @param bool $updateWorkOrderStatus
     * @param bool $force
     * @param null $updateStatusReason
     */
    public function setInProgress(
        $lpwoId,
        $updateWorkOrderStatus = true,
        $force = false,
        $updateStatusReason = null
    ) {
        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpwoRepo->findSoft($lpwoId);

        if (!$lpWo) {
            return;
        }

        // force
        if ($force) {
            $this->handleSetInProgress(
                $lpWo,
                $updateWorkOrderStatus,
                $updateStatusReason,
                $force
            );

            return;
        }

        // normal (not force)
        if ($lpWo->getStatusTypeId()
            != getTypeIdByKey('wo_vendor_status.completed')
        ) {
            $this->handleSetInProgress(
                $lpWo,
                $updateWorkOrderStatus,
                $updateStatusReason,
                $force
            );
        } else {
            // @todo - should we throw exception here ?
        }
    }

    /**
     * Handle setting in progress
     *
     * @param LinkPersonWo $lpWo
     * @param bool $updateWoStatus
     * @param string $updateStatusReason
     * @param bool $force
     *
     * @return array
     */
    protected function handleSetInProgress(
        LinkPersonWo $lpWo,
        $updateWoStatus,
        $updateStatusReason,
        $force
    ) {
        $statusId = getTypeIdByKey('wo_vendor_status.in_progress');

        DB::transaction(function () use (
            $lpWo,
            $statusId,
            $updateStatusReason,
            $updateWoStatus,
            $force
        ) {
            $this->updateStatusAndPriority($lpWo, $statusId);

            $this->addActivityMessage(
                $lpWo,
                'In Progress',
                $updateStatusReason
            );
            if ($updateWoStatus) {
                $wss = $this->app->make(WorkOrderStatusService::class);
                $wss->update($lpWo->getWorkOrderId());
            }
        });
    }

    /**
     * Handle setting in progress & hold
     *
     * @param LinkPersonWo $lpWo
     * @param bool $updateWoStatus
     * @param string $updateStatusReason
     * @param bool $force
     *
     * @return array
     */
    protected function handleSetInProgressAndHold(
        LinkPersonWo $lpWo,
        $updateWoStatus,
        $updateStatusReason,
        $force
    ) {
        $statusId = getTypeIdByKey('wo_vendor_status.in_progress_and_hold');

        DB::transaction(function () use (
            $lpWo,
            $statusId,
            $updateStatusReason,
            $updateWoStatus,
            $force
        ) {
            $this->updateStatusAndPriority($lpWo, $statusId);

            $this->addActivityMessage(
                $lpWo,
                'In Progress & Hold',
                $updateStatusReason
            );
            if ($updateWoStatus) {
                $wss = $this->app->make(WorkOrderStatusService::class);
                $wss->update($lpWo->getWorkOrderId());
            }
        });
    }

    /**
     * Update Link Person Wo status and priority
     *
     * @param LinkPersonWo $lpWo
     * @param int $statusTypeId
     *
     * @return bool
     */
    protected function updateStatusAndPriority(
        LinkPersonWo $lpWo,
        $statusTypeId
    ) {
        $lpWo->status_type_id = $statusTypeId;
        if (empty($lpWo->getPriority())) {
            $lpWo->priority = $this->lpwoRepo
                ->getNewPriorityWithUpdateInProgress($lpWo->getPersonId());
        }

        return $lpWo->save();
    }

    /**
     * Get repository
     *
     * @param string $repositoryName
     * @param string $moduleName
     *
     * @return mixed
     */
    protected function getRepository($repositoryName, $moduleName = null)
    {
        return $this->lpwoRepo->getRepository($repositoryName, $moduleName);
    }
}
