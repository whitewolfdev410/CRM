<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\CalendarEvent\Models\CalendarEventTemplate;
use App\Modules\CalendarEvent\Repositories\CalendarEventTemplateRepository;
use App\Modules\WorkOrder\Http\Requests\WorkOrderTemplateRequest;
use App\Modules\WorkOrder\Models\LinkPersonWoTemplate;
use App\Modules\WorkOrder\Models\WorkOrderRepeat;
use App\Modules\WorkOrder\Models\WorkOrderTemplate;
use App\Modules\WorkOrder\Repositories\LinkPersonWoTemplateRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepeatRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderTemplateRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderTemplateService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var CalendarEventTemplateRepository
     */
    protected $calendarEventTemplateRepository;

    /**
     * @var LinkPersonWoTemplateRepository
     */
    protected $linkPersonWoTemplateRepository;

    /**
     * @var WorkOrderRepeatRepository
     */
    protected $workOrderRepeatRepository;

    /**
     * @var WorkOrderTemplateRepository
     */
    protected $workOrderTemplateRepository;

    /**
     * WorkOrderService constructor.
     *
     * @param  Container  $app
     * @param  CalendarEventTemplateRepository  $calendarEventTemplateRepository
     * @param  LinkPersonWoTemplateRepository  $linkPersonWoTemplateRepository
     * @param  WorkOrderRepeatRepository  $workOrderRepeatRepository
     * @param  WorkOrderTemplateRepository  $workOrderTemplateRepository
     */
    public function __construct(
        Container $app,
        CalendarEventTemplateRepository $calendarEventTemplateRepository,
        LinkPersonWoTemplateRepository $linkPersonWoTemplateRepository,
        WorkOrderRepeatRepository $workOrderRepeatRepository,
        WorkOrderTemplateRepository $workOrderTemplateRepository
    ) {
        $this->app = $app;
        $this->calendarEventTemplateRepository = $calendarEventTemplateRepository;
        $this->linkPersonWoTemplateRepository = $linkPersonWoTemplateRepository;
        $this->workOrderRepeatRepository = $workOrderRepeatRepository;
        $this->workOrderTemplateRepository = $workOrderTemplateRepository;
    }

    /**
     * @param  string  $string
     *
     * @return array
     */
    public function getRequestRules(string $string)
    {
        /** @var WorkOrderDataService $workOrderDataService */
        $workOrderDataService = app(WorkOrderDataService::class);

        $workOrderTemplateRequest = new WorkOrderTemplateRequest();

        $rules = $workOrderTemplateRequest->getFrontendRules();

        if (isset($rules['company_person_id'])) {
            $rules['company_person_id']['data'] = $workOrderDataService->getCompanyList()[0];
        }

        if (isset($rules['estimated_time'])) {
            $rules['estimated_time']['data'] = $workOrderDataService->getEstimatedTimeList();
        }

        if (isset($rules['project_manager_person_id'])) {
            $rules['project_manager_person_id']['data'] = $workOrderDataService->getProjectManagerList();
        }
        
        $types = $workOrderDataService->getTypes([
            'bill_status_type_id',
            'crm_priority_type_id',
            'invoice_status_type_id',
            'parts_status_type_id',
            'quote_status_type_id',
            'tech_trade_type_id',
            'trade_type_id',
            'via_type_id',
            'wo_status_type_id',
            'wo_type_id',
        ]);

        foreach ($types as $key => $values) {
            if (isset($rules[$key])) {
                $rules[$key]['data'] = $values;
            }
        }

        $range = [
            ['label' => 'day(s)', 'value' => 'DAY'],
            ['label' => 'week(s)', 'value' => 'WEEK'],
            ['label' => 'month(s)', 'value' => 'MONTH']
        ];

        $period = [
            ['label' => 'earlier', 'value' => '-'],
            ['label' => 'later', 'value' => '+'],
            ['label' => 'custom', 'value' => 'custom', 'extra_options' => [
                ['label' => 'end_of_month', 'value' => 'LAST_MONTH_DAY']
            ]]
        ];
        
        $rules['received_date_interval_range']['data'] = $range;
        $rules['received_date_interval_period']['data'] = $period;
        $rules['expected_completion_date_interval_range']['data'] = $range;
        $rules['expected_completion_date_interval_period']['data'] = $period;

        $rules['work_order_repeat.interval_keyword']['data'] = $range;
        
        return $rules;
    }

    /**
     * @param  array  $input
     *
     * @return WorkOrderTemplate
     * @throws \Exception
     */
    public function create(array $input)
    {
        DB::beginTransaction();

        try {
            /** @var WorkOrderTemplate $workOrderTemplate */
            $workOrderTemplate = $this->workOrderTemplateRepository->create($input);

            $
            
            $this->saveWorkOrderTemplateDependency($workOrderTemplate, $input);

            DB::commit();

            return $workOrderTemplate;
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * @param  int  $workOrderTemplateId
     * @param  array  $input
     *
     * @return WorkOrderTemplate
     * @throws \Exception
     */
    public function update(int $workOrderTemplateId, array $input)
    {
        DB::beginTransaction();

        try {
            /** @var WorkOrderTemplate $workOrderTemplate */
            $workOrderTemplate = $this->workOrderTemplateRepository->updateWithIdAndInput($workOrderTemplateId, $input);

            $this->saveWorkOrderTemplateDependency($workOrderTemplate, $input);

            DB::commit();

            return $workOrderTemplate;
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * @param $workOrderTemplateId
     *
     * @return bool
     * @throws \Exception
     */
    public function destroy($workOrderTemplateId)
    {
        $this->workOrderTemplateRepository->find($workOrderTemplateId);
        
        DB::beginTransaction();

        try {
            $this->deleteTasks($workOrderTemplateId);
            $this->deleteVendor($workOrderTemplateId);
            $this->deleteRecurring($workOrderTemplateId);
            
            $this->workOrderTemplateRepository->destroy($workOrderTemplateId);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * @param  WorkOrderTemplate  $workOrderTemplate
     * @param  array  $input
     */
    private function saveWorkOrderTemplateDependency(WorkOrderTemplate $workOrderTemplate, array $input)
    {
        $workOrderTemplateId = $workOrderTemplate->getId();

        $lpWoMap = [];
        $lpWoPersonMap = [];

        if (!empty($input['vendor_to_assign'])) {
            foreach ($input['vendor_to_assign'] as $vendor) {
                $linkPersonWoTemplate = $this->saveVendor($workOrderTemplateId, $vendor, $input['description'] ?? null);
                if ($linkPersonWoTemplate) {
                    $lpWoMap[$vendor['lpwo_id']] = $linkPersonWoTemplate->getId();
                    $lpWoPersonMap[$vendor['lpwo_id']] = $linkPersonWoTemplate->person_id;
                }
            }
        }

        if (!empty($input['task_to_create'])) {
            foreach ($input['task_to_create'] as $task) {
                $this->saveTask($workOrderTemplateId, $task, $lpWoMap, $lpWoPersonMap);
            }
        }

        if (!empty($input['is_recurring'])) {
            $this->saveRecurring($workOrderTemplateId, $input['work_order_repeat']);
        } else {
            $this->deleteRecurring($workOrderTemplateId);
        }
    }

    /**
     * @param $workOrderTemplateId
     * @param $vendor
     * @param  null  $qbInfo
     *
     * @return LinkPersonWoTemplate|boolean
     */
    private function saveVendor($workOrderTemplateId, $vendor, $qbInfo = null)
    {
        $vendor['work_order_template_id'] = $workOrderTemplateId;

        if (empty($vendor['type'])) {
            $vendor['type'] = 'work';
        }

        if (is_null($vendor['qb_info'])) {
            $vendor['qb_info'] = $qbInfo ?? '';
        }
        
        if (!empty($vendor['id'])) {
            if (!empty($vendor['is_deleted'])) {
                try {
                    $this->linkPersonWoTemplateRepository->destroy($vendor['id']);
                } catch (\Exception $exception) {
                    Log::error('Cannot remove link person wo template', $exception->getTrace());
                }

                return false;
            } else {
                $linkPersonWoTemplate = $this->linkPersonWoTemplateRepository
                    ->updateWithIdAndInput($vendor['id'], $vendor);
            }
        } else {
            $linkPersonWoTemplate = $this->linkPersonWoTemplateRepository->create($vendor);
        }

        /** @var LinkPersonWoTemplate $linkPersonWoTemplate */
        return $linkPersonWoTemplate;
    }

    /**
     * @param $workOrderTemplateId
     * @param $task
     * @param  array  $lpWoMap
     * @param  array  $lpWoPersonMap
     *
     * @return CalendarEventTemplate|false
     */
    private function saveTask($workOrderTemplateId, $task, array $lpWoMap = [], array $lpWoPersonMap = [])
    {
        $task['work_order_template_id'] = $workOrderTemplateId;

        if (empty($task['duration'])) {
            $task['duration'] = '00:15:00';
        }

        if (empty($task['type'])) {
            $task['type'] = 'wo_task';
        }

        $task['tablename'] = empty($task['lpwo_id']) ? 'work_order_template' : 'link_person_wo_template';
        $task['record_id'] = empty($task['lpwo_id']) ? $workOrderTemplateId : ($lpWoMap[$task['lpwo_id']] ?? null);
        $task['assigned_to'] = $lpWoPersonMap[$task['lpwo_id']] ?? null;

        $hotTypeId = getTypeIdByKey('task.hot');
        
        if (!empty($task['is_hot'])) {
            $task['type_id'] = $hotTypeId;
            $task['hot_type_id'] = getTypeIdByKey('hot_type.internal');
        } else {
            $task['type_id'] = getTypeIdByKey('task.not_hot');
        }

        $task['status_type_id'] = 0;

        if (!empty($task['id'])) {
            if (!empty($task['is_deleted'])) {
                try {
                    $this->calendarEventTemplateRepository->destroy($task['id']);
                } catch (\Exception $exception) {
                    Log::error('Cannot remove calendar event template', $exception->getTrace());
                }

                return false;
            } else {
                $calendarEventTemplate = $this->calendarEventTemplateRepository
                    ->updateWithIdAndInput($task['id'], $task);
            }
        } else {
            $calendarEventTemplate = $this->calendarEventTemplateRepository->create($task);
        }

        /** @var CalendarEventTemplate $calendarEventTemplate */
        return $calendarEventTemplate;
    }

    /**
     * @param $workOrderTemplateId
     * @param $workOrderRepeat
     *
     * @return WorkOrderRepeat
     */
    private function saveRecurring($workOrderTemplateId, $workOrderRepeat): WorkOrderRepeat
    {
        $workOrderRepeat['work_order_template_id'] = $workOrderTemplateId;
        $workOrderRepeat['number_remaining'] = -1;
        $workOrderRepeat['interval_keyword'] = strtolower($workOrderRepeat['interval_keyword']);
        
        $dbWorkOrderRepeat = $this->workOrderRepeatRepository->getByWorkOrderTemplateId($workOrderTemplateId);
        if ($dbWorkOrderRepeat) {
            $model = $this->workOrderRepeatRepository->updateWithIdAndInput($dbWorkOrderRepeat->getId(), $workOrderRepeat);
        } else {
            $model = $this->workOrderRepeatRepository->create($workOrderRepeat);
        }

        /** @var WorkOrderRepeat $model */
        return $model;
    }

    /**
     * @param $workOrderTemplateId
     *
     * @return bool
     */
    private function deleteVendor($workOrderTemplateId): bool
    {
        $linkPersonWoTemplates = $this->linkPersonWoTemplateRepository->getByWorkOrderTemplateId($workOrderTemplateId);
        if ($linkPersonWoTemplates) {
            foreach ($linkPersonWoTemplates as $linkPersonWoTemplate) {
                try {
                    /** @var LinkPersonWoTemplate $linkPersonWoTemplate */
                    $linkPersonWoTemplate->delete();
                } catch (\Exception $exception) {
                    Log::error('Cannot remove link person wo template', $exception->getTrace());
                }
            }
        }

        return true;
    }

    /**
     * @param $workOrderTemplateId
     *
     * @return bool
     */
    private function deleteTasks($workOrderTemplateId): bool
    {
        $vendors = $this->linkPersonWoTemplateRepository->getByWorkOrderTemplateId($workOrderTemplateId);
        
        $calendarEventTemplates = $this->calendarEventTemplateRepository
            ->getByVendorsOrWorkOrderTemplateId($vendors, $workOrderTemplateId);
        
        if ($calendarEventTemplates) {
            foreach ($calendarEventTemplates as $calendarEventTemplate) {
                try {
                    /** @var CalendarEventTemplate $calendarEventTemplate */
                    $calendarEventTemplate->delete();
                } catch (\Exception $exception) {
                    Log::error('Cannot remove calendar event template', $exception->getTrace());
                }
            }
        }

        return true;
    }
    
    /**
     * @param $workOrderTemplateId
     *
     * @return bool
     */
    private function deleteRecurring($workOrderTemplateId): bool
    {
        $workOrderRepeats = $this->workOrderRepeatRepository->getByWorkOrderTemplateId($workOrderTemplateId);
        if ($workOrderRepeats) {
            foreach ($workOrderRepeats as $workOrderRepeat) {
                try {
                    /** @var WorkOrderRepeat $workOrderRepeat */
                    $workOrderRepeat->delete();
                } catch (\Exception $exception) {
                    Log::error('Cannot remove work order repeat', $exception->getTrace());
                }
            }
        }

        return true;
    }

    public function show($workOrderTemplateId)
    {
        $result = $this->workOrderTemplateRepository->show($workOrderTemplateId);
        
        $result['item']['vendor_to_assign'] = $this->linkPersonWoTemplateRepository
            ->getByWorkOrderTemplateId($workOrderTemplateId);
        
        $result['item']['task_to_create'] = $this->calendarEventTemplateRepository
            ->getByVendorsOrWorkOrderTemplateId($result['item']['vendor_to_assign'], $workOrderTemplateId);
        
        $result['item']['work_order_repeat'] = $this->workOrderRepeatRepository
            ->getByWorkOrderTemplateId($workOrderTemplateId);

        $result['item']['is_recurring'] = $result['item']['work_order_repeat'] ? 1 : 0;
        
        return $result;
    }
}
