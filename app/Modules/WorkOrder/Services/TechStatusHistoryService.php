<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\WorkOrder\Repositories\TechStatusHistoryRepository;
use Illuminate\Container\Container;

class TechStatusHistoryService
{
    /**
     * @var TechStatusHistoryRepository
     */
    protected $statusRepo;

    /**
     * @var Container
     */
    protected $app;

    /**
     * Initialize fields
     *
     * @param WorkOrderRepository $woRepo
     * @param Container           $app
     */
    public function __construct(
        TechStatusHistoryRepository $statusRepo,
        Container $app
    ) {
        $this->statusRepo = $statusRepo;
        $this->app = $app;
    }

    public function add(
        $linkPersonWoId,
        $currentTechStatusTypeId,
        $previousTechStatusTypeId,
        $changedAt
    ) {
        $data = [
            'link_person_wo_id' => $linkPersonWoId,
            'current_tech_status_type_id' => $currentTechStatusTypeId,
            'previous_tech_status_type_id' => $previousTechStatusTypeId,
            'changed_at' => $changedAt,
        ];

        $link = $this->statusRepo->findWithData($data);

        if ($link) {
            return $link;
        }

        return $this->statusRepo->create($data);
    }

    /**
     * Update work order status
     *
     * @param int   $woId
     * @param int   $newWoStatusTypeId
     * @param mixed $extra
     *
     * @return bool
     * @throws NotImplementedException
     */
    public function update($woId, $newWoStatusTypeId = 0, $extra = null)
    {
        /** @var WorkOrder $wo */
        $wo = $this->woRepo->find($woId);
        $this->wo = $wo;
        if (!$wo) {
            $this->log("Problem with WO ID: '{$woId}'");

            return false;
        }

        $type = $this->getRepository('Type');
        $stat = $type->getList('wo_status', 'type_id', 'type_key');
        $this->woStat = $stat;

        if ($newWoStatusTypeId) {
            /* @todo implement if it will be used - in old crm it seems to be
             * not used  (in old CRM work_order_status@update_work_order_status)
             */
            $exception = $this->app->make(NotImplementedException::class);
            $exception->setData([
                'class' => __CLASS__,
                'line' => __LINE__,
            ]);
            throw $exception;
        }

        $lpwoRepo = $this->getRepository('LinkPersonWo', 'WorkOrder');
        $this->totalVendCount = $lpwoRepo->getAssignedVendorsCount(
            $woId,
            true
        ); // true - count disabled also
        $this->totalActVendCount
            = $lpwoRepo->getAssignedVendorsCount(
                $woId,
                false
            ); // active vendor count
        $this->vendStatusCount = $lpwoRepo->getVendorStatusesCount($woId);

        $this->setLogMessagePrefix();

        // if work order is Canceled, return true and STOP
        if ($this->hasWoStatus('canceled')) {
            $this->log('Status update canceled - WO already canceled');

            return true;
        }

        // no vendor assigned or all canceled
        if ($this->totalVendCount == 0 || $this->totalActVendCount == 0) {
            if ($wo->getPickupId() > 0) {
                // check if there are any quotes
                if ($lpwoRepo->getAssignedVendorsCount(
                    $woId,
                    false,
                    [],
                    'quote'
                ) > 0
                ) {
                    $this->updateWoStatus('quote');
                    $this->log('Updating status to: Quote');
                } // picked Up
                elseif ($this->hasNotWoStatus('picked_up')) {
                    $this->updateWoStatus('picked_up');
                    $this->log('Updating status to: Picked Up');
                }
            }

            return true;
        } // we have assigned vendors
        else {
            if ($this->shouldUpdateToComplete()) {
                if ($this->shouldUpdateCompletionDate()) {
                    $acdUpdatedDate = date('Y-m-d H:i:s');
                    $wo->actual_completion_date = $acdUpdatedDate;
                    $status = $wo->save();
                    if ($status) {
                        $this->prependToMessage("\nACD updated to: "
                            .$acdUpdatedDate);
                    } else {
                        $this->prependToMessage("\nFailure on updating ACD");
                    }
                }
                $this->log('Updating status to: Completed');

                return $this->updateWoStatus('completed');
            } elseif ($this->shouldUpdateToProgressAndHold()) {
                if ($this->hasNotWoStatus('in_progress_and_hold')) {
                    $this->log('Updating status to: In Progress & Hold');

                    return $this->updateWoStatus('in_progress_and_hold');
                } else {
                    $this->log('Already In Progress & Hold');

                    return true;
                }
            } elseif ($this->shouldUpdateToInProgress()) {
                if ($this->hasNotWoStatus('in_progress')) {
                    $this->log('Updating status to: In Progress');

                    return $this->updateWoStatus('in_progress');
                } else {
                    $this->log('Already In Progress');

                    return true;
                }
            } elseif ($this->shouldUpdateToConfirmed()) {
                if ($this->hasNotWoStatus('confirmed')) {
                    $this->log('Updating status to: Confirmed"');

                    return $this->updateWoStatus('confirmed');
                } else {
                    $this->log('Already Confirmed');

                    return true;
                }
            } elseif ($this->shouldUpdateToIssued()) {
                if ($this->hasNotWoStatus('issued_to_vendor_tech')) {
                    $this->log('Updating status to: Issued');

                    return $this->updateWoStatus('issued_to_vendor_tech');
                } else {
                    $this->log('Already Issued');

                    return true;
                }
            } elseif ($this->shouldUpdateToAssigned()) {
                if ($this->hasNotWoStatus('assigned_in_crm')) {
                    $this->log('Updating status to: Assigned');

                    return $this->updateWoStatus('assigned_in_crm');
                } else {
                    $this->log('Already Issued');

                    return true;
                }
            } else {
                $this->log('No need to update status');

                return true;
            }
        }
    }

    /**
     * Verify if work order status should be changed to Complete
     *
     * @return bool
     */
    protected function shouldUpdateToComplete()
    {
        // Completed - all vendors are completed
        return (bool) (
            $this->getVendStatusCount('completed') > 0
            && ($this->getVendStatusCount('completed')
                >= $this->totalActVendCount)
            && $this->hasNotWoStatus('completed')
        );
    }

    /**
     * Verify if work order status should be changed to In progress & Hold
     *
     * @return bool
     */
    protected function shouldUpdateToProgressAndHold()
    {
        // In Progress & Hold - all vendors are IN PROGRESS & HOLD OR there
        // is one IN PROGRESS & HOLD and other are higher (completed)
        $vs = 'wo_vendor_status.';

        return (bool)
        (
            $this->getVendStatusCount('in_progress_and_hold') > 0
            && (
                ($this->getVendStatusCount('in_progress_and_hold')
                    == $this->totalActVendCount)
                || (
                    empty($this->vendStatusCount[$vs.'in_progress'])
                    && empty($this->vendStatusCount[$vs.'confirmed'])
                    && empty($this->vendStatusCount[$vs.'assigned'])
                    && empty($this->vendStatusCount[$vs.'issued'])
                )
            )
        );
    }

    /**
     * Verify if work order status should be changed to In progress
     *
     * @return bool
     */
    protected function shouldUpdateToInProgress()
    {
        // In Progress - all vendors are IN PROGRESS OR there is one
        // IN PROGRESS and other are higher (in progress & hold, completed)
        $vs = 'wo_vendor_status.';

        return (bool)
        (
            $this->getVendStatusCount('in_progress') > 0
            && (
                ($this->getVendStatusCount('in_progress')
                    == $this->totalActVendCount)
                || (
                    empty($this->vendStatusCount[$vs.'confirmed'])
                    && empty($this->vendStatusCount[$vs.'assigned'])
                    && empty($this->vendStatusCount[$vs.'issued'])
                )
            )
        );
    }

    /**
     * Verify if work order status should be changed to Confirmed
     *
     * @return bool
     */
    protected function shouldUpdateToConfirmed()
    {
        // In Progress - all vendors are IN PROGRESS OR there is one
        // IN PROGRESS and other are higher (in progress & hold, completed)
        $vs = 'wo_vendor_status.';

        return (bool)
        (
            $this->getVendStatusCount('confirmed') > 0
            && (
                ($this->getVendStatusCount('confirmed')
                    == $this->totalActVendCount)
                || (
                    empty($this->vendStatusCount[$vs.'assigned'])
                    && empty($this->vendStatusCount[$vs.'issued'])
                )
            )
        );
    }

    /**
     * Verify if work order status should be changed to Issued
     *
     * @return bool
     */
    protected function shouldUpdateToIssued()
    {
        // Previous version: Issued Or some vendors are Canceled
        // New: Issued - at least one Issued vendor and  no Assigned
        $vs = 'wo_vendor_status.';

        return (bool)
        (
            $this->getVendStatusCount('issued') > 0
            && empty($this->vendStatusCount[$vs.'assigned'])
        );
    }

    /**
     * Verify if work order status should be changed to Assigned
     *
     * @return bool
     */
    protected function shouldUpdateToAssigned()
    {
        // Assigned in CRM - there is at least one vendor that has status Assigned
        return (bool) ($this->getVendStatusCount('assigned') > 0);
    }

    /**
     * Verify if actual completion date should be updated
     *
     * @return bool
     */
    protected function shouldUpdateCompletionDate()
    {
        return (bool) (
            isEmptyDateTime($this->wo->getActualCompletionDate())
            &&
            $this->config->get('system_settings.work_order_completion_date_on_status_change')
            == 1
        );
    }

    /**
     * Get vendor status count for given type - if there are no vendors for
     * given type 0 will be returned
     *
     * @param string $type
     *
     * @return int
     */
    protected function getVendStatusCount($type)
    {
        $vs = 'wo_vendor_status.';

        return isset($this->vendStatusCount[$vs.$type])
            ? $this->vendStatusCount[$vs.$type] : 0;
    }

    /**
     * Update work order status to given status
     *
     * @param string $status
     *
     * @return bool
     */
    protected function updateWoStatus($status)
    {
        return $this->woRepo->updateStatus(
            $this->wo,
            $this->woStat['wo_status.'.$status]
        );
    }

    /**
     * Verify if work order has NOT given status
     *
     * @param string $status
     *
     * @return bool
     */
    protected function hasNotWoStatus($status)
    {
        return !$this->hasWoStatus($status);
    }

    /**
     * Verify if work order has given status
     *
     * @param string $status
     *
     * @return bool
     */
    protected function hasWoStatus($status)
    {
        return (bool) ($this->wo->getWoStatusTypeId()
            == $this->woStat['wo_status.'.$status]);
    }

    /**
     * Set prefix that will be used for all messages
     */
    protected function setLogMessagePrefix()
    {
        $this->messagePrefix
            = "WO_ID: {$this->wo->getId()}\n".
            "OLD_STATUS: {$this->wo->getWoStatusTypeId()}\n".
            "TOTAL_VENDOR: {$this->totalVendCount}\n".
            "ACTIVE_VENDOR: {$this->totalActVendCount}\n".
            'VENDOR_STATUS_COUNT: '.print_r($this->vendStatusCount, true);
    }

    /**
     * Add text to message prefix that will be used to prepend final message
     *
     * @param string $message
     */
    protected function prependToMessage($message)
    {
        $this->messagePrefix .= $message;
    }

    /**
     * Log message
     *
     * @param $message
     */
    protected function log($message)
    {
        $this->app['logger']->log(
            $this->messagePrefix."\n".$message,
            'wo_status_log'
        );
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
        return $this->woRepo->getRepository($repositoryName, $moduleName);
    }
}
