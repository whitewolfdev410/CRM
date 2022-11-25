<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\MsDynamics\Services\MsDynamicsService;
use App\Modules\TwilioMessage\Jobs\CallRackMaintenanceLimitExceeded;
use App\Modules\User\Repositories\UserDeviceRepository;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderRackMaintenance;
use App\Modules\WorkOrder\Models\WorkOrderRackMaintenanceItem;
use App\Modules\WorkOrder\Repositories\WorkOrderRackMaintenanceItemRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRackMaintenanceRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Services_Twilio;

class WorkOrderRackMaintenanceService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var WorkOrderRackMaintenanceRepository
     */
    protected $workOrderRackMaintenanceRepository;

    /**
     * @var WorkOrderRackMaintenanceItemRepository
     */
    protected $workOrderRackMaintenanceItemRepository;

    /**
     * @var WorkOrderRepository
     */
    protected $workOrderRepository;

    /**
     * @var string
     */
    private $notificationEmailAddress = 'BFC-3C@bfcsolutions.com';

    /**
     * Initialize class
     *
     * @param Container                              $app
     * @param WorkOrderRackMaintenanceRepository     $workOrderRackMaintenanceRepository
     * @param WorkOrderRackMaintenanceItemRepository $workOrderRackMaintenanceItemRepository
     * @param WorkOrderRepository                    $workOrderRepository
     */
    public function __construct(
        Container $app,
        WorkOrderRackMaintenanceRepository $workOrderRackMaintenanceRepository,
        WorkOrderRackMaintenanceItemRepository $workOrderRackMaintenanceItemRepository,
        WorkOrderRepository $workOrderRepository
    ) {
        $this->app = $app;
        $this->workOrderRackMaintenanceRepository = $workOrderRackMaintenanceRepository;
        $this->workOrderRackMaintenanceItemRepository = $workOrderRackMaintenanceItemRepository;
        $this->workOrderRepository = $workOrderRepository;
    }

    /**
     * Create new rack maintenance
     *
     * @param array $input
     *
     * @return WorkOrderRackMaintenance
     */
    public function createWorkOrderRackMaintenance(array $input)
    {
        if ($input['notification_sent']) {
            $this->sendNotificationForWorkWithoutStartedTimer($input);
        }

        if ($input['complete']) {
            $this->sendNotificationForCompletedRackMaintenance($input);
        }

        if ($input['no_rack']) {
            $this->sendNotificationForNoRack($input);
        }

        return $this->workOrderRackMaintenanceRepository->create($input);
    }

    /**
     * Create new rack maintenance item
     *
     * @param array $input
     *
     * @return WorkOrderRackMaintenanceItem
     */
    public function createWorkOrderRackMaintenanceItem(array $input)
    {
        if ($input['notification_sent']) {
            $this->sendNotificationForRackItemLimitExceeded($input);
        }

        return $this->workOrderRackMaintenanceItemRepository->create($input);
    }

    /**
     * Update rack maintenance
     *
     * @param integer $id
     * @param array   $input
     *
     * @return WorkOrderRackMaintenance
     */
    public function updateWorkOrderRackMaintenance($id, array $input)
    {
        $before = $this->workOrderRackMaintenanceRepository->findSoft($id);

        if (!$before['notification_sent'] && $input['notification_sent']) {
            $this->sendNotificationForWorkWithoutStartedTimer($input);
        }

        if (!$before['complete'] && $input['complete']) {
            $this->sendNotificationForCompletedRackMaintenance($input);
        }

        if (!$before['no_rack'] && $input['no_rack']) {
            $this->sendNotificationForNoRack($input);
        }

        return $this->workOrderRackMaintenanceRepository->updateWithIdAndInput($id, $input);
    }

    /**
     * Update rack maintenance item
     *
     * @param integer $id
     * @param array   $input
     *
     * @return WorkOrderRackMaintenanceItem
     */
    public function updateWorkOrderRackMaintenanceItem($id, array $input)
    {
        $before = $this->workOrderRackMaintenanceItemRepository->findSoft($id);

        if (!$before['notification_sent'] && $input['notification_sent']) {
            $this->sendNotificationForRackItemLimitExceeded($input);
        }

        return $this->workOrderRackMaintenanceItemRepository->updateWithIdAndInput($id, $input);
    }

    /**
     * @param array $input
     *
     * @return mixed
     */
    private function sendNotificationForRackItemLimitExceeded(array $input)
    {
        $data = $this->getTechAndSiteData($input['work_order_id']);
        $data['name'] = $input['name'];
        $data['start_at'] = $input['start_at'];
        $data['stop_at'] = $input['stop_at'];

        $bcc = Auth::user()->getEmail();

        $recipients = $this->notificationEmailAddress;
        $subject = $input['name'] . ' for Site ID ' . $data['site_id'] . ' has been down for 60 minutes';
        $view = 'emails.notifications.rack_item_limit_exceeded';

        $this->notifyByCall($data['site_id'], $data['name'], $data['work_order_number']);

        return Mail::queue($view, $data, function ($message) use ($recipients, $bcc, $subject) {
            $message
                ->to($recipients)
                ->bcc($bcc)
                ->subject($subject);
        });
    }

    /**
     * @param array $input
     *
     * @return mixed
     */
    private function sendNotificationForWorkWithoutStartedTimer(array $input)
    {
        $data = $this->getTechAndSiteData($input['work_order_id']);
        $data['start_at'] = $input['start_at'];
        $data['stop_at'] = $input['stop_at'];

        $recipients = $this->notificationEmailAddress;
        $subject = 'Racks timer for Site ID ' . $data['site_id'] . ' has not been started';
        $view = 'emails.notifications.rack_timer_not_started';

        return Mail::queue($view, $data, function ($message) use ($recipients, $subject) {
            $message
                ->to($recipients)
                ->subject($subject);
        });
    }

    /**
     * @param array $input
     *
     * @return mixed
     */
    private function sendNotificationForCompletedRackMaintenance(array $input)
    {
        $data = $this->getTechAndSiteData($input['work_order_id']);
        $data['start_at'] = $input['start_at'];
        $data['stop_at'] = $input['stop_at'];

        $data['items'] = $this->workOrderRackMaintenanceItemRepository->getItems(
            $input['work_order_id'],
            $input['link_person_wo_id']
        )->toArray();

        $recipients = $this->notificationEmailAddress;
        $subject = 'Rack Maintenance for Site ID ' . $data['site_id'] . ' has been completed';
        $view = 'emails.notifications.rack_maintenance_completed';

        return Mail::queue($view, $data, function ($message) use ($recipients, $subject) {
            $message
                ->to($recipients)
                ->subject($subject);
        });
    }

    /**
     * @param array $input
     *
     * @return mixed
     */
    private function sendNotificationForNoRack(array $input)
    {
        $data = $this->getTechAndSiteData($input['work_order_id']);
        $data['start_at'] = $input['start_at'];
        $data['stop_at'] = $input['stop_at'];

        $recipients = $this->notificationEmailAddress;
        $subject = 'No Rack Within Site ID ' . $data['site_id'];
        $view = 'emails.notifications.no_rack_within_site_id';

        return Mail::queue($view, $data, function ($message) use ($recipients, $subject) {
            $message
                ->to($recipients)
                ->subject($subject);
        });
    }

    /**
     * @param $workOrderId
     *
     * @return array
     */
    private function getTechAndSiteData($workOrderId)
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->workOrderRepository->findSoft($workOrderId);

        $personId = Auth::user()->getPersonId();

        return [
            'employee_id'       => app(MsDynamicsService::class)->getEmployeeIdByPersonId($personId),
            'person_id'         => $personId,
            'person_name'       => Auth::user()->getUsername(),
            'site_id'           => $workOrder->fin_loc,
            'work_order_number' => $workOrder->work_order_number
        ];
    }

    /**
     *
     * Notify users depending on $toNumber
     *
     * @param        $siteId
     * @param        $rackName
     * @param        $workOrderNumber
     */
    protected function notifyByCall($siteId, $rackName, $workOrderNumber)
    {
        try {
            $personId = Auth::user()->getPersonId();

            /** @var UserDeviceRepository $userDevice */
            $userDevice = app(UserDeviceRepository::class);

            $phoneNumber = $userDevice->getPhoneNumberByPersonId($personId);
            if ($phoneNumber) {
                $job = new CallRackMaintenanceLimitExceeded($phoneNumber, $siteId, $rackName, $workOrderNumber);

                app(Dispatcher::class)->dispatchNow($job);
            }
        } catch (\Exception $e) {
            Log::error('Cannot add CallRackMaintenanceLimitExceeded to queue', $e->getTrace());
        }
    }
}
