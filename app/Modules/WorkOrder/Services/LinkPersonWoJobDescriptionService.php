<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Crm;
use App\Modules\Invoice\Http\Requests\InvoiceTemplateRequest;
use App\Modules\System\Repositories\SystemSettingsRepository;
use App\Modules\System\Services\SystemSettingsService;
use App\Modules\WorkOrder\Exceptions\LpWoMissingEcdException;
use App\Modules\WorkOrder\Exceptions\LpWoMissingWorkOrderException;
use App\Modules\WorkOrder\Http\Requests\LinkPersonWoJobDescriptionRequest;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\QbStatus;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

class LinkPersonWoJobDescriptionService
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
     * LinkPersonWoJobDescriptionService constructor.
     *
     * @param Container $app
     * @param LinkPersonWoRepository $lpWoRepo
     */
    public function __construct(
        Container $app,
        LinkPersonWoRepository $lpWoRepo
    ) {
        $this->app = $app;
        $this->lpWoRepo = $lpWoRepo;
    }

    /**
     * Gets data for link person wo description action
     *
     * @param int $lpWoId
     *
     * @return array
     * @throws LpWoMissingWorkOrderException|LpWoMissingEcdException
     */
    public function get($lpWoId)
    {
        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpWoRepo->findWithWorkOrder($lpWoId);

        /** @var WorkOrder $workOrder */
        $workOrder = $lpWo->workOrder;

        if (isEmptyDate($workOrder->getExpectedCompletionDate())) {
            /** @var LpWoMissingEcdException $exp */
            $exp = $this->app->make(LpWoMissingEcdException::class);
            $exp->setData([
                'work_order_id' => $workOrder->getId(),
                'link_person_wo_id' => $lpWo->getId(),
            ]);
            throw $exp;
        }

        $canIssue = $lpWo->getStatusTypeId() ==
            getTypeIdByKey('wo_vendor_status.assigned');

        $qbStatus = $this->calculateQbStatus($lpWo);

        // In old CRM in work_order_data.php there was also something like this
        // but it was not used by reference so it was not affecting displayed
        // data
        // $data['not_to_exceed'] = $data['not_to_exceed'] * 0.60;

        if ($qbStatus != QbStatus::MISSING) {
            $qbInfo = $lpWo->getQbInfo();
        } else {
            /** @var  LinkPersonWoQbInfoService $service */
            $service = $this->app->make(LinkPersonWoQbInfoService::class);
            $qbInfo = $service->getQbInfo($lpWo, $workOrder);
        }

        $item = $this->parseLpWo($lpWo);
        
        $defaultEstimatedTime = SystemSettingsService::getValueByKey('crm_config.default_estimated_work_time', '00:00:00');
        
        return [
            'item' => $item,
            'tag' => config('app.crm_user'),
            'qb_status' => $qbStatus,
            'qb_info' => $qbInfo,
            'default_estimated_time' => $defaultEstimatedTime,
            'can_issue' => $canIssue,
        ];
    }

    /**
     * Saves link person wo job description data
     *
     * @param int $lpWoId
     * @param array $input
     *
     * @return array
     */
    public function save($lpWoId, array $input)
    {
        $data = [];
        $data['qb_info'] = $input['qb_info'];
        $data['special_type'] = $input['special_type'];
        $data['estimated_time'] = (empty($input['estimated_time'])) ? '00:00:00' : $input['estimated_time'];
        $data['send_past_due_notice'] = $input['send_past_due_notice'];

        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpWoRepo->find($lpWoId);
        
        /** @var Crm $crm */
        $crm = $this->app->make(Crm::class);

        // for GFS extra fields will be also updated
        if ($crm->is('gfs')) {
            $data['qb_nte'] = $input['qb_nte'] > 0 ? $input['qb_nte'] : 0;
            $data['qb_ecd'] = !empty($input['qb_ecd']) ? $input['qb_ecd'] : null;
            $data['completed_pictures_received'] = $input['completed_pictures_received'];
            $data['completed_pictures_required'] = $input['completed_pictures_required'];
        }

        if ($crm->is('bfc')) {
            $data['qb_nte'] = $input['qb_nte'] > 0 ? $input['qb_nte'] : 0;
            $data['qb_ecd'] = !empty($input['qb_ecd']) ? $input['qb_ecd'] : null;

            $statusTypeId = getTypeIdByKey('wo_vendor_status.assigned');
            if (!empty($input['assigned_person_id']) && $input['assigned_person_id'] != $lpWo->person_id && $lpWo->status_type_id == $statusTypeId) {
                $lpWo->person_id = (int)$input['assigned_person_id'];
            }

            $lpWo->primary_technician = $input['primary_technician'];
            $lpWo->tech_status_type_id = $input['tech_status_type_id'];
            $lpWo->is_ghost = isset($input['is_ghost']) ? $input['is_ghost'] : 0;
        }
        
        if ($crm->is('mighty')) {
            $data['assets_enabled'] = !empty($input['assets_enabled']) ? $input['assets_enabled'] : 0;
        }
        
        /** @var WorkOrderRepository $woRepo */
        $woRepo = $this->app->make(WorkOrderRepository::class);
        
        $wo = $woRepo->find($lpWo->getWorkOrderId());

        $changes = [];

        DB::transaction(function () use (
            &$lpWo,
            $woRepo,
            $data,
            $wo,
            &$changes,
            $input
        ) {
            // update this link person wo first
            /** @var LinkPersonWo $lpWo */
            $lpWo = $this->lpWoRepo->internalUpdate($lpWo, $data);

            // find any lpwo of the same person for the same work order
            $links = $this->lpWoRepo->getForWoAndPerson(
                $lpWo->getWorkOrderId(),
                $lpWo->getPersonId()
            );

            // update all those lpwo to the same special_type as the one we've
            // just updated
            $updateData = ['special_type' => $lpWo->getSpecialType()];
            /** @var LinkPersonWo $link */
            foreach ($links as $link) {
                // if it's the same we've just edited - skip it
                if ($link->getId() == $lpWo->getId()) {
                    continue;
                }
                $this->lpWoRepo->internalUpdate($link, $updateData);
            }

            /** @var LinkPersonWoStatusService $service */
            $service = $this->app->make(LinkPersonWoStatusService::class);

            // if issue, we try to issue this lpwo
            if (isset($input['issue']) && $input['issue'] == 1) {
                $lpWo = $service->updateStatus($lpWo, 'issued', true, false);
            }

            // now we want to get work order changes
            $wo2 = $woRepo->find($lpWo->getWorkOrderId());
            $changes = $service->calculateChanges($wo, $wo2);
        });

        $item = $this->parseLpWo($lpWo);
        
        return [$item, $changes];
    }

    /**
     * @return array
     */
    public function getRequestRules()
    {
        $linkPersonWoJobDescriptionRequest = new LinkPersonWoJobDescriptionRequest();
        
        return [
            'fields'  => $linkPersonWoJobDescriptionRequest->getFrontendRules()
        ];
    }
    
    /**
     * Calculates value of qb status
     *
     * @param LinkPersonWo $lpWo
     *
     * @return int
     */
    protected function calculateQbStatus(LinkPersonWo $lpWo)
    {
        if ($lpWo->getQbRef() != '') {
            return QbStatus::SENT;
        } elseif ($lpWo->getQbInfo() != '') {
            return QbStatus::NOT_SENT;
        }

        return QbStatus::MISSING;
    }

    private function parseLpWo(LinkPersonWo $lpWo)
    {
        return [
            'qb_ref' => $lpWo->qb_ref,
            'qb_transfer_date' => $lpWo->qb_transfer_date,
            'qb_info' => $lpWo->qb_info,

            'link_person_wo_id' => $lpWo->link_person_wo_id,
            'work_order_id' => $lpWo->work_order_id,
            'assigned_person_id' => $lpWo->person_id,
            'primary_technician' => $lpWo->primary_technician,
            'tech_status_type_id' => $lpWo->tech_status_type_id,
            'job_type' => $lpWo->type,
            'special_type' => $lpWo->special_type,
            'estimated_time' => $lpWo->estimated_time,
            'qb_nte' => $lpWo->qb_nte,
            'qb_ecd' => $lpWo->qb_ecd,
            'scheduled_date' => $lpWo->scheduled_date,
            'scheduled_date_simple' => $lpWo->scheduled_date_simple,

            'send_past_due_notice' => $lpWo->send_past_due_notice,
            'completed_pictures_required' => $lpWo->completed_pictures_required,
            'completed_pictures_received' => $lpWo->completed_pictures_received
        ];
    }
}
