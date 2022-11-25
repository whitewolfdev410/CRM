<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\CustomerSettings\Models\CustomerSettings;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsRepository;
use App\Modules\ExternalServices\Exceptions\NotImplementedException;
use App\Modules\ExternalServices\WorkOrderProvider;
use App\Modules\ExternalServices\WorkOrderUpdater;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Contracts\Container\Container;

class WorkOrderClientService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * Communication system lists
     *
     * @var array
     */
    protected $communicationSystems = [
        'Facility Maintenance',
        'Big Sky',
        'Service Channel',
        'Affiliate Market Place Support',
        'Market Place Support',
        'Work Oasis',
        'Work Order Network',
        'FM Pilot2',
        'QsiFacilities'
    ];

    /**
     * @var CustomerSettingsRepository
     */
    protected $customerSettingsRepository;

    /**
     * @var WorkOrderProvider
     */
    protected $workOrderProvider;
    
    /**
     * @var WorkOrderRepository
     */
    protected $workOrderRepository;

    /**
     * @var WorkOrderUpdater
     */
    protected $workOrderUpdater;

    /**
     * Initialize class
     *
     * @param Container                  $app
     * @param CustomerSettingsRepository $customerSettingsRepository
     * @param WorkOrderProvider          $workOrderProvider
     * @param WorkOrderRepository        $workOrderRepository
     * @param WorkOrderUpdater           $workOrderUpdater
     */
    public function __construct(
        Container $app,
        CustomerSettingsRepository $customerSettingsRepository,
        WorkOrderProvider $workOrderProvider,
        WorkOrderRepository $workOrderRepository,
        WorkOrderUpdater $workOrderUpdater
    ) {
        $this->app = $app;
        $this->customerSettingsRepository = $customerSettingsRepository;
        $this->workOrderProvider = $workOrderProvider;
        $this->workOrderRepository = $workOrderRepository;
        $this->workOrderUpdater = $workOrderUpdater;
    }

    /**
     * Get work order client IVR
     *
     * @param int $workOrderId
     *
     * @return array
     * @throws NotImplementedException
     */
    public function getIvr($workOrderId)
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->workOrderRepository->find($workOrderId);

        /** @var null|string $communicationSystem */
        $communicationSystem = $this->getUsedCommunicationSystem($workOrder);
        $communicationSystemForUpdateWorkOrder = [
            'Big Sky',
            'Service Channel',
            'Affiliate Market Place Support',
            'Work Order Network',
            'FM Pilot2',
            'QsiFacilities'
        ];
        
        if ($communicationSystem && in_array($communicationSystem, $communicationSystemForUpdateWorkOrder)) {
            $ivrColumns = ['username', 'caller_id', 'date_time', 'check_in_out', 'work_type', 'status', 'hours'];
            $laborColumns = ['name', 'work_date', 'time_in', 'time_out', 'reg_hrs', 'prem_hrs', 'techs_number'];

            $result = $this->workOrderUpdater->updateWorkOrder($workOrder)->getAttributes();

//            if ($result['ivr']) {
//                $result['ivr'] = html2array($result['ivr'], $ivrColumns, 2);
//            }
//
//            if ($result['labor']) {
//                $result['labor'] = html2array($result['labor'], $laborColumns, 2);
//            }

            return $result;
        } else {
            throw new NotImplementedException;
        }
    }

    /**
     * Get work order client note
     *
     * @param int $workOrderId
     *
     * @return array
     * @throws NotImplementedException
     */
    public function getNote($workOrderId)
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->workOrderRepository->find($workOrderId);

        /** @var null|string $communicationSystem */
        $communicationSystem = $this->getUsedCommunicationSystem($workOrder);

        if ($communicationSystem) {
            return [
                'data' => trim($this->workOrderProvider->getNotes($workOrder))
            ];
        } else {
            throw new NotImplementedException;
        }
    }
    
    /**
     * Get used communication system for work order
     *
     * @param WorkOrder $workOrder
     *
     * @return null|string
     */
    private function getUsedCommunicationSystem(WorkOrder $workOrder)
    {
        $companyPersonId = $workOrder->getCompanyPersonId();

        /** @var CustomerSettings $customerSettings */
        $customerSettings = $this->customerSettingsRepository->getForPerson($companyPersonId);
        if ($customerSettings) {
            $metaData = json_decode($customerSettings->getMetaData(), true);

            if (isset($metaData['Communication_system?']['answer'])) {
                foreach ($this->communicationSystems as $communicationSystem) {
                    if ($metaData['Communication_system?']['answer'] === $communicationSystem) {
                        return $communicationSystem;
                    }
                }
            }
        }

        return null;
    }
}
