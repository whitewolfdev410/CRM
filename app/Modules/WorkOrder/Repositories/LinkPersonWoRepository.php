<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Activity\Models\Activity;
use App\Modules\Asset\Models\LinkAssetPersonWo;
use App\Modules\Bill\Models\Bill;
use App\Modules\CalendarEvent\Models\CalendarEvent;
use App\Modules\Email\Services\EmailSenderService;
use App\Modules\File\Models\File;
use App\Modules\HazardAssessments\Models\HazardAssessment;
use App\Modules\History\Models\History;
use App\Modules\History\Models\HistoryLpwoStatus;
use App\Modules\History\Models\HistoryLpwoTechStatus;
use App\Modules\History\Models\MergeHistory;
use App\Modules\Kb\Models\ArticleProgress;
use App\Modules\MsDynamics\Models\CompletedJobSms;
use App\Modules\Notification\Models\Notification;
use App\Modules\Person\Models\Person;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PushNotification\Services\PushNotificationAdderService;
use App\Modules\PushNotification\Services\PushNotificationSenderService;
use App\Modules\TimeSheet\Models\TimeSheet;
use App\Modules\Type\Models\Type;
use App\Modules\WorkOrder\Exceptions\LpWoInvalidVendorKindException;
use App\Modules\WorkOrder\Exceptions\LpWoMissingWorkOrderException;
use App\Modules\WorkOrder\Http\Requests\LinkPersonWoRequest;
use App\Modules\WorkOrder\Models\DataExchange;
use App\Modules\WorkOrder\Models\Exceptions;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\LinkPersonWoSchedule;
use App\Modules\WorkOrder\Models\LinkPersonWoStats;
use App\Modules\WorkOrder\Models\TechStatusHistory;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderLiveAction;
use App\Modules\WorkOrder\Models\WorkOrderLiveActionToOrder;
use App\Modules\WorkOrder\Models\WorkOrderRackMaintenance;
use App\Modules\WorkOrder\Models\WorkOrderRackMaintenanceItem;
use Carbon\Carbon;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * LinkPersonWo repository class
 */
class LinkPersonWoRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [
        'link_person_wo.person_id',
        'tech.custom_1',
        'tech.custom_3',
        'work_order_number'
    ];

    /**
     * {@inheritdoc}
     */
    protected $sortable = [
        'link_person_wo.person_id',
        'work_order_number',
    ];

    /**
     * Repository constructor
     *
     * @param  Container  $app
     * @param  LinkPersonWo  $linkPersonWo
     */
    public function __construct(Container $app, LinkPersonWo $linkPersonWo)
    {
        parent::__construct($app, $linkPersonWo);
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new LinkPersonWoRequest();

        return $req->getFrontendRules();
    }

    /**
     * Get number of techs in progress
     *
     * @return int
     */
    public function getTechsInProgressCount()
    {
        $type = $this->getRepository('Type');

        $progressStatus = $type->getColumnByKey('wo_vendor_status.in_progress');

        $model = $this->model->where('status_type_id', $progressStatus);

        return $model->count('link_person_wo_id');
    }

    /**
     * Assigned picked up person id for work order
     *
     * @param  int  $workOrderId
     * @param  int  $personId
     *
     * @return LinkPersonWo
     */
    public function assignPickedUp($workOrderId, $personId)
    {
        $type = $this->getRepository('Type');

        /** @var LinkPersonWo $model */
        $model = $this->newInstance();
        $model->work_order_id = $workOrderId;
        $model->person_id = $personId;
        $model->creator_person_id = $personId;
        $model->status_type_id = $type->getIdByKey('wo_vendor_status.confirmed');
        $model->bill_final = 0;
        $model->type = 'work';
        $model->is_hidden = 0;
        $model->priority = $this->getNewPriority($personId);
        $model->save();

        return $model;
    }

    /**
     * Assign vendor for work order
     *
     * @param  int  $workOrderId
     * @param  int  $personId
     * @param  int  $statusTypeId
     * @param  int  $estimatedTime
     * @param  int  $sendPastDueNotice
     * @param  string  $description
     *
     * @return LinkPersonWo
     */
    public function assignVendor(
        $workOrderId,
        $personId,
        $statusTypeId,
        $estimatedTime,
        $sendPastDueNotice,
        $description
    ) {
        /** @var LinkPersonWo $model */
        $model = $this->newInstance();
        $model->work_order_id = $workOrderId;
        $model->person_id = (int) $personId;
        $model->status_type_id = (int) $statusTypeId;
        $model->type = 'work';
        $model->bill_final = 0;
        $model->is_hidden = 0;
        $model->priority = $this->getNewPriority($personId);
        $model->estimated_time = $estimatedTime;
        $model->send_past_due_notice = $sendPastDueNotice;
        $model->qb_info = $description;
        $model->save();

        /** @var PushNotificationAdderService $pushNotificationAdderService */
        $pushNotificationAdderService = app(PushNotificationAdderService::class);
        $pushNotificationAdderService->technicianAssignedToWorkOrder($personId, $workOrderId);
        
        return $model;
    }

    
    /**
     * Get new priority and update in progress priorities
     *
     * @param $personId
     *
     * @return int
     */
    public function getNewPriorityWithUpdateInProgress($personId)
    {
        $priority = $this->getNewPriority($personId);
        $this->updateInProgressPriorities($personId);

        return $priority;
    }

    /**
     * Calculates new priority for $personId
     *
     * @param  int  $personId
     *
     * @return int
     */
    protected function getNewPriority($personId)
    {
        $min = $this->model->where('person_id', $personId)
            ->where('priority', '!=', 0)
            ->where('is_disabled', '!=', 1)->min('priority');

        return $min ? (int) $min : 2;
    }

    /**
     * Update priorities for not completed and not cancelled assignments
     *
     * @param  int  $personId
     * @param  null|int|array  $except
     */
    public function updateInProgressPriorities($personId, $except = null)
    {
        $type = $this->getRepository('Type');
        $completed = $type->getIdByKey('wo_vendor_status.completed');
        $cancelled = $type->getIdByKey('wo_vendor_status.canceled');

        $model = $this->model
            ->where('person_id', $personId)
            ->whereNotIn('status_type_id', [$completed, $cancelled]);
        if ($except) {
            if (!is_array($except)) {
                $except = [$except];
            }
            $model = $model->whereNotIn($this->model->getKeyName(), $except);
        }
        $links = $model->get();

        foreach ($links as $link) {
            ++$link->priority;
            $link->save();
        }
    }

    /**
     * Pagination - based on query url use either automatic paginator or
     * manual paginator
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  array  $order
     *
     * @return \Illuminate\Database\Eloquent\Collection|Paginator
     */
    public function paginate(
        $perPage = 50,
        array $columns = [
            'link_person_wo.*',
            'person_name(link_person_wo.person_id) as tech_name'
        ],
        array $order = []
    ) {
        $perPage = (int) $perPage;
        $input = $this->getInput();

        /** @var LinkPersonWo|Object $model */
        $model = $this->getModel();

        if (isset($input['person_id'])) {
            $model = $model->ofPerson((int) $input['person_id']);

            unset($input['person_id']);
            $this->setInput($input);
        }

        if (isset($input['work_order_id'])) {
            $model = $model->ofWorkOrder((int) $input['work_order_id']);
        }

        if (isset($input['with_work_order']) || isset($input['search'])) {
            $model = $model
                ->join(
                    'work_order',
                    'link_person_wo.work_order_id',
                    '=',
                    'work_order.work_order_id'
                )
                ->join(
                    'address as shop_address',
                    'work_order.shop_address_id',
                    '=',
                    'shop_address.address_id'
                )
                ->leftJoin(
                    'person as client',
                    'work_order.company_person_id',
                    '=',
                    'client.person_id'
                )
                ->leftJoin(
                    'person as tech',
                    'link_person_wo.person_id',
                    '=',
                    'tech.person_id'
                );

            $columns[] = 'work_order.work_order_number as work_order_number';
            $columns[] = 'work_order.expected_completion_date';
            $columns[] = 'person_name(client.person_id) as client_name';
            $columns[] = 'shop_address.address_name as shop_address_name';
            $columns[] = 'shop_address.city as shop_address_city';
            $columns[] = 'shop_address.state as shop_address_state';

            if (isset($input['state'])) {
                $model = $model
                    ->where('shop_address.state', '=', $input['state']);
            }

            if (!empty($input['search'])) {
                $model = $model
                    ->where('is_disabled', '<>', 1)
                    ->where(function ($query) use ($input) {
                        $query->where('work_order_number', 'LIKE', '%'.$input['search'].'%')
                            ->orWhere(DB::raw('link_person_wo_id'), 'LIKE', '%'.$input['search'].'%')
                            ->orWhere(DB::raw('tech.custom_1'), 'LIKE', '%'.$input['search'].'%')
                            ->orWhere(DB::raw('tech.custom_3'), 'LIKE', '%'.$input['search'].'%');
                    });
            }
        }

        $this->setRawColumns(true);
        $this->setWorkingModel($model);

        if (!empty($input['only_tech_name'])) {
            $columns = [
                'link_person_wo_id',
                'person_id',
                'person_name(link_person_wo.person_id) as tech_name'
            ];

            return parent::paginateSimple($perPage, $columns, $order);
        } else {
            return parent::paginate($perPage, $columns, $order);
        }
    }

    /**
     * Get list for work order
     *
     * @param  int  $workOrderId
     *
     * @return mixed
     */
    public function getListForWo($workOrderId)
    {
        return $this->model
            ->where('work_order_id', $workOrderId)
            ->selectRaw('link_person_wo_id, person_name(person_id) AS person_name')
            ->pluck('person_name', 'link_person_wo_id')->all();
    }

    /**
     * Get list for work orders
     *
     * @param  array  $workOrderIds
     *
     * @return mixed
     */
    public function getListForWoIds(array $workOrderIds)
    {
        if (!$workOrderIds) {
            return [];
        }
        
        $linkPersonGroupedByWo = [];
        
        $linkPersonWo = $this->model
            ->select([
                'work_order_id',
                'link_person_wo_id',
                'person_id',
                DB::raw('person_name(person_id) AS person_name'),
                DB::raw('t(status_type_id) AS status_type_value')
            ])
            ->whereIn('work_order_id', $workOrderIds)
            ->where('is_disabled', 0)
            ->get();
        
        foreach ($linkPersonWo as $item) {
            $linkPersonGroupedByWo[$item->work_order_id][] = [
                'link_person_wo_id' => $item->link_person_wo_id,
                'person_id' => $item->person_id,
                'person_name' => $item->person_name,
                'status_type_value' => $item->status_type_value
            ];
        }

        return $linkPersonGroupedByWo;
    }
    
    /**
     * Get list of ids for work order
     *
     * @param  int  $workOrderId
     *
     * @return mixed
     */
    public function getIdsForWo($workOrderId)
    {
        return $this->model
            ->where('work_order_id', $workOrderId)
            ->select('link_person_wo_id')
            ->pluck('link_person_wo_id')->all();
    }

    /**
     * Get list of ids for work order
     *
     * @param $workOrderNumber
     *
     * @return mixed
     */
    public function getIdsForWoNumber($workOrderNumber)
    {
        return $this->model
            ->join('work_order', 'link_person_wo.work_order_id', '=', 'work_order.work_order_id')
            ->where('work_order_number', $workOrderNumber)
            ->select('link_person_wo_id')
            ->pluck('link_person_wo_id')
            ->all();
    }

    
    /**
     * Get assigned link_person_wo entries for work order. IF $onPage is set to
     * not null data will be paginated using $onPage as number of items on page.
     * Otherwise all data will be returned
     *
     * @param  string  $workOrderId
     * @param  bool  $withPersonName
     * @param  bool  $withPersonKind
     * @param  null  $onPage
     *
     * @return Collection
     */
    public function getForWo(
        $workOrderId,
        $withPersonName = false,
        $withPersonKind = false,
        $onPage = null
    ) {
        $columns = ['*'];
        if ($withPersonName) {
            $columns[] = 'person_name(person_id) as person_name';
        }
        if ($withPersonKind) {
            $columns[]
                = '(SELECT kind FROM person p WHERE
                        p.person_id = link_person_wo.person_id) as person_kind';
        }

        $model = $this->model
            ->selectRaw(implode(', ', $columns))
            ->where('work_order_id', $workOrderId);

        if ($onPage === null) {
            return $model->get();
        }

        return $model->paginate($onPage);
    }

    /**
     * Get assigned vendors and techs for work order
     *
     * @param  int  $workOrderId
     *
     * @return LinkPersonWo
     */
    public function getVendorsTechs($workOrderId)
    {
        $model = $this->model;
        $table = $this->model->getTable();
        $key = $this->model->getKeyName();

        $type = $this->getRepository('Type');

        $model = $model
            ->leftJoin(
                'type AS t',
                "{$table}.status_type_id",
                '=',
                't.type_id'
            )
            ->leftJoin(
                "{$table} as recall",
                "{$table}.recall_link_person_wo_id",
                '=',
                "recall.{$key}"
            )
            ->leftJoin(
                'person',
                'person.person_id',
                '=',
                "{$table}.person_id"
            )
            ->where("{$table}.work_order_id", $workOrderId);

        $columns = [
            "{$table}.qb_ref",
            "{$table}.qb_transfer_date",
            "{$table}.person_id",
            "{$table}.vendor_notes",
            "{$table}.is_hidden",
            "person_name({$table}.creator_person_id) AS creator",
            "{$table}.created_date AS full_created_date",
            "{$table}.is_disabled",
            "person_name({$table}.disabling_person_id) AS disabling_person",
            "{$table}.disabled_date",
            'recall.work_order_id AS recall_work_order_id',
            't.type_value AS vendor_status',
            't.type_key AS vendor_status_key',
            "{$table}.status_type_id",
            "{$table}.special_type",
            "{$table}.link_person_wo_id",
            "{$table}.confirmed_date",
            "{$table}.type",
            "{$table}.scheduled_date",
            "{$table}.estimated_time",
            'person.notes',
            "person_name({$table}.person_id) AS person_name",
            "IF(recall.person_id IS NULL,'',
                (SELECT person_name(recall.person_id))) AS recall_person_name",
            "IF(recall.person_Id IS NULL,'',
                (SELECT rwo.work_order_number FROM work_order rwo WHERE
                    rwo.work_order_id = recall.work_order_id))
                AS recall_work_order_number",
            "(SELECT value FROM contact WHERE
              contact.person_id={$table}.person_id AND contact.type_id=
              {$type->getIdByKey('contact.phone')}
             ORDER BY is_default DESC LIMIT 1) AS phone",
            "(SELECT status_type_id FROM person WHERE
                person.person_id={$table}.person_id LIMIT 1)
                AS vendor_person_status",
            "(SELECT type_id FROM person WHERE
                person.person_id={$table}.person_id LIMIT 1)
                AS vendor_type",
            "{$table}.issued_date",
            "{$table}.completed_date AS completion_date",
            "(SELECT sum(b.amount) FROM bill b WHERE
               b.link_person_wo_id = {$table}.{$key} LIMIT 1)
               AS bill_total_amount",
            "(SELECT count(bill_id) FROM bill b WHERE
                b.link_person_wo_id = {$table}.{$key} AND b.final = 1 LIMIT 1)
                AS bill_final",
            "(SELECT CASE WHEN file_id IS NOT NULL THEN 1 ELSE 0 END AS file_id
                FROM `file` WHERE table_name = '{$table}' AND
                 table_id = {$table}.{$key} AND
                 filename LIKE '%_signature_%'
                 ORDER BY created_date DESC LIMIT 1)
                 AS have_signature",
            "(SELECT SUM(poe.total) 
                FROM purchase_order po
                INNER JOIN purchase_order_entry poe ON po.purchase_order_id = poe.purchase_order_id
                WHERE po.link_person_wo_id = {$table}.{$key}
                GROUP BY po.link_person_wo_id)
                 AS po_total_amount"
        ];
        $model = $model->selectRaw(implode(', ', $columns));

        /** @var LinkPersonWo $items */
        $items = $model->get();

        return $items;
    }

    /**
     * Get last vendors in location where work order is different than given
     *
     * @param  int  $locationId
     * @param  int  $workOrderId
     * @param  int  $limit  Number of records to get. If 0 - no limit
     *
     * @return mixed
     */
    public function getLastLocationVendors(
        $locationId,
        $workOrderId,
        $limit = 0
    ) {
        $table = $this->model->getTable();
        $columns = [
            $table.'.person_id',
            "person_name({$table}.person_id) as person_name",
            'p.kind',
            "count({$table}.person_id) as cnt",
        ];

        $query = $this->model
            ->selectRaw(implode(', ', $columns))
            ->leftJoin(
                'work_order AS wo',
                "{$table}.work_order_id",
                '=',
                'wo.work_order_id'
            )
            ->leftJoin(
                'person AS p',
                "{$table}.person_id",
                '=',
                'p.person_id'
            )
            ->where('wo.shop_address_id', $locationId)
            ->where("{$table}.is_disabled", 0)
            ->where('wo.work_order_id', '!=', $workOrderId)
            ->groupBy("{$table}.person_id")
            ->orderByDesc('cnt');

        if ($limit) {
            $query = $query->take($limit);
        }

        return $query->get();
    }

    /**
     * Add single vendor to work order
     *
     * @param  int  $workOrderId
     * @param  int  $vendorId
     * @param  string  $vendorKind
     * @param  string  $jobType
     * @param  int  $recallLinkPersonWoId
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     * @throws LpWoInvalidVendorKindException
     */
    public function addSingleVendorToWorkOrder(
        $workOrderId,
        $vendorId,
        $vendorKind,
        $jobType,
        $recallLinkPersonWoId
    ) {
        if ($workOrderId == 0) {
            return false;
        }

        if ($jobType == 'work') {
            $rec = $this->model
                ->select('link_person_wo_id')
                ->where('work_order_id', $workOrderId)
                ->where('person_id', $vendorId)->where('is_disabled', 0)
                ->where('type', 'work')->first();
            if ($rec) {
                return [false, null];
            }
        }

        $allowedKindTypes = ['company' => 0, 'person' => 2];

        if (!array_key_exists($vendorKind, $allowedKindTypes)) {
            /** @var LpWoInvalidVendorKindException $exp */
            $exp = $this->app->make(LpWoInvalidVendorKindException::class);
            $exp->setData([
                'vendor_id'          => $vendorId,
                'vendor_kind'        => $vendorKind,
                'valid_vendor_kinds' => array_keys($allowedKindTypes),
            ]);
            throw $exp;
        }

        $model = $this->newInstance();
        $model->work_order_id = $workOrderId;
        $model->person_id = $vendorId;
        $model->creator_person_id = getCurrentPersonId();
        $model->status_type_id = ($jobType == 'quote')
            ? getTypeIdByKey('wo_quote_status.rfq_issued')
            : getTypeIdByKey('wo_vendor_status.assigned');
        $model->bill_final = $allowedKindTypes[$vendorKind];
        $model->type = $jobType;
        $model->recall_link_person_wo_id = $recallLinkPersonWoId;
        $model->is_hidden = 0;
        $model->priority = $this->getNewPriority($vendorId);

        $statusTypeId = null;

        DB::transaction(function () use (
            &$model,
            $workOrderId,
            $vendorId,
            &$statusTypeId
        ) {
            $model->save();
            $this->updateInProgressPriorities($vendorId, $model->getId());

            $workOrder = $this->getRepository('WorkOrder');
            $wo = $workOrder->findSoft($workOrderId);

            if ($this->getAssignedVendorsCount($workOrderId)) {
                $statusTypeId = getTypeIdByKey('wo_status.assigned_in_crm');
            } else {
                $statusTypeId = getTypeIdByKey('wo_status.quote');
            }
            $wo->wo_status_type_id = $statusTypeId;
            $wo->save();
        });

        $names = DB::select(
            'SELECT person_name(?) AS name,
                                     person_name(?) AS creator FROM person',
            [$vendorId, getCurrentPersonId()]
        );
        $names = $names[0];

        /** @var \App\Modules\Email\Services\EmailSenderService $emailSenderService */
        $emailSenderService = new EmailSenderService($this->config);
        $subject = "Work order assigned to {$names->name} by {$names->creator}";
        $body = $subject;
        $emailSenderService->sendType('lpwo_assigned', $subject, $body);

        /** @var PushNotificationAdderService $pushNotificationAdderService */
        $pushNotificationAdderService = app(PushNotificationAdderService::class);
        $pushNotificationAdderService->technicianAssignedToWorkOrder($vendorId, $workOrderId);
        
        return [$model, $statusTypeId];
    }

    /**
     * Get assigned vendors count for work order
     *
     * @param  int  $workOrderId
     * @param  bool  $countDisabled
     * @param  array  $vendorStatus
     * @param  string  $jobType
     *
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    public function getAssignedVendorsCount(
        $workOrderId,
        $countDisabled = false,
        array $vendorStatus = [],
        $jobType = 'work,recall'
    ) {
        $type = $this->getRepository('Type');

        /** @var \Illuminate\Database\Query\Builder $query */
        $query = $this->model->where('work_order_id', $workOrderId);

        $jobTypes = explode(',', $jobType);
        if (isset($jobTypes[1])) {
            $query = $query->whereIn('link_person_wo.type', $jobTypes);
        }

        if (!empty($vendorStatus)) {
            $query = $query->whereIn('status_type_id', $vendorStatus);
        }

        if (!$countDisabled) {
            $query = $query->where('is_disabled', '!=', 1)
                ->where(function ($q) use ($type) {
                    $q->whereNull('status_type_id')
                        ->orWhereNotIn('status_type_id', [
                            $type->getIdByKey('wo_vendor_status.canceled') ?? 0,
                            $type->getIdByKey('wo_quote_status.canceled') ?? 0,
                        ]);
                });
        }

        return $query->count('link_person_wo_id');
    }

    /**
     * Get vendors assigned to work order with given id
     *
     * @param  int  $workOrderId
     * @param  bool  $removeDisabled
     *
     * @return Collection
     *
     * @throws \InvalidArgumentException
     */
    public function getAssignedVendors($workOrderId, $removeDisabled = true)
    {
        /** @var Builder $query */
        $query = $this->model->where('work_order_id', $workOrderId);

        if ($removeDisabled) {
            $query = $query->where('is_disabled', '!=', 1)
                ->where(function ($q) {
                    $q->whereNull('status_type_id')
                        ->orWhereNotIn('status_type_id', [
                            getTypeIdByKey('wo_vendor_status.canceled') ?? 0,
                            getTypeIdByKey('wo_quote_status.canceled') ?? 0,
                        ]);
                });
        }

        return $query->get();
    }

    /**
     * Get vendor statuses count for each status type
     *
     * @param  int  $workOrderId
     * @param  string  $jobType
     *
     * @return array
     */
    public function getVendorStatusesCount(
        $workOrderId,
        $jobType = 'work,recall',
        $countDisabled = true
    ) {
        $table = $this->model->getTable();
        $columns = [
            'count(link_person_wo_id) AS cnt',
            'type.type_key AS status',
        ];
        $model = $this->model->selectRaw(implode(', ', $columns))
            ->leftJoin('type', "{$table}.status_type_id", '=', 'type.type_id')
            ->where("{$table}.work_order_id", $workOrderId);

        if (!$countDisabled) {
            $model = $model->where("{$table}.is_disabled", 0);
        }

        $jobTypes = explode(',', $jobType);
        if (isset($jobTypes[1])) {
            $model = $model->whereIn("{$table}.type", $jobTypes);
        }
        $model = $model->groupBy("{$table}.status_type_id");

        return $model->pluck('cnt', 'status')->all();
    }

    /**
     * Get assigned vendors for Work Order with $contacts
     *
     * @param  int  $workOrderId
     * @param  array  $contacts  Id of contact types to get only
     *
     * @return Collection
     */
    public function getAssignedWithContacts($workOrderId, array $contacts = [])
    {
        $table = $this->model->getTable();

        $columns = [
            'con.contact_id',
            'con.value AS contact_value',
            'con.contact_id',
            'con.is_default',
            't2.type_value AS contact_type',
            't2.type_key AS contact_key',
            "person_name({$table}.person_id) AS vendor_name",
            "{$table}.link_person_wo_id",
            "{$table}.person_id",
            "{$table}.is_hidden",
            "{$table}.is_disabled",
            't.type_value AS vendor_status',
            "IF((SELECT custom_9 AS prefered_type_id FROM person
                  WHERE person_id = {$table}.person_id) = t2.type_value, 1, 0)
                AS is_prefered",
        ];

        $model = $this->model
            ->selectRaw(implode(', ', $columns))
            ->leftJoin(
                'type AS t',
                "{$table}.status_type_id",
                '=',
                't.type_id'
            )
            ->leftJoin(
                'contact AS con',
                'con.person_id',
                '=',
                "{$table}.person_id"
            )
            ->leftJoin(
                'type AS t2',
                'con.type_id',
                '=',
                't2.type_id'
            )
            ->where("{$table}.work_order_id", $workOrderId);

        if ($contacts) {
            $model = $model->whereIn('con.type_id', $contacts);
        }

        $model = $model->groupBy('con.contact_id')
            ->orderBy('vendor_name')
            ->orderBy('con.type_id')->orderByDesc('con.is_default');

        return $model->get();
    }

    /**
     * Get list of link person WO specifying value
     *
     * @param  string  $valueColumn  Value to get
     * @param  string|null  $keyColumn  Key (by default primary key_
     * @param  array  $ids  List of ids that should be get
     *
     * @return mixed
     */
    public function getList($valueColumn, $keyColumn = null, array $ids = [])
    {
        $key = $this->model->getKeyName();
        $model = $this->model;
        if ($ids) {
            $model = $model->whereIn($key, $ids);
        }

        if ($keyColumn === null) {
            $keyColumn = $key;
        }

        return $model->pluck($valueColumn, $keyColumn)->all();
    }

    /**
     * List distinct ids of link person wo for given work order and person
     *
     * @param  int  $workOrderId
     * @param  int  $personId
     *
     * @return array
     */
    public function listDistinctForWorkOrderAndPerson($workOrderId, $personId)
    {
        return $this->model
            ->where('work_order_id', $workOrderId)
            ->where('person_id', $personId)->distinct()
            ->pluck('link_person_wo_id')->all();
    }

    /**
     * Verify if person is assigned to given work order number
     *
     * @param  int  $workOrderId
     * @param  int  $personId
     *
     * @return bool
     */
    public function isPersonAssignedForWo($workOrderId, $personId)
    {
        $count = $this->model
            ->select('link_person_wo_id')
            ->where('work_order_id', $workOrderId)
            ->where('person_id', $personId)
            ->where('is_disabled', 0)->count();

        if ($count) {
            return true;
        }

        return false;
    }

    /**
     * Get count of issued (not disabled) work orders. If $personId is not null
     * it will get count only for this person, otherwise it will get issued
     * count for all persons
     *
     * @param  int|null  $personId
     *
     * @return int
     */
    public function getIssuedCount($personId = null)
    {
        $query = $this->model
            ->where('is_disabled', 0)
            ->where('status_type_id', getTypeIdByKey('wo_vendor_status.issued'));
        if ($personId) {
            $query = $query->where('person_id', $personId);
        }

        return $query->count();
    }

    /**
     * Get link person wo for given work order id and person id
     *
     * @param  int  $workOrderId
     * @param  int  $personId
     * @param  bool  $firstOnly
     *
     * @return LinkPersonWo
     */
    public function getForWoAndPerson(
        $workOrderId,
        $personId,
        $firstOnly = false
    ) {
        $query = $this->model
            ->where('work_order_id', $workOrderId)
            ->where('person_id', $personId);
        if ($firstOnly) {
            return $query->first();
        }

        return $query->get();
    }

    /**
     * Get first link person wo for given work order id and person id
     *
     * @param  int  $workOrderId
     * @param  int  $personId
     *
     * @return LinkPersonWo
     */
    public function getFirstForWoAndPerson($workOrderId, $personId)
    {
        return $this->getForWoAndPerson($workOrderId, $personId, true);
    }

    /**
     * @param $workOrderId
     * @param $personId
     *
     * @return mixed
     */
    public function getLastForWoAndPerson($workOrderId, $personId)
    {
        return $this->model
            ->where('work_order_id', $workOrderId)
            ->where('person_id', $personId)
            ->orderByDesc('link_person_wo_id')
            ->first();
    }
    
    /**
     * Cancel link person wo
     *
     * @param  LinkPersonWo  $lpWo
     * @param  int  $statusTypeId
     * @param  int  $cancelReasonTypeId
     *
     * @return LinkPersonWo
     */
    public function cancel(
        LinkPersonWo $lpWo,
        $statusTypeId,
        $cancelReasonTypeId
    ) {
        $lpWo->is_disabled = 1;
        $lpWo->status_type_id = $statusTypeId;
        $lpWo->cancel_reason_type_id = $cancelReasonTypeId;
        $lpWo->disabled_date = Carbon::now()->format('Y-m-d H:i:s');
        $lpWo->priority = 0;
        $lpWo->save();

        return $lpWo;
    }

    /**
     * Find link person wo together with work order. If 2nd parameter is set to
     * true it will throw exception in case work order is missing
     *
     * @param  int  $id
     * @param  bool  $workOrderRequired
     *
     * @return LinkPersonWo
     * @throws LpWoMissingWorkOrderException
     */
    public function findWithWorkOrder($id, $workOrderRequired = true)
    {
        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->model
            ->with('workOrder')
            ->findOrFail($id);

        if ($workOrderRequired && !$lpWo->workOrder) {
            /** @var LpWoMissingWorkOrderException $exp */
            $exp = $this->app->make(LpWoMissingWorkOrderException::class);
            $exp->setData([
                'link_person_wo_id' => $lpWo->getId(),
                'work_order_id'     => $lpWo->getWorkOrderId(),
            ]);
            throw $exp;
        }

        return $lpWo;
    }

    /**
     * Find link person wo together linking person and work order
     *
     * @param  int  $personId
     * @param  int  $workOrderId
     *
     * @return LinkPersonWo
     *
     * @throws LpWoMissingWorkOrderException
     */
    public function findByPersonAndWorkOrder($personId, $workOrderId)
    {
        /** @var Builder|LinkPersonWo $lpWo */
        $lpWo = $this->model;

        $lpWo = $lpWo
            ->ofPerson($personId)
            ->ofWorkOrder($workOrderId);

        return $lpWo->firstOrFail();
    }

    /**
     * Get statuses for link person wo
     *
     * @param $workOrderId
     *
     * @return mixed
     */
    public function getLinkPersonWo($workOrderId)
    {
        $columns = [
            'link_person_wo_id',
            'link_person_wo.person_id',
            DB::raw('person_name(link_person_wo.person_id) as person_name'),
            'is_disabled',
            'disabled_date',
            'status_type_id',
            DB::raw('t(status_type_id) as status'),
            'tech_status_type_id',
            DB::raw('t(tech_status_type_id) as tech_status'),
            'person_data.data_value as sl_tech_id',
            'created_date as assigned_at'
        ];

        return $this->model
            ->select($columns)
            ->leftJoin('person_data', function ($join) {
                /** @var JoinClause $join */
                $join->on('person_data.data_key', '=', DB::raw('"external_id"'));
                $join->on('person_data.person_id', '=', 'link_person_wo.person_id');
            })
            ->where('work_order_id', $workOrderId)
            ->get();
    }

    /**
     * @param $personId
     * @param $workOrderNumbers
     *
     * @return mixed
     */
    public function getIdsByPersonAndWorkOrderNumbers($personId, $workOrderNumbers)
    {
        $statusCompleted = getTypeIdByKey('wo_vendor_status.completed');
        $statusCanceled = getTypeIdByKey('wo_vendor_status.canceled');

        $techStatusCompleted = getTypeIdByKey('tech_status.completed');
        $techStatusIncomplete = getTypeIdByKey('tech_status.incomplete');

        return $this->model
            ->join('work_order', 'link_person_wo.work_order_id', '=', 'work_order.work_order_id')
            ->where('link_person_wo.person_id', $personId)
            ->whereNotIn('link_person_wo.status_type_id', [$statusCompleted, $statusCanceled])
            ->whereNotIn('link_person_wo.tech_status_type_id', [$techStatusCompleted, $techStatusIncomplete])
            ->whereIn('work_order.work_order_number', $workOrderNumbers)
            ->pluck('link_person_wo_id', 'work_order_number')
            ->all();
    }

    /**
     * @param $workOrderId
     *
     * @return mixed
     */
    public function getLinkPersonWoByWorkOrderId($workOrderId)
    {
        return $this->model
            ->where('work_order_id', '=', $workOrderId)
            ->where('is_disabled', '!=', 1)
            ->orderByDesc('link_person_wo_id')
            ->first();
    }

    /**
     * @param $direction
     * @param $linkPersonWo
     *
     * @return bool
     * @throws Exception
     */
    public function changePriority($direction, $linkPersonWo)
    {
        // $sql = 'SELECT max(priority) AS max FROM link_person_wo WHERE person_id = '.$this->person_id.' AND is_disabled != 1';
        $currentLink = new LinkPersonWo();
        $currentLink = $currentLink->find($linkPersonWo);
        $currentLinkPriority = $currentLink->priority;

        $nextLink = new LinkPersonWo();
        $nextLink = $nextLink->where('person_id', '=', $currentLink->person_id)
            ->where('priority', '>', $currentLink->priority)
            ->where('is_disabled', '!=', 1)
            ->orderBy('priority')
            ->first();

        $previousLink = new LinkPersonWo();
        $previousLink = $previousLink->where('person_id', '=', $currentLink->person_id)
            ->where('priority', '<', $currentLink->priority)
            ->where('is_disabled', '!=', 1)
            ->orderByDesc('priority')
            ->first();


        $max = LinkPersonWo::where('person_id', '=', $currentLink->person_id)
            ->where('is_disabled', '!=', 1)
            ->max('priority');

        $canceledTypeID = Type::where('type_key', '=', 'wo_vendor_status.canceled')->first()->id;
        $completedTypeID = Type::where('type_key', '=', 'wo_vendor_status.completed')->first()->id;

        switch ($direction) {
            case 'top':
                $currentLink->priority = $max + 1;

                if (!$currentLink->save()) {
                    throw new Exception('Error while saving new priority!');
                }

                $this->priority = $currentLink->priority;
                return true;
                break;

            case 'up':
                if (0 <= $currentLink->priority && $currentLink->priority < $max) {
                    $currentLink->priority = $nextLink->priority;
                    $nextLink->priority = $currentLinkPriority;

                    if (!$currentLink->save() || !$nextLink->save()) {
                        throw new Exception('Error while saving new priority!');
                    }
                    return true;
                }
                break;

            case 'down':
                if (1 < $currentLink->priority && $currentLink->priority <= $max) {
                    $currentLink->priority = $previousLink->priority;
                    $previousLink->priority = $currentLinkPriority;

                    if (!$currentLink->save() || !$previousLink->save()) {
                        throw new Exception('Error while saving new priority!');
                    }
                    return true;
                }
                break;
        }
        throw new Exception('Nothing to change!');
    }

    /**
     * @param $input
     *
     * @return array
     */
    public function techGrid($input)
    {
        $issuedId = getTypeIdByKey('wo_status.issued_to_vendor_tech');
        $confirmedId = getTypeIdByKey('wo_status.confirmed');
        $completedId = getTypeIdByKey('wo_status.completed');
        $canceledId = getTypeIdByKey('wo_status.canceled');
        $assignedId = getTypeIdByKey('wo_status.assigned_in_crm');

        $vendorCompletedId = getTypeIdByKey('wo_vendor_status.completed');
        $vendorCanceledId = getTypeIdByKey('wo_vendor_status.canceled');

        $employeeTypeId = getTypeIdByKey('person.employee');
        $techTypeId = getTypeIdByKey('person.technician');

        $companyCustomerTypeId = getTypeIdByKey('company.customer');

        $viaTypes = Type::where('type', '=', 'via')->pluck('type_value', 'type_id');
        $woStatus = Type::where('type', '=', 'wo_status')->pluck('type_value', 'type_id');
        $quoteStatus = Type::where('type', '=', 'quote_status')->pluck('type_value', 'type_id');
        $crmPriority = Type::where('type', '=', 'crm_priority')->pluck('type_value', 'type_id');
        $reasonTypes = Type::where('type', '=', 'time_sheet_reason')->pluck('type_value', 'type_id');
        $companies = Person::where('type_id', '=', $companyCustomerTypeId)->pluck('custom_1', 'type_id');

        $data = [];

        $persons = Person::whereIn('type_id', [$employeeTypeId, $techTypeId])
            ->join('time_sheet', 'time_sheet.person_id', '=', 'person.person_id')
            ->orderByDesc('time_sheet.entry_date')
            ->groupBy('time_sheet.person_id');

        if (Arr::has($input, 'technician_person_id')) {
            $persons = $persons->where('time_sheet.person_id', '=', $input['technician_person_id']);
        }

        $persons = $persons->get();

        foreach ($persons as $person) {
            $grid = [
                'person_id'                    => $person->id,
                'person_name'                  => $person->getName(),
                'work_orders_issued'           => 0,
                'work_orders_confirmed'        => 0,
                'work_orders_assigned'         => 0,
                'work_orders_completed'        => 0,
                'total_time'                   => 0,
                'last_time_sheet_reason_id'    => $person->reason_type_id,
                'last_time_sheet_reason_value' => $person->reason_type_id ? $reasonTypes[$person->reason_type_id] : 'null',
                'last_time_sheet_entry_date'   => $person->entry_date
            ];

            $monday = date("Y-m-d", strtotime('monday this week'))." 00:00:00";
            $sunday = date("Y-m-d", strtotime('sunday this week'))." 23:59:59";

            $totalTime = TimeSheet::where('time_sheet.person_id', '=', $person->id)
                ->where('time_sheet.table_name', '=', 'link_person_wo')
                ->whereBetween('time_sheet.entry_date', [$monday, $sunday])
                ->select([DB::raw("SUM(TIME_TO_SEC(time_sheet.duration)) as duration"), 'time_sheet.table_id'])
                ->groupBy('time_sheet.table_id')
                ->get();

            $workOrderIds = LinkPersonWo::where('link_person_wo.person_id', '=', $person->id)
                ->where('is_disabled', '!=', 1)
                ->join('work_order', 'work_order.work_order_id', '=', 'link_person_wo.work_order_id')
                ->join('address', 'work_order.shop_address_id', '=', 'address.address_id')
                ->join('time_sheet', function ($query) {
                    $query
                        ->on('time_sheet.table_id', '=', 'link_person_wo.link_person_wo_id')
                        ->on('time_sheet.table_name', '=', DB::raw('"link_person_wo"'));
                })
                ->select(
                    'link_person_wo.link_person_wo_id',
                    DB::raw('count(time_sheet.time_sheet_id) as time_sheets'),
                    'time_sheet.reason_type_id as reason_type_id',
                    'link_person_wo.priority as order_by_1',
                    'work_order.*',
                    'address.city',
                    'address.state'
                )
                ->orderByDesc('time_sheet.entry_date')
                ->groupBy('time_sheet.table_id')
                ->orderBy('link_person_wo.priority');


            if (empty($input)) {
                $workOrderIds = $workOrderIds
                    ->orWhereIn('link_person_wo.link_person_wo_id', $totalTime->pluck('table_id'));
            }

            if (Arr::has($input, 'fin_loc')) {
                $workOrderIds = $workOrderIds
                    ->where('work_order.fin_loc', 'LIKE', $input['fin_loc'].'%');
            }

            if (Arr::has($input, 'work_order_number')) {
                $workOrderIds = $workOrderIds
                    ->where('work_order.work_order_number', 'LIKE', $input['work_order_number'].'%');
            }

            if (Arr::has($input, 'client_person_id')) {
                $workOrderIds = $workOrderIds
                    ->where('work_order.company_person_id', '=', $input['client_person_id']);
            }

            if (Arr::has($input, 'expected_completion_date')) {
                $explode = explode(',', $input['expected_completion_date']);
                $workOrderIds = $workOrderIds
                    ->whereBetween('expected_completion_date', [$explode[0]." 00:00:00", $explode[1]." 23:59:59"]);
            }

            if (Arr::has($input, 'crm_priority_id')) {
                $workOrderIds = $workOrderIds->where('crm_priority_type_id', '=', $input['crm_priority_id']);
            }

            if (Arr::has($input, 'wo_status_type_id')) {
                if ($input['wo_status_type_id'] == $completedId) {
                    $workOrderIds = $workOrderIds->where(function ($query) use ($input, $totalTime) {
                        $query
                            ->whereIn('link_person_wo.link_person_wo_id', $totalTime->pluck('table_id'))
                            ->where('wo_status_type_id', '=', $input['wo_status_type_id']);
                    });
                } else {
                    $workOrderIds = $workOrderIds->where('wo_status_type_id', '=', $input['wo_status_type_id']);
                }
            } else {
                $workOrderIds = $workOrderIds
                    ->whereNotIn('link_person_wo.status_type_id', [0, $vendorCompletedId, $vendorCanceledId])
                    ->whereNotIn('work_order.wo_status_type_id', [0, $completedId, $canceledId]);
            }

//            echo $person->getName() . " - " . $workOrderIds->get()->count() . "\n";
//            continue;
            
            $workOrderIds = $workOrderIds->get();

            $init = $totalTime->sum('duration');

            $hours = floor($init / 3600);
            $minutes = floor(($init / 60) % 60) > 10 ? floor(($init / 60) % 60) : '0'.floor(($init / 60) % 60);
            $seconds = ($init % 60) > 10 ? $init % 60 : '0'.$init % 60;

            $grid['total_time'] = "$hours:$minutes:$seconds";

            foreach ($workOrderIds as $workOrder) {
                $grid['work_orders'][] =
                    [
                        'id'                           => $workOrder->work_order_id,
                        'order_by'                     => $workOrder->order_by_1,
                        'work_order_number'            => $workOrder->work_order_number,
                        'received_date'                => $workOrder->received_date,
                        'via_type_id'                  => $workOrder->via_type_id,
                        'via_type_value'               => ($workOrder->via_type_id) ? $viaTypes[$workOrder->via_type_id] : 'null',
                        'wo_status_type_id'            => $workOrder->wo_status_type_id,
                        'wo_status_type_value'         => $woStatus[$workOrder->wo_status_type_id],
                        'priority'                     => ($workOrder->crm_priority_type_id) ? $crmPriority[$workOrder->crm_priority_type_id] : 'null',
                        'expected_completion_date'     => $workOrder->expected_completion_date,
                        'quote_status_type_id'         => $workOrder->quote_status_type_id,
                        'quote_status_type_value'      => ($workOrder->quote_status_type_id) ? $quoteStatus[$workOrder->quote_status_type_id] : 'null',
                        'client'                       => (Arr::has(
                            $companies,
                            $workOrder->company_person_id
                        )) ? $companies[$workOrder->company_person_id] : 'null',
                        'fin_loc'                      => $workOrder->fin_loc,
                        'city'                         => $workOrder->city,
                        'state'                        => $workOrder->state,
                        'project_maneger_person_id'    => $workOrder->project_maneger_person_id,
                        'project_manager_person_value' => ($workOrder->project_maneger_person_id) ? Person::find($workOrder->project_maneger_person_id)->getName() : 'null',
                        'actual_completion_date'       => $workOrder->actual_completion_date,
                        'link_person_wo_id'            => $workOrder->link_person_wo_id,
                        'not_to_exceed'                => $workOrder->not_to_exceed,
                        'time_sheet_reason'            => $workOrder->reason_type_id ? $reasonTypes[$workOrder->reason_type_id] : 'null',
                        'time_sheets_count'            => $workOrder->time_sheets
                    ];

                switch ($workOrder->wo_status_type_id) {
                    case $issuedId:
                        $grid['work_orders_issued']++;
                        break;
                    case $confirmedId:
                        $grid['work_orders_confirmed']++;
                        break;
                    case $assignedId:
                        $grid['work_orders_assigned']++;
                        break;
                    case $completedId:
                        $grid['work_orders_completed']++;
                        break;
                }
            }

            $data[] = $grid;
        }

        return $data;
    }

    /**
     * Merge link person work orders with Each Other
     *
     * @param $fromLpwoID
     * @param $toLpwoID
     */
    public function merge($fromLpwoID, $toLpwoID)
    {
        // 1. get Link person WOs
        $fromLinkPersonWO = $this->model->find($fromLpwoID);
        $toLinkPersonWO = $this->model->find($toLpwoID);
        // 2. if ok, start merge
        if ($fromLinkPersonWO->link_person_wo_id == $fromLpwoID && $toLinkPersonWO->link_person_wo_id == $toLpwoID) {
            $bills = Bill::where('link_person_wo_id', $fromLpwoID)
                ->update(['link_person_wo_id' => $toLpwoID]);

            $purchaseOrders = PurchaseOrder::where('link_person_wo_id', $fromLpwoID)
                ->update(['link_person_wo_id' => $toLpwoID]);

            $articleProgress = ArticleProgress::where('link_record_id', $fromLpwoID)
                ->where('link_tablename', 'link_person_wo')
                ->update(['link_record_id' => $toLpwoID]);

            $activity = Activity::where('table_name', 'link_person_wo')
                ->where('table_id', $fromLpwoID)
                ->update(['table_id' => $toLpwoID]);

            $event = CalendarEvent::where('record_id', $fromLpwoID)
                ->where('tablename', 'work_order')
                ->update(['record_id' => $toLpwoID]);

            $dataExchange = DataExchange::where('table_name', 'link_person_wo')
                ->where('record_id', $fromLpwoID)
                ->update(['record_id' => $toLpwoID]);

            $files = File::where('table_id', $fromLpwoID)
                ->where('table_name', 'link_person_wo')
                ->update(['table_id' => $toLpwoID]);

            if (config('app.crm_user') != 'fs') {
                $files2 = File::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);
            }

            $timeSheets = TimeSheet::where('table_id', $fromLpwoID)
                ->where('table_name', 'link_person_wo')
                ->update(['table_id' => $toLpwoID]);

            $history = History::where('record_id', $fromLpwoID)
                ->where('tablename', 'link_person_wo')
                ->update(['record_id' => $toLpwoID]);

            $history = History::where('related_record_id', $fromLpwoID)
                ->where('related_tablename', 'link_person_wo')
                ->update(['related_record_id' => $toLpwoID]);

            if (config('app.crm_user') != 'fs') {
                $completedJobSMS = CompletedJobSms::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);

                $exceptions = Exceptions::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);

                $hazardAssessments = HazardAssessment::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);

                $historyLpwoStatus = HistoryLpwoStatus::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);

                $historyLpwoTechStatus = HistoryLpwoTechStatus::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);

                $linkAssetPersonWo = LinkAssetPersonWo::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);


                $linkPersonWoSchedule = LinkPersonWoSchedule::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);
                
                $linkAssetPersonWoStats = LinkPersonWoStats::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);

                $notification = Notification::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);

                $techStatusHistory = TechStatusHistory::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);

                $workOrderLiveAction = WorkOrderLiveAction::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);

                $workOrderLiveActionToOrder = WorkOrderLiveActionToOrder::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);

                $workOrderRackMaintenance = WorkOrderRackMaintenance::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);

                $workOrderRackMaintenanceItems = WorkOrderRackMaintenanceItem::where('link_person_wo_id', $fromLpwoID)
                    ->update(['link_person_wo_id' => $toLpwoID]);
            }

            $mergeHistory = new MergeHistory();
            $mergeHistory->table_name = 'link_person_wo';
            $mergeHistory->object_id = $fromLpwoID;
            $mergeHistory->merged_object_id = $toLpwoID;
            $mergeHistory->save();
            
            $fromLinkPersonWO->status_type_id = getTypeIdByKey('wo_vendor_status.canceled');
            $fromLinkPersonWO->save();
        }
    }

    /**
     * @param $linkPersonWoId
     *
     * @return mixed|null
     */
    public function getWorkOrderId($linkPersonWoId)
    {
        $linkPersonWo = $this->findSoft($linkPersonWoId);
        if ($linkPersonWo) {
            return $linkPersonWo->work_order_id;
        }
        
        return null;
    }

    /**
     * @param  int $linkPersonWoId
     *
     * @return mixed
     */
    public function getWorkOrderByLinkPersonWoId($linkPersonWoId)
    {
        return $this->model
            ->select('work_order.*')
            ->join('work_order', 'link_person_wo.work_order_id', '=', 'work_order.work_order_id')
            ->where('link_person_wo.link_person_wo_id', $linkPersonWoId)
            ->first();
    }
    
    /**
     * @param  array  $linkPersonWoIds
     *
     * @return array
     */
    public function getWorkOrdersByLinkPersonWoIds(array $linkPersonWoIds)
    {
        if (!$linkPersonWoIds) {
            return [];
        }

        $mappedWorkOrders = [];
        $workOrders = $this->model
            ->select([
                'link_person_wo.link_person_wo_id',
                'link_person_wo.work_order_id',
                'work_order.work_order_number',
            ])
            ->join('work_order', 'link_person_wo.work_order_id', '=', 'work_order.work_order_id')
            ->whereIn('link_person_wo.link_person_wo_id', $linkPersonWoIds)
            ->get();

        foreach ($workOrders as $workOrder) {
            $mappedWorkOrders[$workOrder->link_person_wo_id] = $workOrder;
        }
        
        return $mappedWorkOrders;
    }
}
