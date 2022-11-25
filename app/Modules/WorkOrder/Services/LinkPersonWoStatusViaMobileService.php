<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\MsDynamics\TaskStatusSync;

class LinkPersonWoStatusViaMobileService
{
    const DEFAULT_STATUS_UPDATE_POLICY = 'default';
    const TECH_STATUS_UPDATE_POLICY = 'tech_status';

    /**
     * Status update policy
     * @var string
     */
    protected $statusUpdatePolicy;

    /**
     * @var LinkPersonWoConfirmService
     */
    protected $lpwoConfirmService;

    /**
     * @var LinkPersonWoStatusService
     */
    protected $lpwoStatusService;

    /**
     * @var LinkPersonWoCompleteService
     */
    protected $lpwoCompleteService;

    /**
     * @var LinkPersonWoRecordService
     */
    protected $lpwoRecordService;

    /**
     * Constructor
     * @param LinkPersonWoConfirmService  $lpwoConfirmService
     * @param LinkPersonWoStatusService   $lpwoStatusService
     * @param LinkPersonWoCompleteService $lpwoCompleteService
     * @param LinkPersonWoRecordService   $lpwoRecordService
     * @param TechStatusHistoryService    $techStatusHistoryService
     */
    public function __construct(
        LinkPersonWoConfirmService $lpwoConfirmService,
        LinkPersonWoStatusService $lpwoStatusService,
        LinkPersonWoCompleteService $lpwoCompleteService,
        LinkPersonWoRecordService $lpwoRecordService,
        TechStatusHistoryService $techStatusHistoryService
    ) {
        $this->lpwoConfirmService = $lpwoConfirmService;
        $this->lpwoStatusService = $lpwoStatusService;
        $this->lpwoCompleteService = $lpwoCompleteService;
        $this->lpwoRecordService = $lpwoRecordService;
    }

    /**
     * Set status update policy
     * @param string $policy
     * @return self
     */
    public function setStatusUpdatePolicy($policy)
    {
        $this->statusUpdatePolicy = $policy;

        return $this;
    }

    /**
     * Update status via mobile
     * @param  LinkPersonWo $lpwo
     * @param  string       $newStatus
     * @param  int      $newTechStatusTypeId
     * @param  \Carbon      $confirmedAt
     * @param  string       $completionCode
     * @param  bool      $updateWorkOrderStatus
     */
    public function updateStatus(
        LinkPersonWo $lpwo,
        $newStatus,
        $newTechStatusTypeId,
        $confirmedAt,
        $completionCode,
        $updateWorkOrderStatus = true
    ) {
        if ($this->statusUpdatePolicy == self::TECH_STATUS_UPDATE_POLICY) {
            $this->techStatusUpdate(
                $lpwo,
                $newStatus,
                $newTechStatusTypeId,
                $confirmedAt,
                $completionCode
            );
        } else {
            $this->defaultStatusUpdate(
                $lpwo,
                $newStatus,
                $confirmedAt,
                $completionCode,
                $updateWorkOrderStatus
            );
        }

        // @fixme
        if (config('app.crm_user') == 'bfc') {
            // DO NOT queue status change in SL - it's been queued by MySQL trigger
            // app(TaskStatusSync::class)->queueStatusChange($lpwo->getId());
        }
    }

    /**
     * Default link person wo status update flow
     * @param  LinkPersonWo $lpwo
     * @param  string       $newStatus
     * @param  \Carbon       $confirmedAt
     * @param  string       $completionCode
     */
    protected function defaultStatusUpdate(
        LinkPersonWo $lpwo,
        $newStatus,
        $confirmedAt,
        $completionCode,
        $updateWorkOrderStatus
    ) {
        $statusInCrm = $this->getStatusInCrm($lpwo);

        // this code was optimized for speed and readability
        if ($newStatus == 'confirmed') {
            if ($statusInCrm == 'issued') {
                $this->lpwoConfirmService->confirm(
                    $lpwo->getId(),
                    'mobile',
                    $confirmedAt,
                    $updateWorkOrderStatus
                );
            }

            return ;
        }

        if ($newStatus == 'in_progress') {
            if ($statusInCrm != 'in_progress') {
                $this->lpwoStatusService->setInProgress(
                    $lpwo->getId(),
                    $updateWorkOrderStatus
                );
            }

            return ;
        }

        if ($newStatus == 'in_progress_and_hold') {
            if ($statusInCrm != 'in_progress_and_hold') {
                $this->lpwoStatusService->setInProgressAndHold(
                    $lpwo->getId(),
                    $updateWorkOrderStatus
                );
            }

            return ;
        }

        if (config('mobile_work_order.allow_wo_complete_from_mobile')
            && $newStatus == 'completed'
        ) {
            if ($statusInCrm != 'completed') {
                $this->lpwoCompleteService->complete(
                    $lpwo->getId(),
                    'mobile',
                    $completionCode,
                    true,
                    false,
                    $updateWorkOrderStatus
                );
            }

            return ;
        }

        return ;
    }

    /**
     * Link person wo status with tech status update flow
     * @param  LinkPersonWo $lpwo
     * @param  string       $newStatus
     * @param  int      $newTechStatusTypeId
     * @param  \Carbon      $confirmedAt
     * @param  string       $completionCode
     */
    protected function techStatusUpdate(
        LinkPersonWo $lpwo,
        $newStatus,
        $newTechStatusTypeId,
        $confirmedAt,
        $completionCode
    ) {
        $statusInCrm = $this->getStatusInCrm($lpwo);

        if (in_array($statusInCrm, ['completed', 'assigned', 'canceled'])) {
            // we don't have to update record in this case
            // also this code shouldn't be called
            return ;
        }

        // @fixme
        if (config('app.crm_user') == 'bfc') {
            // @todo logic for BFC statuses
            if ($statusInCrm == 'issued' && $newStatus == 'confirmed') {
                $this->lpwoConfirmService->confirm(
                    $lpwo->getId(),
                    'mobile',
                    $confirmedAt
                );
            } elseif ($statusInCrm != 'in_progress'
                && in_array($newTechStatusTypeId, [
                    getTypeIdByKey('tech_status.in_route'),
                    getTypeIdByKey('tech_status.work_in_progress'),
                ])
            ) {
                $this->lpwoStatusService->setInProgress($lpwo->getId());
            } elseif ($statusInCrm != 'in_progress_and_hold'
                && in_array($newTechStatusTypeId, [
                    getTypeIdByKey('tech_status.completed'),
                    getTypeIdByKey('tech_status.incomplete'),
                ])
            ) {
                $this->lpwoStatusService->setInProgressAndHold($lpwo->getId());
            }

            // change status in SL - moved to updateStatus() method, but originally was here:
            // $this->app[TaskStatusSync::class]->queueStatusChange($lpwo->getId());
        } else {
            if ($statusInCrm == 'issued' && $newStatus == 'confirmed') {
                $this->lpwoConfirmService->confirm(
                    $lpwo->getId(),
                    'mobile',
                    $confirmedAt
                );
            } elseif ($statusInCrm != 'in_progress'
                && in_array($newTechStatusTypeId, [
                    getTypeIdByKey('tech_status.travel'),
                    getTypeIdByKey('tech_status.onsite'),
                ])
            ) {
                $this->lpwoStatusService->setInProgress($lpwo->getId());
            } elseif ($statusInCrm != 'in_progress_and_hold'
                && in_array($newTechStatusTypeId, [
                    getTypeIdByKey('tech_status.return_trip'),
                    getTypeIdByKey('tech_status.check_out'),
                    getTypeIdByKey('tech_status.waiting_quote'),
                    getTypeIdByKey('tech_status.tech_declined'),
                ])
            ) {
                $this->lpwoStatusService->setInProgressAndHold($lpwo->getId());
            }
        }

        return ;
    }

    /**
     * Return link person wo status as string (part of key)
     * @param  LinkPersonWo $lpwo
     * @return string
     */
    protected function getStatusInCrm(LinkPersonWo $lpwo)
    {
        $statusInCrm = '';
        $statusType = $lpwo->statusType;

        if ($statusType) {
            $statusInCrm = $statusType->getTypeKey();
            $statusInCrm = str_replace('wo_vendor_status.', '', $statusInCrm);
        }

        return $statusInCrm;
    }
}
