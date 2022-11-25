<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Core\Exceptions\LockedMismatchException;
use App\Core\Exceptions\NoPermissionException;
use App\Core\User;
use App\Modules\Activity\Http\Requests\ActivityRequest;
use App\Modules\Activity\Models\Activity;
use App\Modules\Activity\Repositories\ActivityRepository;
use App\Modules\Address\Models\Address;
use App\Modules\AddressIssue\Models\AddressIssue;
use App\Modules\Asset\Models\LinkAssetPersonWo;
use App\Modules\Asset\Models\LinkAssetWo;
use App\Modules\Bill\Repositories\BillRepository;
use App\Modules\CalendarEvent\Http\Requests\CalendarEventTaskRequest;
use App\Modules\CalendarEvent\Models\CalendarEvent;
use App\Modules\Chat\Services\ChatRoomService;
use App\Modules\Contact\Models\Contact;
use App\Modules\Email\Models\Email;
use App\Modules\Email\Repositories\EmailThreadWorkOrderRepository;
use App\Modules\File\Models\File;
use App\Modules\History\Models\History;
use App\Modules\History\Models\MergeHistory;
use App\Modules\History\Repositories\HistoryRepository;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Kb\Models\ArticleProgress;
use App\Modules\Kb\Models\LinkArticleWo;
use App\Modules\Kb\Models\LinkedArticleWo;
use App\Modules\MsDynamics\DatabaseManager;
use App\Modules\MsDynamics\ExternalFileSync as SlExternalFileSync;
use App\Modules\MsDynamics\ExternalNoteSync as SlExternalNoteSync;
use App\Modules\MsDynamics\SlRecordsManager;
use App\Modules\MsDynamics\TechLinkImporter as SlTechLinkImporter;
use App\Modules\MsDynamics\WorkOrderManager as SlManager;
use App\Modules\Person\Models\Company;
use App\Modules\Person\Models\Person;
use App\Modules\Person\Repositories\PersonDataRepository;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\Quote\Models\Quote;
use App\Modules\TimeSheet\Models\TimeSheet;
use App\Modules\TruckOrder\Models\TruckOrder;
use App\Modules\Type\Models\Type;
use App\Modules\Wgln\Services\WglnService;
use App\Modules\WorkOrder\Exceptions\WoBfcCannotReassignException;
use App\Modules\WorkOrder\Http\Requests\WorkOrderBasicUpdateRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderExtensionRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderStoreRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderUpdateRequest;
use App\Modules\WorkOrder\Models\DataExchange;
use App\Modules\WorkOrder\Models\LinkLabtechWo;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderExtension;
use App\Modules\WorkOrder\Services\WorkOrderAddVendorsService;
use App\Modules\WorkOrder\Services\WorkOrderBoxCounterServiceContract;
use App\Modules\WorkOrder\Services\WorkOrderDataServiceContract;
use App\Modules\WorkOrder\Services\WorkOrderFilterService;
use App\Modules\WorkOrder\Services\WorkOrderFleetmaticsApiService;
use App\Modules\WorkOrder\Services\WorkOrderQueryGeneratorService;
use App\Modules\WorkOrder\Services\WorkOrderService;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * WorkOrder repository class
 */
class WorkOrderRepository extends AbstractRepository
{
    /**
     * Default meta data
     */
    const DEF_META_DATA = '%"Communication_system?":{"answer":"Service Channel"}%';

    /**
     * {@inheritdoc}
     */
    protected $searchable
        = [
            'work_order_number',
            'shop_address_id',
            'wo_status_type_id',
            'company_person_id',
            'billing_company_person_id',
            'bill_status_type_id',
            'invoice_status_type_id',
            'client_status',
            'via_type_id',
            'crm_priority_type_id',
            'trade_type_id',
            'cancel_reason_type_id',
            'quote_status_type_id',
            'parts_status_type_id',
            'project_manager_person_id',
            'work_order_number',
            'comment',
            'assigned_to'
            // when adding new filters consider change in $joinableFilters
        ];

    /**
     * Columns that might be used for sorting
     *
     * @var array
     */
    protected $sortable = [];

    /**
     * Custom filters
     *
     * @var array
     */
    protected $customFilters
        = [
            'client_type_id',
            'person_type_id',
            'assigned_to_tech',
            'assigned_to_vendor',
            'state',
            'city',
            'country',
            'work_order_number',
            'fin_loc',
            'created_date_from',
            'created_date_to',
            'expected_completion_date_from',
            'expected_completion_date_to',
            'actual_completion_date_from',
            'actual_completion_date_to',
            'nte_from',
            'nte_to',
            'opened',
            'techs_min_1',
            'vendors_min_1',
            'hot',
            'completed_need_invoice',
            'ready_to_quote',
            'quote_needs_approval',
            'quote_approved_need_invoice',
            'invoice_needs_approval',
            'invoiced_not_sent',
            'updated_work_orders',
            'past_due_work_orders',
            'techs_in_progress',
            'quote_status',
            'ready_to_invoice',
            'only_recalled',
            'vendors_min_1_additional',
            'technicians_with_bills',
            'only_sl_work_orders',
            'comment',
            'received_date'
            // when adding new filters consider change in $joinableFilters
        ];

    protected $availableColumns = [
        "work_order_id"                   => "work_order.work_order_id",
        "bill_total"                      => "(select sum(bill_amount) from link_person_wo where link_person_wo.work_order_id=work_order.work_order_id limit 1)",
        "address_id"                      => "address.address_id",
        "address_1"                       => "address.address_1",
        "city"                            => "address.city",
        "zip_code"                        => "address.zip_code",
        "state"                           => "address.state",
        "bill_status_type_id"             => "work_order.bill_status_type_id",
        "cancel_reason_type"              => "cancel_type.type_value",
        "category"                        => "work_order.category",
        "hours_until_ecd"                 => "datediff(ifnull((select work_order_extension.extended_date
                                        from work_order_extension where
                                        work_order_extension.work_order_id = work_order.work_order_id
                                        and work_order_extension.extended_date >
                                        work_order.expected_completion_date order by
                                        work_order_extension.extended_date desc limit 1),
                                        expected_completion_date),now())",
        "description"                     => "work_order.description",
        "expected_completion_date"        => "work_order.expected_completion_date",
        "fin_loc"                         => "work_order.fin_loc",
        "invoice_status_type_id"          => "work_order.invoice_status_type_id",
        "invoice_status_type_id_value"    => "invoice_status_type.type_value",
        "not_to_exceed"                   => "work_order.not_to_exceed",
        "priority"                        => "work_order.priority",
        "crm_priority_color"              => "priority_type.color",
        "crm_priority"                    => "priority_type.type_value",
        "received_date"                   => "work_order.received_date",
        "trade"                           => "work_order.trade",
        "crm_trade"                       => "trade_type.type_value",
        "via_type_id"                     => "work_order.via_type_id",
        "wo_status_type_id"               => "work_order.wo_status_type_id",
        "created_date"                    => "work_order.created_date",
        "id"                              => "work_order.work_order_id",
        "work_order_number"               => "work_order.work_order_number",
        "extended_due_date"               => "(select count(calendar_event_id) from calendar_event c
                                        where c.tablename='work_order' and
                                        c.record_id = work_order.work_order_id and c.is_completed=0
                                        and c.type_id=590 limit 1)
                                        as hot_tasks_count, (select distinct woe.created_date from work_order_extension woe
                                        where woe.work_order_id=work_order.work_order_id
                                        order by woe.created_date desc limit 1)
                                        as extended_date, (select distinct woe.extended_date from work_order_extension woe
                                        where woe.work_order_id=work_order.work_order_id
                                        order by woe.created_date desc limit 1)",
        "actual_completion_date"          => "work_order.actual_completion_date",
        "company_person_id"               => "work_order.company_person_id",
        "completion_code"                 => "work_order.completion_code",
        "costs"                           => "work_order.costs",
        "extended_why"                    => "work_order.extended_why",
        "invoice_amount"                  => "work_order.invoice_amount",
        "invoice_id"                      => "work_order.invoice_id",
        "client"                          => "person_name(work_order.company_person_id)",
        "shop"                            => "work_order.shop",
        "tracking_number"                 => "work_order.tracking_number",
        "quote_status_type_id"            => "IFNULL(work_order.quote_status_type_id, 0)",
        "comment"                         => "work_order.comment",
        "project_manager_person_id_value" => "person_name(project_manager_person_id)",
        "via_type_id_value"               => "t(via_type_id)",
        "wo_status_type_id_value"         => "t(wo_status_type_id)",
        "locked_id"                       => "work_order.locked_id",
        "locked_by"                       => "person_name(work_order.locked_id)",
        "modified_date"                   => "work_order.modified_date",
    ];


    /**
     * List of filters (from both $searchable and $customFilters) that require
     * making join for count query. Whenever you add new filter
     * for $customFilters or $searchable that require join you should add
     * this filter also here with type of join it will require
     *
     * Type of join may be also array, possible values comes from methods from
     * WorkOrderQueryGeneratorService and are address, trade_type, cancel_type,
     * priority_type
     *
     * @var array
     */
    protected $joinableFilters
        = [
            'state'   => 'address',
            'city'    => 'address',
            'country' => 'address',
        ];

    /**
     * @var
     */
    protected $allowGhostLink;

    public $statusesKeys = [
        'wo_status.new',
        'wo_status.picked_up',
        'wo_status.assigned_in_crm',
        'wo_status.extended',
        'wo_status.in_progress',
        'wo_status.completed',
        'wo_status.canceled',
        'wo_status.qa',
        'wo_status.confirmed',
        'wo_status.in_progress_and_hold',
        'wo_status.issued_to_vendor_tech',
    ];

    /**
     * @var mixed
     */
    private $type;

    /**
     * Repository constructor
     *
     * @param  Container  $app
     * @param  WorkOrder  $workOrder
     */
    public function __construct(Container $app, WorkOrder $workOrder)
    {
        parent::__construct($app, $workOrder);

        $this->type = $this->makeRepository('Type');
    }

    /**
     * Get default meta data
     *
     * @return string
     */
    public function getMetaData()
    {
        return self::DEF_META_DATA;
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(
        $perPage = 50,
        array $columns = ['*'],
        array $order = []
    ) {
        /** @var WorkOrder|Builder $model */
        $model = new WorkOrder();
        $input = $this->request->all();

        if (!empty($input['locked'])) {
            $permissions = ['workorder.locked_work_orders_list'];
            /** @var User $user */
            $user = Auth::user();
            if (!$user || !$user->hasPermissions($permissions)) {
                /** @var NoPermissionException $exp */
                $exp = App::make(NoPermissionException::class);
                $exp->setData(['permissions' => $permissions]);
                
                throw $exp;
            }
            
            $this->availableColumns = [
                'work_order_id' => 'work_order.work_order_id',
                'work_order_number' => 'work_order.work_order_number',
                'locked_id' => 'work_order.locked_id',
                'locked_by' => 'person_name(work_order.locked_id)',
                'modified_date' => 'work_order.modified_date',
            ];

            $lockLimit = $this->app->config->get('system_settings.workorder_lock_limit_minutes', 15);
            
            $startDate = Carbon::now('utc')->subMinutes($lockLimit)->format('Y-m-d H:i:s');
            
            $model = $model
                ->where('locked_id', '>', 0)
                ->where('modified_date', '>=', $startDate);

            $model = $this->setCustomColumns($model);
            $model = $this->setCustomSort($model);
            $model = $this->setCustomFilters($model);
            
            $this->setWorkingModel($model);
            $data = parent::paginate($perPage, [], $order);
            $this->clearWorkingModel();

            foreach ($data->items() as $item) {
                $item->locked_from = Carbon::parse($item->modified_date, 'utc')->diffForHumans();
                
                unset($item->modified_date);
            }
            
            return $data;
        }
        
        if(isCrmUser('fs')) {
            $this->availableColumns['subject'] = 'work_order.subject';
        }
        
        $onlyFilters = (isset($input['only_filters']) && $input['only_filters'] == 1);
        if (Arr::has($input, 'plain') && $input['plain']) {
            $countModel = clone $model;

            if (Arr::has($input, 'only_sl_work_orders') && $input['only_sl_work_orders']) {
                $model = $model->join('sl_records', function ($j) {
                    $j->where('sl_records.table_name', '=', 'work_order')
                        ->on('sl_records.record_id', '=', 'work_order.work_order_id');
                });
            }

            if (Arr::has($input, 'work_order_number') && !empty($input['work_order_number'])) {
                $model = $model->where('work_order_number', 'LIKE', '%'.$input['work_order_number']);
                unset($input['work_order_number']);
                $this->setInput($input);
            }

            $this->setWorkingModel($model);
            $this->setCountModel($countModel);
            $data = parent::paginate($perPage, $columns, $order)->toArray();
            $this->clearWorkingModel();

            return $data;
        }

        if (Arr::has($input, 'fields')) {
            $inputColumns = explode(',', $input['fields']);
        }

        if (isset($inputColumns)) {
            $slRoute = in_array('route', $inputColumns);
            $slType = in_array('sl_type_id_value', $inputColumns);
        }

        if (isset($input['solved'])) {
            $woCompletedTypeId = getTypeIdByKey('wo_status.completed');
            $woCanceledTypeId = getTypeIdByKey('wo_status.canceled');
            
            if ((int)$input['solved'] === 1) {
                $model = $model->where('wo_status_type_id', $woCompletedTypeId);
            } else {
                $model = $model->whereNotIn('wo_status_type_id', [$woCompletedTypeId, $woCanceledTypeId]);
            }
        }

        if (isset($input['created_by_user']) && (int)$input['created_by_user'] === 1) {
            $personId = Auth::user()->getPersonId();
            
            $model = $model->where('creator_person_id', $personId);
        }
        
        if (!$onlyFilters) {
            $this->setDefaultSort('-received_date');

            $model = $this->setCustomSort($model);
            $model = $this->setCustomFilters($model);
            $model = $this->setCustomColumns($model);

            if (Arr::has($input, 'only_sl_work_orders')) {
                $model = $model->join('sl_records', function ($j) {
                    $j->where('sl_records.table_name', '=', 'work_order')
                        ->on('sl_records.record_id', '=', 'work_order.work_order_id');
                });
            }

            if (Arr::has($input, 'opened')) {
                if ($input['opened']) {
                    $model = $model->whereNotIn(
                        'work_order.wo_status_type_id',
                        [
                            getTypeIdByKey('wo_status.canceled'),
                            getTypeIdByKey('wo_status.completed')
                        ]
                    );
                }
            }

            if (in_array('address', $this->joinableTables)) {
                $model = $model->leftJoin('address', 'address.address_id', '=', 'work_order.shop_address_id');
            }

            if (in_array('priority_type', $this->joinableTables)) {
                $model = $model->leftJoin(
                    'type as priority_type',
                    'priority_type.type_id',
                    '=',
                    'work_order.crm_priority_type_id'
                );
            }

            if (in_array('invoice_status_type', $this->joinableTables)) {
                $model = $model->leftJoin(
                    'type as invoice_status_type',
                    'invoice_status_type.type_id',
                    '=',
                    'work_order.invoice_status_type_id'
                );
            }

            if (in_array('trade_type', $this->joinableTables)) {
                $model = $model->leftJoin('type as trade_type', 'trade_type.type_id', '=', 'work_order.trade_type_id');
            }

            if (in_array('cancel_type', $this->joinableTables)) {
                $model = $model->leftJoin(
                    'type as cancel_type',
                    'cancel_type.type_id',
                    '=',
                    'work_order.cancel_reason_type_id'
                );
            }
            // additional searched for BFC client
            if (config('app.crm_user') == 'bfc') {
                $workOrderBFCSearch = [];
                if (isset($input['assigned_to_tech']) && !empty($input['assigned_to_tech'])) {
                    $workOrderBFCSearch[] = " smServFault.Empid = '".trim($input['assigned_to_tech'])."' ";
                }
                if (isset($input['customer_po']) && !empty($input['customer_po'])) {
                    $workOrderBFCSearch[] = " (smServCall.CustomerPO LIKE '".trim($input['customer_po'])."' or smServCall.User2 LIKE '".trim($input['customer_po'])."') ";
                }
                if (isset($input['route']) && !empty($input['route'])) {
                    $workOrderBFCSearch[] = " (smServCall.CustGeographicID LIKE '".trim($input['route'])."') ";
                }
                if (isset($input['promise_date']) && !empty($input['promise_date'])) {
                    $dates = explode(',', $input['promise_date']);
                    if (count($dates) == 2) {
                        $workOrderBFCSearch[] = " (ServiceCallDateProm BETWEEN '".$dates[0]."' and '".$dates[1]."') ";
                    }
                }
                if (isset($input['scheduled_date']) && !empty($input['scheduled_date'])) {
                    $dates = explode(',', $input['scheduled_date']);
                    if (count($dates) == 2) {
                        $workOrderBFCSearch[] = " (PromDate BETWEEN '".$dates[0]."' and '".$dates[1]."') ";
                    }
                }
                if (isset($input['task_status_id']) && !empty($input['task_status_id'])) {
                    $workOrderBFCSearch[] = " (smServCall.CallStatus = '".trim($input['task_status_id'])."') ";
                }
                if (isset($input['sl_type_id']) && !empty($input['sl_type_id'])) {
                    $workOrderBFCSearch[] = " (smServCall.CallType = '".trim($input['sl_type_id'])."') ";
                }
                if (isset($input['invoice_total']) && !empty($input['invoice_total'])) {
                    $workOrderBFCSearch[] = " (smServCall.invoiceAmount = '".trim($input['invoice_total'])."') ";
                }
                if (!empty($workOrderBFCSearch)) {
                    $woNumberList = $this->getSlManager()->searchWorkOrdersSL($workOrderBFCSearch);
                    $model = $model->whereIN(
                        'work_order_number',
                        $woNumberList
                    );
                }
            }
            // added for FS
            if (config('app.crm_user') == 'fs') {
                if (!empty($input['mobile_created_or_assigned_to_tech'])) {
                    $personId = $input['mobile_created_or_assigned_to_tech'];
                    
                    $model = $model->where(function ($query) use ($personId) {
                        $query
                            ->where('creator_person_id', $personId)
                            ->orWhereRaw(
                                'work_order_id in (select work_order_id from link_person_wo where person_id = ?)',
                                [$personId]
                            );
                    });
                }
                
                if (!empty($input['assigned_to_tech'])) {
                    $model = $model->whereRaw(
                        'work_order_id in (select work_order_id from link_person_wo where person_id = ?)',
                        [$input['assigned_to_tech']]
                    );
                }
                
                if (!empty($input['opened'])) {
                    $model = $model->whereRaw(
                        '(work_order.wo_status_type_id != ? AND work_order.wo_status_type_id != ?)',
                        [getTypeIdByKey('wo_status.completed'), getTypeIdByKey('wo_status.canceled')]
                    );
                }
            }
            
            if (!empty($input['hot'])) {
                $this->filterByHot($model);
            }

            if (!empty($input['completed_need_invoice'])) {
                $this->filterByCompletedNeedInvoice($model);
            }

            if (!empty($input['ready_to_quote'])) {
                $this->filterByReadyToQuote($model);
            }

            if (!empty($input['quote_needs_approval'])) {
                $this->filterByQuoteNeedsApproval($model);
            }

            if (!empty($input['quote_approved_need_invoice'])) {
                $this->filterByQuoteApprovedNeedInvoice($model);
            }

            if (!empty($input['invoice_needs_approval'])) {
                $this->filterByInvoiceNeedsApproval($model);
            }

            if (!empty($input['invoiced_not_sent'])) {
                $this->filterByInvoicedNotSent($model);
            }

            if (!empty($input['invoice_rejected'])) {
                $this->filterByInvoiceRejected($model);
            }

            if (!empty($input['updated_work_orders'])) {
                $this->filterByUpdatedWorkOrders($model);
            }

            if (!empty($input['past_due_work_orders'])) {
                $this->filterByPastDueWorkOrders($model);
            }

            if (!empty($input['techs_in_progress'])) {
                $this->filterByTechsInProgress($model);
            }

            $this->setWorkingModel($model);
            $data = parent::paginate($perPage, [], $order);
            $this->clearWorkingModel();

            $lockLimit = $this->app->config->get('system_settings.workorder_lock_limit_minutes', 15);

            /** @var EmailThreadWorkOrderRepository $emailThreadWorkOrderRepository */
            $emailThreadWorkOrderRepository = app(EmailThreadWorkOrderRepository::class);

            $workOrderIds = array_column($data->items(), 'work_order_id');
            $emailThreadIds = $emailThreadWorkOrderRepository->getEmailThreadIdsByWorkOrderIds($workOrderIds);

            foreach ($data->items() as $item) {
                $workOrder = $item->toArray();

                $item->email_thread_id = isset($emailThreadIds[$workOrder['work_order_id']])
                    ? $emailThreadIds[$workOrder['work_order_id']]
                    : null;

                if (!empty($workOrder['updated_at'])) {
                    $lastModified = $workOrder['updated_at'];
                } elseif (!empty($workOrder['modified_date'])) {
                    $lastModified = $workOrder['modified_date'];
                } else {
                    $lastModified = null;
                }
                
                if (!empty($item->locked_id) && $lastModified) {
                    $lockedTo = Carbon::parse($lastModified, 'utc')
                        ->addMinutes($lockLimit)
                        ->format('Y-m-d H:i:s');

                    if ($lockedTo < now('utc')->format('Y-m-d H:i:s')) {
                        $item->locked_to = null;
                    } else {
                        $item->locked_to = $lockedTo;
                    }
                } else {
                    $item->locked_to = null;
                }

                if (!$item->locked_to) {
                    $item->locked_id = null;
                    $item->locked_by = null;
                }
                
                if(isset($item->crm_priority)) {
                    $item->crm_priority = preg_replace('/\s*\(.*\)/', '', $item->crm_priority);
                }
            }
            
            $data = $data->toArray();
            
            if (config('app.crm_user') == 'bfc') {
                if (!empty($inputColumns) && in_array('assigned_to', $inputColumns)) {
                    $data = $this->addAssignedTechsBfc($data);
                } elseif (empty($inputColumns)) {
                    $data = $this->addAssignedTechsBfc($data);
                }
            }

            if (!empty($inputColumns)) {
                if (in_array('billNumbers', $inputColumns)) {
                    $data = $this->addBillsIntoOutput($data);
                }
            } else {
                $data = $this->addBillsIntoOutput($data);
            }

            $data = $this->addLastActivity($data);

            //adding route and sl_type moved below because there is more data to fetch then onlyu CallType and AreaId (CustGeographicID)
            /*if (isset($inputColumns) && ($slRoute || $slType)) {
                / @var SlRecordsManager $slRecordManager /
                $slRecordManager = $this->app->make(SlRecordsManager::class);
                foreach ($data['data'] as &$workOrder) {
                    $slRecordId = $slRecordManager->findSlRecordId('work_order', $workOrder['work_order_id']);

                    if ($slRecordId) {
                        / @var ServiceCall|Builder $serviceCall /
                        $serviceCall = new ServiceCall();
                        $serviceCall = $serviceCall->where('smServCall.ServiceCallID', '=', $slRecordId);
                        if ($slRoute) {
                            $serviceCall = $serviceCall->join(
                                'smArea',
                                'AreaId',
                                '=',
                                'smServCall.CustGeographicID'
                            );
                        }

                        $serviceCall = $serviceCall->first();

                        if ($slType) {
                            $workOrder['sl_type_id_value'] = ($serviceCall) ? $serviceCall->CallType : null;
                        }

                        if ($slRoute) {
                            $workOrder['route'] = ($serviceCall) ? $serviceCall->AreaId : null;
                        }
                    }
                }
            }
        */
            if (config('app.crm_user') == 'fs') {
                $lpwoNotIncludedStatuses = [
                    getTypeIdByKey('wo_vendor_status.canceled')
                ];
                
                if (isset($inputColumns)) {
                    if (in_array('assigned_to', $inputColumns)) {
                        if (in_array('comment', $inputColumns)) {
                            $statusCompleted = getTypeIdByKey('wo_vendor_status.completed');
                            if ($statusCompleted > 0) {
                                $lpwoNotIncludedStatuses[] = $statusCompleted;
                            }
                        }
                    }
                }
                
                if (isset($data['data']) && count($data['data']) > 0) {
                    $companyActiveTypeId = getTypeIdByKey('company_status.active');
                    $personActiveTypeId = getTypeIdByKey('person_status.active');

                    $activeTypeIds = [$companyActiveTypeId, $personActiveTypeId];
                    
                    foreach ($data['data'] as &$workOrder) {
                        $workOrder['description'] = iconv('latin1', 'utf-8', $workOrder['description']);
                        $workOrder['assigned_to'] = [];
//                        if ($withAssignedList) {
                        $sql = "
                            SELECT 
                                person.person_id, 
                                person_name(person.person_id) as tech_name, 
                                (t1.type_value) as status 
                            FROM
                                link_person_wo
                            JOIN 
                                person ON person.person_id = link_person_wo.person_id and person.status_type_id IN (
                                    ".implode(',', $activeTypeIds)."
                                )
                            LEFT JOIN 
                                type t1 on t1.type_id = link_person_wo.status_type_id
                            WHERE
                                work_order_id = ".$workOrder['work_order_id']." ";

                        if (count($lpwoNotIncludedStatuses) > 0) {
                            $sql .= " and link_person_wo.status_type_id not in (
                                ".implode(',',$lpwoNotIncludedStatuses)."
                            )";
                        }
                        $techs = DB::select(DB::raw($sql));
                        if (count($techs) > 0) {
                            foreach ($techs as $i => $t) {
                                $workOrder['assigned_to'][] = [
                                    'person_name' => $t->tech_name,
                                    'status'      => $t->status
                                ];
                            }
                        }
//                        }
                    }
                }
            }

            if (config('app.crm_user') == 'bfc') {
                if (isset($data['data']) && count($data['data']) > 0) {
                    $workOrderNumbers = [];
                    foreach ($data['data'] as &$workOrder) {
                        $workOrderNumbers[] = $workOrder['work_order_number'];
                    }
                    $woAdditionalInfo = $this->getSlManager()->getBFCAdditionalInfo($workOrderNumbers);
                    foreach ($data['data'] as &$workOrder) {
                        if (isset($woAdditionalInfo[$workOrder['work_order_number']])) {
                            //$workOrder['assigned_to'] = $woAdditionalInfo['assigned_to'];
                            $workOrder['task_status_id'] = $woAdditionalInfo[$workOrder['work_order_number']]['task_status_id'];
                            $workOrder['task_status_id_value'] = $woAdditionalInfo[$workOrder['work_order_number']]['task_status_id_value'];
                            $workOrder['scheduled_date'] = $woAdditionalInfo[$workOrder['work_order_number']]['scheduled_date'];
                            $workOrder['actual_completion_date'] = $woAdditionalInfo[$workOrder['work_order_number']]['actual_completion_date'];
                            $workOrder['promise_date'] = $woAdditionalInfo[$workOrder['work_order_number']]['promise_date'];
                            // $workOrder['invoice_status_type_id'] = $woAdditionalInfo[$workOrder['work_order_number']]['invoice_status_type_id'];
                            // $workOrder['invoice_status_type_id_value'] = $woAdditionalInfo[$workOrder['work_order_number']]['invoice_status_type_id_value'];
                            $workOrder['invoice_total'] = $woAdditionalInfo[$workOrder['work_order_number']]['invoice_total'];
                            $workOrder['customer_po'] = $woAdditionalInfo[$workOrder['work_order_number']]['customer_po'];
                            $workOrder['sl_type_id_value'] = $woAdditionalInfo[$workOrder['work_order_number']]['sl_type_id_value'];
                            $workOrder['route'] = $woAdditionalInfo[$workOrder['work_order_number']]['route'];
                        } else {
                            // $workOrder['assigned_to'] = '';
                            $workOrder['task_status_id'] = '';
                            $workOrder['task_status_id_value'] = '';
                            $workOrder['scheduled_date'] = '';
                            $workOrder['actual_completion_date'] = '';
                            $workOrder['promise_date'] = '';
                            // $workOrder['invoice_status_type_id'] = '';
                            // $workOrder['invoice_status_type_id_value'] = '';
                            $workOrder['invoice_total'] = '';
                            $workOrder['customer_po'] = '';
                            $workOrder['sl_type_id_value'] = '';
                            $workOrder['route'] = '';
                        }
                    }
                }
            }
        }

        // needed always - to set values
        $woData
            = $this->app->make(
                WorkOrderDataServiceContract::class,
                [$this->type, $this, $this->app]
            );

        // want to get data for filters and count data for colour filters
        if (isset($input['with_data']) && $input['with_data'] == 1 || $onlyFilters) {
            $woData = $woData->getAll();

            $data['fields'] = $woData;

            /** @var WorkOrderBoxCounterServiceContract $woCounter */
            $woCounter = $this->app->make(WorkOrderBoxCounterServiceContract::class);

            $data['boxes'] = $woCounter->generate();

            // get types to display different views depending on parameters
            $data['types'] = $this->getTypes();
        } else { // want to get data to display work orders values
            $woData = $woData->getValues();
        }

        return $data;
    }

    public function addBillsIntoOutput($data)
    {
        /** @var BillRepository $billRepository */
        $billRepository = $this->app->make(BillRepository::class);

        foreach ($data['data'] as $key => $work_order) {
            if (!empty($work_order['id'])) {
                $data['data'][$key]['bill_numbers'] = $billRepository->getWoVendorsBills($work_order['id']);
            }
        }

        return $data;
    }

    /**
     * Add SL assigned techs (assigned_to) data to work orders
     *
     * @param  array  $data
     *
     * @return array
     */
    private function addAssignedTechsBfc($data)
    {
        $workOrderNumbers = array_map(function ($i) {
            return $i['work_order_number'];
        }, $data['data']);

        $assignedTechs = $this->getSlManager()->getAssignedTechnicians($workOrderNumbers);

        foreach ($data['data'] as $key => $item) {
            $workOrderNumber = $item['work_order_number'];

            if (isset($assignedTechs[$workOrderNumber])) {
                $assignedTech = $assignedTechs[$workOrderNumber];

                $data['data'][$key]['assigned_to'] =
                    [
                        'id'          => $assignedTech['tech_id'],
                        'person_name' => $assignedTech['tech_name'],
                    ];
            }
        }
        return $data;
    }

    /**
     * Add last activity
     *
     * @param  array  $data
     *
     * @return array
     */
    private function addLastActivity(array $data)
    {
        $crmUser = config('app.crm_user');

        foreach ($data['data'] as $key => $item) {
            $data['data'][$key]['last_activity'] = null;

            if ($crmUser === 'fs') {
                $activities = $this->getUserActivityDataByWorkOrderIds([$item['work_order_id']], 1, 1);
                if ($activities) {
                    $activity = current($activities->items());

                    if ($activity) {
                        $data['data'][$key]['last_activity'] = $activity;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Get work orders without invoice
     *
     * @param  int  $perPage
     * @param  array  $order
     *
     * @return array
     */
    public function getNotInvoiced($perPage = 50, array $order = [])
    {
        $this->availableColumns = [
            'id'                      => 'work_order.work_order_id',
            'work_order_number'       => 'work_order.work_order_number',
            'company_person_id'       => 'work_order.company_person_id',
            'company_person_id_value' => 'person_name(work_order.company_person_id)',
            'work_order_status'       => 't(work_order.wo_status_type_id)',
            'actual_completion_date'  => 'work_order.completed_date',
            'time_sheets'             => 'time_sheets.count',
            'time_sheets_total'       => 'time_sheets.total',
            'purchase_order_entries'  => 'purchase_order_entry.count',
            'purchase_order_total'    => 'purchase_order_entry.total'
        ];

        $input = $this->request->all();

        /** @var WorkOrder|Builder $model */
        $model = $this->model;

        //Time sheets
        $timeSheets = DB::table('link_person_wo')
            ->select([
                'link_person_wo.work_order_id',
                DB::raw('count(time_sheet.time_sheet_id) as count'),
                DB::raw('SEC_TO_TIME(SUM(TIME_TO_SEC(time_sheet.duration))) as total')
            ])
            ->leftJoin('time_sheet', function ($join) {
                $join
                    ->on('time_sheet.table_id', '=', 'link_person_wo.link_person_wo_id')
                    ->where('time_sheet.table_name', 'link_person_wo')
                    ->whereNotIn('time_sheet.reason_type_id', [671, 601, 666, 669, 670, 672, 673, 685, 686]);
            })
            ->whereRaw('!(time_sheet.invoice_entry_id > 0)')
            ->groupBy('link_person_wo.work_order_id');

        if (!empty($input['person_id'])) {
            $timeSheets = $timeSheets
                ->whereIn('link_person_wo.person_id', explode(',', $input['person_id']));
        }
        //Time sheets - end

        //Purchase order entries
        $purchaseOrderEntries = DB::table('link_person_wo')
            ->select([
                'link_person_wo.work_order_id',
                DB::raw('count(purchase_order_entry.purchase_order_entry_id) as count'),
                DB::raw('ROUND(SUM(purchase_order_entry.total), 2) as total')
            ])
            ->leftJoin('purchase_order', 'purchase_order.link_person_wo_id', '=', 'link_person_wo.link_person_wo_id')
            ->leftJoin(
                'purchase_order_entry',
                'purchase_order_entry.purchase_order_id',
                '=',
                'purchase_order.purchase_order_id'
            )
            ->whereRaw('!(purchase_order_entry.invoice_entry_id > 1)')
            ->groupBy('link_person_wo.work_order_id');

        if (!empty($input['person_id'])) {
            $purchaseOrderEntries = $purchaseOrderEntries
                ->whereIn('link_person_wo.person_id', explode(',', $input['person_id']));
        }
        //Purchase order entries - end

        $model = $model
            ->leftJoinSub($timeSheets, 'time_sheets', 'time_sheets.work_order_id', '=', 'work_order.work_order_id')
            ->leftJoinSub(
                $purchaseOrderEntries,
                'purchase_order_entry',
                'purchase_order_entry.work_order_id',
                '=',
                'work_order.work_order_id'
            )
            ->where(function ($query) {
                $query
                    ->where('time_sheets.count', '>', 0)
                    ->orWhere('purchase_order_entry.count', '>', 0);
            });

        if (!empty($input['company_person_id'])) {
            $model = $model
                ->whereIn('work_order.company_person_id', explode(',', $input['company_person_id']));
        }

        $model = $this->setCustomColumns($model, false, false);
        $model = $this->setCustomSort($model);
        $model = $this->setCustomFilters($model);

        if (!isset($input['sort'])) {
            $model = $model->orderByDesc('work_order.work_order_id');
        }

        $this->setWorkingModel($model);
        $data = parent::paginate($perPage, [], $order)->toArray();
        $this->clearWorkingModel();

        return $data;
    }

    /**
     * @param  int[]  $excludeTypes
     *
     * @return mixed
     */
    public function getOpenForAll(array $excludeTypes = [0])
    {
        return $this->model
            ->select([
                DB::raw('AVG(DATEDIFF(NOW(), created_date)) as average_age'),
                DB::raw('count(*) as total'),
            ])
            ->whereNotIn('wo_status_type_id', $excludeTypes)
            ->first();
    }

    /**
     * @param  int[]  $excludeTypes
     * @param  int  $personId
     *
     * @return mixed
     */
    public function getOpenForPerson(array $excludeTypes, int $personId)
    {
        $workOrderIds = DB::table('link_person_wo')
            ->where('person_id', $personId)
            ->where('is_disabled', 0)
            ->pluck('work_order_id');

        return $this->model
            ->select([
                DB::raw('AVG(DATEDIFF(NOW(), created_date)) as average_age'),
                DB::raw('count(*) as total'),
            ])
            ->whereNotIn('wo_status_type_id', $excludeTypes)
            ->whereIn('work_order_id', $workOrderIds)
            ->first();
    }

    /**
     * @param  int[]  $excludeTypes
     *
     * @return mixed
     */
    public function getEcdForAll(array $excludeTypes)
    {
        return $this->model
            ->select([
                DB::raw('count(*) as total'),
            ])
            ->whereNotIn('wo_status_type_id', $excludeTypes)
            ->where('expected_completion_date', '>=', now())
            ->first();
    }

    /**
     * @param  int[]  $excludeTypes
     * @param  int  $personId
     *
     * @return mixed
     */
    public function getEcdForPerson(array $excludeTypes, int $personId)
    {
        $workOrderIds = DB::table('link_person_wo')
            ->where('person_id', $personId)
            ->where('is_disabled', 0)
            ->pluck('work_order_id');

        return $this->model
            ->select([
                DB::raw('count(*) as total'),
            ])
            ->whereNotIn('wo_status_type_id', $excludeTypes)
            ->whereIn('work_order_id', $workOrderIds)
            ->where('expected_completion_date', '>=', now())
            ->first();
    }

    public function getStatsAverageWorkOrders($range = '-3 months')
    {
        $startDate = Carbon::parse($range);

        $query = $this->model
            ->select([
                'work_order.work_order_id',
                'time_sheet.reason_type_id',
                DB::raw('SUM(time_sheet.duration) AS duration'),
                DB::raw('YEAR(work_order.created_date) AS created_year'),
                DB::raw('MONTH(work_order.created_date) AS created_month'),

            ])
            ->leftJoin('link_person_wo', 'link_person_wo.work_order_id', '=', 'work_order.work_order_id')
            ->leftJoin('time_sheet', function ($join) {
                $join
                    ->on('time_sheet.table_name', '=', DB::raw("'link_person_wo'"))
                    ->on('time_sheet.table_id', '=', 'link_person_wo.link_person_wo_id');
            })
            ->where('work_order.created_date', '>=', $startDate)
            ->whereNotNull('reason_type_id')
            ->groupBy('work_order.work_order_id')
            ->groupBy('time_sheet.reason_type_id')
            ->groupBy(DB::raw('YEAR(work_order.created_date)'))
            ->groupBy(DB::raw('MONTH(work_order.created_date)'));

        return DB::query()
            ->fromSub($query, 'sub')
            ->select([
                DB::raw('t(reason_type_id) as reason'),
                DB::raw('ROUND(AVG(duration)/60/60, 2) as duration'),
                'created_year',
                'created_month'
            ])
            ->groupBy('created_year')
            ->groupBy('created_month')
            ->groupBy('reason_type_id')
            ->get();
    }

    /**
     * @param  string  $range
     *
     * @return mixed
     */
    public function getStatsNewWorkOrders($range = '-3 months')
    {
        $startDate = Carbon::parse($range);

        return $this->model
            ->select([
                DB::raw('count(*) as total'),
                DB::raw('DATE(created_date) as date'),
            ])
            ->where('created_date', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_date)'))
            ->orderBy(DB::raw('DATE(created_date)'))
            ->get();
    }

    /**
     * @param  array  $excludeTypes
     *
     * @return mixed
     */
    public function getStatsOpenWorkOrders(array $excludeTypes)
    {
        return $this->model
            ->select([
                DB::raw('count(*) as total'),
                DB::raw('DATE_FORMAT(created_date, "%Y-%m") as date'),
            ])
            ->whereNotIn('wo_status_type_id', $excludeTypes)
            ->groupBy(DB::raw('DATE_FORMAT(created_date, \'%Y-%m\')'))
            ->orderBy(DB::raw('DATE_FORMAT(created_date, \'%Y-%m\')'))
            ->get();
    }

    public function getStatsOpenWorkOrdersByPersonId($personId)
    {
        $excludeTypes = [
            getTypeIdByKey('wo_status.completed'),
            getTypeIdByKey('wo_status.canceled')
        ];
        
        $today = Carbon::now()->format('Y-m-d');
        $yesterday = Carbon::now()->subDay(1)->format('Y-m-d');
        $tomorrow = Carbon::now()->addDay(1)->format('Y-m-d');
        $last7Days = Carbon::now()->subDay(7)->format('Y-m-d');
        $last30Days = Carbon::now()->subDay(30)->format('Y-m-d');
        $next7Days = Carbon::now()->addDay(7)->format('Y-m-d');
        
        return [
            'last_30_days' => $this->getTotalWorkOrders($personId, $excludeTypes, $last30Days),
            'last_7_days' => $this->getTotalWorkOrders($personId, $excludeTypes, $last7Days, $yesterday),
            'current' => $this->getTotalWorkOrders($personId, $excludeTypes, $today),
            'next_7_days' => $this->getTotalWorkOrders($personId, $excludeTypes, $tomorrow, $next7Days)
        ];
    }

    private function getTotalWorkOrders($personId, array $excludeTypes, string $from, string $to = null)
    {
        $model = $this->model->newInstance();
        
        return $model->select([
                DB::raw('count(*) as total'),
            ])
            ->join('link_person_wo', function ($join) use ($personId, $from, $to) {
                $join
                    ->on('work_order.work_order_id', '=', 'link_person_wo.work_order_id')
                    ->where('link_person_wo.person_id', $personId)
                    ->where('link_person_wo.is_disabled', 0);

                if (is_null($to)) {
                    $join->where(DB::raw('DATE(link_person_wo.scheduled_date)'), $from);
                } else {
                    $join
                        ->where(DB::raw('DATE(link_person_wo.scheduled_date)'), '>=', $from)
                        ->where(DB::raw('DATE(link_person_wo.scheduled_date)'), '<=', $to);
                }
            })
            ->whereNotIn('wo_status_type_id', $excludeTypes)
            ->value('total');
    }
    
    /**
     * @param $model
     */
    public function filterByHot(&$model): void
    {
        $model = $model
            ->whereRaw(
                '(SELECT count(calendar_event_id) FROM calendar_event c WHERE c.tablename=\'work_order\' AND c.record_id = work_order.work_order_id AND c.is_completed=0 AND c.type_id = ?) > 0',
                [getTypeIdByKey('task.hot')]
            );
    }

    /**
     * @param $model
     */
    public function filterByCompletedNeedInvoice(&$model): void
    {
        $model = $model
            ->where('invoice_status_type_id', getTypeIdByKey('wo_billing_status.update_required'))
            ->where('wo_status_type_id', getTypeIdByKey('wo_status.completed'));
    }

    /**
     * @param $model
     */
    public function filterByReadyToQuote(&$model): void
    {
        $model = $model
            ->where('invoice_status_type_id', getTypeIdByKey('wo_billing_status.ready_to_invoice'))
            ->whereIn('work_order.client_status', ['COMPLETED (confirmed)', 'COMPLETED (pending confirmation)'])
            ->whereNotIn('work_order.work_order_id', function ($q) {
                $q->select('quote.table_id')->from('quote')
                    ->where('quote.table_name', 'work_order')
                    ->whereRaw('quote.table_id = work_order.work_order_id');
            })
            ->whereIn('company_person_id', function ($q) {
                $q->select('company_person_id')->from('customer_settings')
                    ->where('meta_data', 'LIKE', $this->getMetaData());
            });
    }

    /**
     * @param $model
     */
    public function filterByQuoteNeedsApproval(&$model): void
    {
        $model = $model
            ->where('invoice_status_type_id', getTypeIdByKey('wo_billing_status.ready_to_invoice'))
            ->where('work_order.client_status', 'COMPLETED (confirmed)')
            ->whereIn('work_order.work_order_id', function ($q) {
                $q->select('quote.table_id')->from('quote')
                    ->where('quote.table_name', 'work_order')
                    ->whereRaw('quote.table_id = work_order.work_order_id')
                    ->where('quote.type_id', getTypeIdByKey('quote_status.internal_waiting_quote_approval'));
            })
            ->whereIn('company_person_id', function ($q) {
                $q->select('company_person_id')->from('customer_settings')
                    ->where('meta_data', 'LIKE', $this->getMetaData());
            });
    }

    /**
     * @param $model
     */
    public function filterByQuoteApprovedNeedInvoice(&$model): void
    {
        $status = [
            getTypeIdByKey('quote_status.internal_waiting_quote_approval'),
            getTypeIdByKey('quote_status.internal_quote_approved'),
            getTypeIdByKey('quote_status.internal_waiting_invoice_approval'),
        ];

        $model = $model
            ->where('invoice_status_type_id', getTypeIdByKey('wo_billing_status.ready_to_invoice'))
            ->where('work_order.client_status', 'COMPLETED (confirmed)')
            ->whereIn('work_order.work_order_id', function ($q) use ($status) {
                $q->select('quote.table_id')->from('quote')
                    ->where('quote.table_name', 'work_order')
                    ->whereRaw('quote.table_id = work_order.work_order_id')
                    ->whereIn('quote.type_id', $status);
            })
            ->whereIn('company_person_id', function ($q) {
                $q->select('company_person_id')->from('customer_settings')
                    ->where('meta_data', 'LIKE', $this->getMetaData());
            })
            ->whereNotIn('work_order.work_order_id', function ($q) {
                $q->selecT('invoice.table_id')->from('invoice')
                    ->where('invoice.table_name', 'work_order')
                    ->whereRaw('invoice.table_id = work_order.work_order_id');
            });
    }

    /**
     * @param $model
     */
    public function filterByInvoiceNeedsApproval(&$model): void
    {
        $model = $model
            ->leftJoin('invoice', function ($q) {
                $q->on('invoice.table_id', '=', 'work_order.work_order_id');
                $q->on('invoice.table_name', '=', DB::raw('"work_order"'));
            })
            ->where('invoice.invoice_id', '>', 0)
            ->where('invoice.status_type_id', getTypeIdByKey('invoice_status.internal_waiting_for_approval'));
    }

    /**
     * @param $model
     */
    public function filterByInvoicedNotSent(&$model): void
    {
        $model = $model
            ->leftJoin('invoice', function ($q) {
                $q->on('invoice.table_id', '=', 'work_order.work_order_id');
                $q->on('invoice.table_name', '=', DB::raw('"work_order"'));
            })
            ->whereNotNull('invoice.invoice_id')
            ->where('invoice.status_type_id', getTypeIdByKey('invoice_status.internal_approved'));
    }

    /**
     * @param $model
     */
    public function filterByInvoiceRejected(&$model): void
    {
        $model = $model
            ->leftJoin('invoice', function ($q) {
                $q->on('invoice.table_id', '=', 'work_order.work_order_id');
                $q->on('invoice.table_name', '=', DB::raw('"work_order"'));
            })
            ->whereNotNull('invoice.invoice_id')
            ->where('invoice.status_type_id', getTypeIdByKey('invoice_status.internal_rejected'));
    }

    /**
     * @param $model
     */
    public function filterByUpdatedWorkOrders(&$model): void
    {
        $subQuery = DB::table('email')
            ->selectRaw('count(email_id)')
            ->whereRaw('email.work_order_id = work_order.work_order_id')
            ->limit(1);

        $model = $model->whereRaw("1 < ({$subQuery->toSql()})", $subQuery->getBindings());
    }

    /**
     * @param $model
     */
    public function filterByPastDueWorkOrders(&$model): void
    {
        $model = $model
            ->whereNotIn('work_order.wo_status_type_id', [
                getTypeIdByKey('wo_status.completed'),
                getTypeIdByKey('wo_status.canceled'),
            ])
            ->where('expected_completion_date', '<', DB::raw('now()'));
    }

    /**
     * @param $model
     */
    public function filterByTechsInProgress(&$model): void
    {
        $model = $model->join('link_person_wo', function ($join) {
            $join
                ->on('work_order.work_order_id', '=', 'link_person_wo.work_order_id')
                ->where('link_person_wo.is_disabled', 0)
                ->where('link_person_wo.status_type_id', getTypeIdByKey('wo_vendor_status.in_progress'));
        });
    }

    /**
     * @param $personId
     *
     * @return mixed
     */
    public function unlockAllByPersonId($personId)
    {
        return $this->model
            ->where('locked_id', $personId)
            ->update(['locked_id' => 0]);
    }

    /**
     * @param  array  $addressIds
     *
     * @return array
     */
    public function getLastWorkOrdersByAddressIds(array $addressIds)
    {
        if ($addressIds) {
            $workOrderIds = $this->model
                ->select(DB::raw('MAX(work_order_id) as work_order_id'))
                ->whereIn('shop_address_id', $addressIds)
                ->groupBy('shop_address_id')
                ->pluck('work_order_id')
                ->all();

            if ($workOrderIds) {
                return $this->model
                    ->whereIn('work_order_id', $workOrderIds)
                    ->get();
            }
        }
        
        return [];
    }


    /**
     * @param  array  $assetIds
     *
     * @return array
     */
    public function getLastWorkOrdersByAssetIds(array $assetIds)
    {
        if ($assetIds) {
            $workOrderIds = $this->model
                ->select(DB::raw('MAX(work_order_id) as work_order_id'))
                ->join('asset', 'asset.address_id', '=', 'work_order.shop_address_id')
                ->whereIn('asset.asset_id', $assetIds)
                ->groupBy('shop_address_id')
                ->pluck('work_order_id')
                ->all();

            if ($workOrderIds) {
                return $this->model
                    ->select([
                        'asset.asset_id',
                        'asset.name',
                        'work_order.*'
                    ])
                    ->join('asset', 'asset.address_id', '=', 'work_order.shop_address_id')
                    ->whereIn('work_order_id', $workOrderIds)
                    ->get();
            }
        }

        return [];
    }

    
    /**
     * Add joins (if necessary) to count query. Using all joins for count query
     * if no joins are needed in where statement affects performance a lot
     *
     * @param  Builder  $model
     * @param  WorkOrderQueryGeneratorService  $queryGenerator
     * @param  array  $filters
     *
     * @return Builder
     */
    protected function addCountModelJoins(
        $model,
        WorkOrderQueryGeneratorService $queryGenerator,
        array $filters
    ) {
        if (!$filters) {
            return $model;
        }

        $joinsToAdd = [];
        foreach ($filters as $filter => $joinNames) {
            if (!is_array($joinNames)) {
                $joinNames = [$joinNames];
            }
            foreach ($joinNames as $joinName) {
                if (!in_array($joinName, $joinsToAdd)) {
                    $joinsToAdd[] = $joinName;
                }
            }
        }

        foreach ($joinsToAdd as $join) {
            $methodName = 'add'.Str::studly($join).'Join';
            $model = $queryGenerator->{$methodName}($model);
        }

        return $model;
    }

    /**
     * Get those filters that will be that require making joins for count query
     *
     * @param  array  $input
     *
     * @return array
     */
    protected function getUsedJoinableFilters(array $input)
    {
        $filters = [];
        $inputKeys = array_flip(array_keys($input));

        foreach ($this->joinableFilters as $filter => $joinName) {
            if (isset($inputKeys[$filter])) {
                $filters[$filter] = $joinName;
                unset($inputKeys[$filter]);
            }
        }

        return $filters;
    }

    /**
     * Modify output
     *
     * @param  array  $data
     * @param  array  $woData
     *
     * @return array
     */
    protected function modifyOutput(array $data, array &$woData)
    {
        if ($data) {
            $data = $this->getValuesAndClearBillData($data, $woData);
        }

        return $data;
    }

    /**
     * Set values for each work order for some id columns and clears
     * some unused bill numbers data
     *
     * @param  array  $data
     * @param  array  $woData
     *
     * @return array
     */
    protected function getValuesAndClearBillData(array $data, array &$woData)
    {
        // merge companies and persons just to use one array
        $woData['company_person_id']['data']
            = $woData['company_person_id']['data']['companies']
            + $woData['company_person_id']['data']['persons'];

        // keys that will be filled based on $woData
        $lookupKeys = [
            'via_type_id',
            'wo_status_type_id',
            'bill_status_type_id',
            'invoice_status_type_id',
            'company_person_id',
            'quote_status_type_id',
        ];
        foreach ($data['data'] as $k => $v) {
            foreach ($lookupKeys as $key) {
                if (array_key_exists($key, $v)) {
                    $data['data'][$k][$key.'_value']
                        = isset($woData[$key]['data'][$v[$key]])
                        ? $woData[$key]['data'][$v[$key]] : '';
                }
            }
            foreach ($data['data'][$k]['bill_numbers'] as $id => $bill) {
                unset($data['data'][$k]['bill_numbers'][$id]['work_order_id']);
                unset($data['data'][$k]['bill_numbers'][$id]['id']);
            }
        }

        return $data;
    }

    /**
     * Run custom filters
     *
     * @param  \Illuminate\Database\Query\Builder  $model
     * @param  array  $input
     *
     * @return array
     */
    protected function runCustomFilters($model, array $input)
    {
        $filterService = new WorkOrderFilterService($this, $this->type);

        foreach ($this->customFilters as $filter) {
            if (!isset($input[$filter])) {
                continue;
            }
            $model = $filterService->addCustomCondition(
                $filter,
                $model,
                $input[$filter],
                $input
            );

            unset($input[$filter]);
        }

        return [$model, $input];
    }

    /**
     * Set sortable columns based on columns that will be used to get from
     * database
     *
     * @param  array  $columns
     */
    protected function setSortableColumns(array $columns)
    {
        foreach ($columns as $column) {
            $column = trim(str_replace("\n", ' ', mb_strtolower($column)));
            $pos = mb_strrpos($column, ' ');
            if ($pos === false) {
                $pos2 = strrpos($column, '.');
                if ($pos2 === false) {
                    $this->sortable[] = $column;
                } else {
                    $this->sortable[] = mb_substr($column, $pos2 + 1);
                    $this->sortableMap[mb_substr($column, $pos2 + 1)] = $column;
                }
            } else {
                if (Str::endsWith(mb_substr($column, 0, $pos), ' as')) {
                    $this->sortable[] = mb_substr($column, $pos + 1);
                } else {
                    // do nothing - it's unknown column format
                    //@todo - we could log it if we want to use custom code
                }
            }
        }
    }

    /**
     * @param $workOrderId
     *
     * @return mixed
     */
    public function getWorkOrderData($workOrderId)
    {
        return $this->model
            ->select([
                'work_order.work_order_number',
                'address.address_name',
                'address.site_name',
                DB::raw('person_name(work_order.company_person_id) as customer_name'),
                DB::raw('sl_records.sl_record_id as customer_id')
            ])
            ->join('address', 'work_order.shop_address_id', '=', 'address.address_id')
            ->leftJoin('sl_records', function ($join) {
                $join
                    ->on('sl_records.table_name', '=', DB::raw('"person"'))
                    ->on('sl_records.record_id', '=', 'work_order.company_person_id');
            })
            ->where('work_order.work_order_id', $workOrderId)
            ->first();
    }

    /**
     * Set quote_status_type_id column
     *
     * @param  array  $columns
     *
     * @return array $columns
     */
    protected function setQuoteStatusColumn(array $columns)
    {
        return $columns = array_merge(
            $columns,
            ['IFNULL(work_order.quote_status_type_id, 0) as quote_status_type_id']
        );
    }

    /**
     * Set allow ghost link (from config)
     */
    protected function setAllowGhostLink()
    {
        $this->allowGhostLink =
            (int) config('crm_settings.allow_ghost_link', 0);
    }

    /**
     * Get types that are necessary to display different work order list
     * depending on parameters
     *
     * @return mixed
     */
    protected function getTypes()
    {
        return $this->type->getListByKeys($this->statusesKeys);
    }

    /**
     * Get project managers list based on entries for work_order
     *
     * @return array
     */
    public function getProjectsManagersList()
    {
        $data = DB::select(
            'SELECT project_manager_person_id,
             person_name(project_manager_person_id) AS `person_name`
             FROM (SELECT DISTINCT project_manager_person_id FROM work_order) d
             ORDER BY person_name'
        );

        $out = [];
        foreach ($data as $item) {
            $out[$item->project_manager_person_id] = $item->person_name;
        }

        return $out;
    }

    /**
     * Get client status list based on entries for work_order
     *
     * @return mixed
     */
    public function getClientStatusList()
    {
        return $this->model->select('client_status')->distinct()
            ->orderBy('client_status')->pluck('client_status', 'client_status')
            ->all();
    }

    /**
     * Get configuration - request rules together with user roles
     *
     * @param  string  $type
     *
     * @return array
     */
    public function getConfig($type)
    {
        $output = $this->getRequestRules($type);

        $woData
            =
            $this->app->makeWith(
                \App\Modules\WorkOrder\Services\WorkOrderDataServiceContract::class,
                [$this->type, $this, $this->app]
            );

        $data = $woData->getRecordCreateData();

        $out['fields'] = array_merge_recursive($output, $data);

        // faking item - some values are only displayed and are not used for input
        $out['item']['wo_status_type_id_value']
            = $this->type->getValueByKey('wo_status.new');
        $out['item']['bill_status_type_id_value']
            = $this->type->getValueByKey('bill_status.no_bill_received');

        return $out;
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $input)
    {
        $model = null;
        $pickedUp = null;
        $vendors = null;

        DB::transaction(function () use (
            $input,
            &$model,
            &$pickedUp,
            &$vendors
        ) {
            if(isCrmUser('fs') && !empty($input['description'])) {
                /** @var WorkOrderService $workOrderService */
                $workOrderService = app(WorkOrderService::class);

                $input['subject'] = $workOrderService->getSubjectFromDescription($input['description']);
            }
            
            if (is_null($input['category'])) {
                $input['category'] = '';
            }
            
            if ($input['pickup_and_assign'] == 1) {
                $input['pickup_id'] = $this->getCreatorPersonId();
                $input['wo_status_type_id']
                    = $this->type->getIdByKey('wo_status.assigned_in_crm');
            } else {
                $input['wo_status_type_id']
                    = $this->type->getIdByKey('wo_status.new');
                $input['pickup_id'] = 0;
            }
            $input['bill_status_type_id']
                = $this->type->getIdByKey('bill_status.no_bill_received');

            $input['creator_person_id'] = $this->getCreatorPersonId();

            // manual creation - we want to set custom fillable fields
            $model = $this->newInstance();
            $model->setFillableType('create');
            $model->fill($input);
            $model->save();

            // auto assigning work order number
            $autoWorkOrderNumber = false;
            if ($model->getCustomerSettingId()) {
                $cs = $this->makeRepository('CustomerSettings');
                $custSet = $cs->findSoft($model->getCustomerSettingId());
                if ($custSet) {
                    $autoWorkOrderNumber
                        = $custSet->getAutoGenerateWorkOrderNumber();
                }
            }
            if (
                $autoWorkOrderNumber
                || (!$autoWorkOrderNumber
                    && $model->getWorkOrderNumber() == '')
            ) {
                $model->work_order_number = $model->getId();
                $model->save();
            }

            // clear fillable fields to avoid any unpredicted results
            $model->clearFillable();

            if ($input['pickup_and_assign'] == 1) {
                $link = $this->getRepository('LinkPersonWo', 'WorkOrder');

                $pickedUp = $link->assignPickedUp(
                    $model->getId(),
                    $this->getCreatorPersonId()
                );
                $link->updateInProgressPriorities(
                    $this->getCreatorPersonId(),
                    $pickedUp->getId()
                );
            }

            if (isset($input['vendor_to_assign_person_id'])) {
                $vendors = $this->assignVendors($model, $input);
            }

            if (!empty($input['assign_to_person_ids'])) {
                /** @var WorkOrderAddVendorsService $workOrderAddVendorsService */
                $workOrderAddVendorsService = app(WorkOrderAddVendorsService::class);

                $vendors = array_map(function ($item) {
                    return ['person_id' => $item];
                }, $input['assign_to_person_ids']);

                try {
                    $workOrderAddVendorsService->run($model->getId(), 'work', $vendors, null);
                } catch (\Exception $e) {
                    Log::error('Cannot create link person wo: '.$e->getMessage());
                }
            }
        });

        return [$model, $pickedUp, $vendors];
    }

    /**
     * {@inheritdoc}
     *
     * @throws \App\Core\Exceptions\LockedMismatchException
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function updateWithIdAndInput($id, array $input, $type = 'edit', $withoutActualCompletionDate = false)
    {
        /** @var WorkOrder $object */
        $object = $this->getModel()->find($id);

        if ($object === null) {
            throw with(new ModelNotFoundException())
                ->setModel(get_called_class());
        }

        $lockedId = $object->getLockedId();

        if (!empty($lockedId) && $lockedId != getCurrentPersonId()) {
            $exception = $this->app->make(LockedMismatchException::class);
            $exception->setData([
                'table_name'        => 'work_order',
                'id'                => $id,
                'locked_id'         => $object->getLockedId(),
                'current_person_id' => getCurrentPersonId(),
            ]);

            throw $exception;
        }

        $messages = [];
        if ($type == 'edit' && !$withoutActualCompletionDate) {
            list($input, $messages) = $this->setActualCompletionDate(
                $object,
                $input
            );
        }

        $object->setFillableType($type);
        $object->fill($input);

        if (isset($input['unlock']) && $input['unlock'] == 1) {
            $object->locked_id = 0;
        }

        $object->save();

        // clear fillable fields to avoid any unpredicted results
        $object->clearFillable();

        return [$this->getModel()->find($id), $messages];
    }

    /**
     * Updates Model object with basic data
     *
     * @param  int  $id
     * @param  array  $input
     *
     * @return array
     * @throws LockedMismatchException
     */
    public function basicUpdateWithIdAndInput($id, array $input)
    {
        list($model, $messages) = $this->updateWithIdAndInput(
            $id,
            $input,
            'basicedit'
        );

        return $model;
    }

    /**
     * Updates Work Order note
     *
     * @param  int  $id
     * @param  array  $input
     *
     * @return array
     * @throws LockedMismatchException
     */
    public function updateNote($id, array $input)
    {
        list($model, $messages) = $this->updateWithIdAndInput(
            $id,
            $input,
            'noteedit'
        );

        return $model;
    }

    /**
     * Set actual_completion_date (in fact unsets this key depending on
     * some conditions)
     *
     * @param  WorkOrder  $object
     * @param  array  $input
     *
     * @return array
     */
    protected function setActualCompletionDate($object, array $input)
    {
        $acd = $input['actual_completion_date'];
        $messages = [];

        // CRMBFC-2769 Eliminacja tabelki history
        //if (!$acd && $object->getActualCompletionDate()) {
        //    $history = $this->getRepository('History');
        //    $last = $history->getRecordColumnLastHistory(
        //        'work_order',
        //        $object->getId(),
        //        'actual_completion_date'
        //    );
        //    if (!$last || $last->getPersonId() == getCurrentPersonId()) {
        //        return [$input, $messages];
        //    }
        //
        //    $interval = (strtotime(date('Y-m-d H:i:s'))
        //        - strtotime($history->getCreatedAt()));
        //    $interval /= 60; // in minutes
        //
        //    if ($interval < 10) {
        //        $messages[] = [
        //            'key' => 'workorder.actual_completion_date_not_updated',
        //        ];
        //        unset($input['actual_completion_date']);
        //    } elseif ($interval >= 10 && $interval <= 30) {
        //        unset($input['actual_completion_date']);
        //    } elseif ($interval > 30) {
        //        return [$input, $messages];
        //    }
        //}

        return [$input, $messages];
    }

    /**
     * Assign vendors for work order
     *
     * @param  WorkOrder  $model
     * @param  array  $input
     *
     * @return array|null
     */
    protected function assignVendors($model, array $input)
    {
        $vendors = [];
        if (!$this->isValidVendors($input)) {
            return null;
        }

        $link = $this->getRepository('LinkPersonWo', 'WorkOrder');

        foreach ($input['vendor_to_assign_person_id'] as $i => $personId) {
            if (!$personId) {
                continue;
            }
            $vendor = $link->assignVendor(
                $model->getId(),
                $personId,
                $input['vendor_to_assign_status_id'][$i],
                $input['vendor_to_assign_estimated_time'][$i],
                $input['vendor_to_assign_send_notice'][$i],
                $input['vendor_to_assign_description'][$i]
            );
            $link->updateInProgressPriorities($personId, $vendor->getId());
            $vendors[] = $vendor;
        }

        return $vendors;
    }

    /**
     * Verify if input data for vendors are valid
     *
     * @param  array  $input
     *
     * @return bool
     */
    protected function isValidVendors(array $input)
    {
        $keys = [
            'vendor_to_assign_person_id',
            'vendor_to_assign_status_id',
            'vendor_to_assign_estimated_time',
            'vendor_to_assign_send_notice',
            'vendor_to_assign_description',
        ];

        $numbers = [];

        foreach ($keys as $key) {
            if (!isset($input[$key]) || !is_array($input[$key])) {
                return false;
            }
            $numbers[] = count($input[$key]);
        }

        if (count(array_unique($numbers)) == 1) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function show($id, $forEdit = false)
    {
        $input = $this->getInput();
        $record = $this->find($id);
        $output['item'] = $record->toArray();

        // lock status verification
//        if (config('app.crm_user') !== 'bfc' && ($forEdit || !empty($input['edit']))) {
        if (config('app.crm_user') !== 'bfc') {
            list($record, $output) = $this->handleLockStatus($record, $output);
        }

        $this->setLockedTo($record, $output);
        
        if ($forEdit) {
            // validation rules
            $output['fields'] = $this->getRequestRules('update');
            $woData = $this->app->makeWith(
                \App\Modules\WorkOrder\Services\WorkOrderDataServiceContract::class,
                [$this->type, $this, $this->app]
            );

            // data for updating (selects)
            $data['fields'] = $woData->getRecordUpdateData();

            // extra selects data that should be loaded
            $personData = $this->getPersonData(
                $record->getCompanyPersonId(),
                true
            );
            $data['fields']['customer_setting_id']['data'] = $personData->customer_settings;
            $data['fields']['shop_address_id']['data'] = $personData->addresses;

            $output = array_merge_recursive($output, $data);
            $output['link_person_wo'] = $this->getRepository('LinkPersonWo', 'WorkOrder')
                ->getLinkPersonWo($id);
        }

        $withAssigned = $this->request->query('with_assigned', 0);
        if ($withAssigned) {
            $output['item']['assigned_to'] = $record->assignedTo()->get()->toArray();
        }
        
        $detailed = $this->request->query('detailed', '');

        if ($forEdit || $detailed != '') {
            //CRMBFC-2769 - Eliminacja tabelki history
            //$record->load('pickupDate');
            //if ($record->pickupDate) {
            //    $output['item']['pickup_date'] = $record->pickupDate->getCreatedAt();
            //}

            // cancelled data
            $cancelledId = $this->type->getIdByKey('wo_status.canceled');
            if ($output['item']['wo_status_type_id'] == $cancelledId) {
                $hRepo = $this->makeRepository('History');
                $output['item']['canceled']
                    = $hRepo->getRecordColumnValueToLastHistory(
                        'work_order',
                        $record->getId(),
                        'wo_status_type_id',
                        $cancelledId
                    );
            }

            // set values for all type id columns
            $output = $this->addTypeColumnsValues($record, $output, $forEdit);
        }

        // get data for current location and customer_setting_id
        if ($detailed != '') {
            $output = $this->addOtherColumnsValues($record, $output);
        }

        // data to link current work order to any other Module
        $output['item']['link_to'] = [
            'table_name' => 'work_order',
            'record_id'  => $id,
        ];

        $output['item']['opening_hours'] = null;
        if (!empty($output['item']['shop_address_id'])) {
            $ashrRepo = $this->makeRepository('AddressStoreHours', 'Address');
            $output['item']['opening_hours'] = $ashrRepo->getOpeningHoursByAddressId($output['item']['shop_address_id']);
        }

        if (!empty($output['item']['customer_setting_id'])) {
            $csRepo = $this->makeRepository('CustomerSettings', 'CustomerSettings');
            try {
                $output['item']['customer_setting_id_value'] = $csRepo
                    ->findSoft($output['item']['customer_setting_id'])
                    ->person()
                    ->first()
                    ->getName();
            } catch (\Exception $e) {
                $output['item']['customer_setting_id_value'] = $output['item']['customer_setting_id'];
            }
        }

        // information whether vendors can be assigned to this work order
        $output['item']['can_assign_vendors'] = $record->getInvoiceStatusTypeId() != getTypeIdByKey('wo_billing_status.invoiced');

        if (!empty($output['item']['scheduled_date'])) {
            if (strlen($output['item']['scheduled_date']) === 10) {
                $output['item']['scheduled_date'] = getDateOrNull($output['item']['scheduled_date']);
            } else {
                $output['item']['scheduled_date'] = getDateTimeOrNUll($output['item']['scheduled_date']);
            }
        }

        if (config('app.crm_user') === 'fs') {
            /** @var ChatRoomService $chatRoomService */
            $chatRoomService = app(ChatRoomService::class);
            $chatRoom = $chatRoomService->getChatRoomForWorkOrderId($id);

            $output['item']['chat_room_id'] = $chatRoom ? $chatRoom->getId() : null;
        }
        
        return $output;
    }

    /**
     * Show record and any necessary data for basic edit. `Basic` in this method
     * name means that work order update itself will be basic however to basic
     * work order data may be attached many work order related data
     *
     * @param  int  $id
     *
     * @return array
     */
    public function basicEdit($id)
    {
        /** @var \App\Modules\WorkOrder\Models\WorkOrder $record */
        $record = $this->find($id, [
            'work_order.*',
            '(UTC_TIMESTAMP()-modified_date) as last_edit_delay',
            "DATE_FORMAT(DATE_SUB(expected_completion_date,
                INTERVAL 1 DAY),'%m/%d/%Y')  as day_before_ecd",
        ]);
        $output['item'] = $record->toArray();

        // get extra modules settings
        $output['modules'] = $this->getModulesInfo();

        // lock status verification
        if (config('app.crm_user') !== 'bfc') {
            list($record, $output) = $this->handleLockStatus($record, $output);
        }

        // validation rules
        $output['fields'] = $this->getRequestRules('basicupdate');

        // ECD warning
        $output['item']['ecd_warning'] = $this->getEcdWarningStatus($record);

        //CRMBFC-2769 - Eliminacja tabelki history
        //$record->load('pickupDate');
        //if ($record->pickupDate) {
        //    $output['item']['pickup_date'] = $record->pickupDate->getCreatedAt();
        //}

        // cancelled data
        $cancelledId = $this->type->getIdByKey('wo_status.canceled');
        if ($output['item']['wo_status_type_id'] == $cancelledId) {
            $hRepo = $this->getRepository('History');
            $output['item']['canceled']
                = $hRepo->getRecordColumnValueToLastHistory(
                    'work_order',
                    $record->getId(),
                    'wo_status_type_id',
                    $cancelledId
                );
        }

        // for some statuses extensions shouldn't be added
        $output['item']['extensions_may_be_added']
            = $this->isValidForAddingExtension($record);

        // not billable data (only for displaying)
        $notBillableId = $this->type->getIdByKey('bill_status.not_billable');
        if ($output['item']['bill_status_type_id'] == $notBillableId) {
            $hRepo = $this->getRepository('History');
            $output['item']['not_billable']
                = $hRepo->getRecordColumnValueToLastHistory(
                    'work_order',
                    $record->getId(),
                    'bill_status_type_id',
                    $notBillableId
                );
        }

        // set values for all type id columns
        $output = $this->addTypeColumnsValues($record, $output, false);

        // get data for current location and customer_setting_id
        $output = $this->addOtherColumnsValues($record, $output);

        // now extra data specific to basic edit
        $output['extensions']['items'] = $record->detailedExtensions;
        $ext = new WorkOrderExtensionRequest();
        $output['extensions']['fields'] = $ext->getFrontendRules();

        // get time sheets
        /** @var \App\Modules\TimeSheet\Repositories\TimeSheetRepository $tsRepo */
        $tsRepo = $this->getRepository('TimeSheet');
        list($timeSheets, $summary, $vendorsSummary)
            = $tsRepo->getForWo($record->getId(), true, true, true, true);
        $output['timesheets']['items'] = $timeSheets;
        $output['timesheets']['summary'] = $summary;
        $output['timesheets']['vendors_summary'] = $vendorsSummary;

        $output['timesheets']['summary']['total_time_cost']
            = $this->calculateTotalTimeCost($summary);

        // get assigned vendors/techs
        list(
            $vendorsTechs, $vendorsTechsIds, $readyToInvoice,
            $notCompletedVendorsCount
            )
            = $this->getVendorsTechs($record, $vendorsSummary);

        $output['item']['ready_to_invoice'] = $readyToInvoice;

        // get bills
        list($vendorsTechs, $billingStatuses)
            = $this->getVendorsBills($vendorsTechs, $vendorsTechsIds);
        // get vendor bills rejections
        $vendorsTechs = $this->getVendorBillsRejections(
            $vendorsTechs,
            $vendorsTechsIds
        );

        list($vendorsTechs, $allBillsTotal)
            = $this->getBillsSummary($vendorsTechs, $billingStatuses);

        $output['item']['all_bills_total'] = $allBillsTotal;

        list($vendorsTechs, $allOrdersTotal)
            = $this->getVendorsPurchaseOrders(
                $vendorsTechs,
                $vendorsTechsIds
            );

        $output['item']['all_orders_total'] = $allOrdersTotal;

        // get files
        list($vendorsTechs, $files, $workOrderFilesCount)
            = $this->getFiles($record->getId(), $vendorsTechs);

        // get opened work orders in the same location
        $openedWorkOrders
            = $this->getOpenedInLocation(
                $record->getShopAddressId(),
                $record->getId()
            );
        $output['location']['open_workorders'] = $openedWorkOrders;

        $lastLocationVendors = $this->getLastLocationVendors($record);

        $output['location']['last_vendors'] = $lastLocationVendors;

        // get opened work orders in the same city
        $openedCityWorkOrders
            = $this->getOpenedInCity(
                $record->getShopAddressId(),
                $record->getId()
            );
        $output['location']['city_open_workorders'] = $openedCityWorkOrders;

        $output['item']['files_count'] = $workOrderFilesCount;

        $output['vendor_techs']['items'] = $vendorsTechs;
        // data to edit
        $output['vendor_techs']['trade_type_id']
            = $this->type->getList('company_trade');
        $output['vendor_techs']['regions'] = $this->getRegions();

        // data to link current work order to any other Module
        $output['item']['link_to'] = [
            'table_name' => 'work_order',
            'record_id'  => $id,
        ];

        // information whether vendors can be assigned to this work order
        $output['item']['can_assign_vendors'] =
            ($record->getInvoiceStatusTypeId() !=
                getTypeIdByKey('wo_billing_status.invoiced'));

        return $output;
    }

    /**
     * Get conditional modules info - if module should be showed and permissions
     * that should be verified to display active module
     *
     * @return array
     */
    protected function getModulesInfo()
    {
        $verify = ['labtech', 'kb', 'monitor'];
        $verifyFor = 'workorder_edit';

        $modConfig = $this->app->config->get('modconfig');

        $out = [];
        foreach ($verify as $mod) {
            $out[$mod]['show'] = $modConfig[$mod]['enabled'];
            if (!$out[$mod]['show']) {
                continue;
            }
            $out[$mod]['show'] = $modConfig[$mod][$verifyFor]['show'];
            if (!$out[$mod]['show']) {
                continue;
            }
            if (!isset($modConfig[$mod][$verifyFor]['permissions'])) {
                continue;
            }
            $permissions = Auth::user()
                ->verifyPermissions(array_flip($modConfig[$mod][$verifyFor]['permissions']));

            foreach ($modConfig[$mod][$verifyFor]['permissions'] as $n => $p) {
                $out[$mod]['permissions'][$n] = $permissions[$p];
            }
        }

        return $out;
    }

    /**
     * Decide if for work order ECD warning should be displayed
     *
     * @param  WorkOrder  $workOrder
     *
     * @return bool
     */
    protected function getEcdWarningStatus(WorkOrder $workOrder)
    {
        if ($workOrder->day_before_ecd !== null) {
            $today_date = date('m/d/Y');
            $date_array['ecd'] = explode('/', $workOrder->day_before_ecd);
            $date_array['today'] = explode('/', $today_date);
            $date_mktime['ecd'] = mktime(
                0,
                0,
                0,
                $date_array['ecd'][0],
                $date_array['ecd'][1],
                $date_array['ecd'][2]
            );
            $date_mktime['today'] = mktime(
                0,
                0,
                0,
                $date_array['today'][0],
                $date_array['today'][1],
                $date_array['today'][2]
            );
            if (($date_mktime['ecd'] < $date_mktime['today'])
                && $workOrder->getWoStatusTypeId() !=
                $this->type->getIdByKey('wo_status.completed')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get list of opened work orders in the same address as given work order
     *
     * @param  int  $locationId
     * @param  int  $workOrderId
     *
     * @return array
     */
    public function getOpenedInLocation($locationId, $workOrderId)
    {
        $table = $this->model->getTable();

        $columns1 = [
            $table.'.work_order_id',
            $table.'.work_order_number',
            'person_name(lpwo.person_id)  AS vendor',
        ];

        $query1 = $this->model->selectRaw(implode(', ', $columns1))
            ->leftJoin(
                'link_person_wo AS lpwo',
                $table.'.work_order_id',
                '=',
                'lpwo.work_order_id'
            )->where(
                $table.'.shop_address_id',
                '=',
                $locationId
            )->whereNotIn($table.'.wo_status_type_id', [
                $this->type->getIdByKey('wo_status.completed'),
                $this->type->getIdByKey('wo_status.canceled'),
            ])->where('lpwo.is_disabled', 0)->where(
                $table.'.work_order_id',
                '!=',
                $workOrderId
            )
            ->groupBy("{$table}.work_order_id", 'lpwo.person_id');

        $columns2 = [
            $table.'.work_order_id',
            $table.'.work_order_number',
            'type_value  AS vendor',
        ];

        $query2 = $this->model->selectRaw(implode(', ', $columns2))
            ->leftJoin(
                'type',
                $table.'.wo_status_type_id',
                '=',
                'type.type_id'
            )
            ->where($table.'.shop_address_id', '=', $locationId)
            ->whereIn($table.'.wo_status_type_id', [
                $this->type->getIdByKey('wo_status.new'),
                $this->type->getIdByKey('wo_status.picked_up'),
            ])->where($table.'.work_order_id', '!=', $workOrderId)
            ->groupBy($table.'.work_order_id');

        $data
            =
            DB::select(
                DB::raw("({$query1->toSql()}) UNION ({$query2->toSql()})"),
                array_merge($query1->getBindings(), $query2->getBindings())
            );

        $out = [];

        foreach ($data as $wo) {
            $id = $wo->work_order_id;
            if (!isset($out[$id])) {
                $out[$id]['work_order_id'] = $wo->work_order_id;
                $out[$id]['work_order_number'] = $wo->work_order_number;
                $out[$id]['vendors'] = [];
            }
            $out[$id]['vendors'][] = $wo->vendor;
        }

        return $out;
    }

    /**
     * Get regions list
     *
     * @return array
     */
    protected function getRegions()
    {
        $regionRepo = $this->getRepository('Region');

        return $regionRepo->getList();
    }

    /**
     * Get list of opened work orders in the same city as given work order
     *
     * @param  int  $locationId
     * @param  int  $workOrderId
     *
     * @return array
     */
    public function getOpenedInCity($locationId, $workOrderId)
    {
        $table = $this->model->getTable();

        $columns = [
            "{$table}.work_order_id",
            "{$table}.work_order_number",
            'type_value as status',
            "{$table}.trade as trade",
            '(Select type_Value from type where type_id=trade_type_id) as trade2',
            'person_name(lpwo.person_id) AS vendor',
        ];

        $workorders = $this->model->selectRaw(implode(', ', $columns))
            ->leftJoin(
                'link_person_wo AS lpwo',
                "{$table}.work_order_id",
                '=',
                'lpwo.work_order_id'
            )
            ->leftJoin('type', 'wo_status_type_id', '=', 'type.type_id')
            ->leftJoin('address', 'address_id', '=', "{$table}.shop_address_id")
            ->where("{$table}.shop_address_id", '!=', $locationId)
            ->whereNotIn('address.city', ['Chicago', 'Detroit']) // @todo
            ->where('address.city', function ($q) use ($locationId) {
                $q->select('city')->from('address')
                    ->where('address_id', $locationId)->limit(1);
            })->where('address.state', function ($q) use ($locationId) {
                $q->select('state')->from('address')
                    ->where('address_id', $locationId)->limit(1);
            })
            ->whereNotIn("{$table}.wo_status_type_id", [
                $this->type->getIdByKey('wo_status.completed'),
                $this->type->getIdByKey('wo_status.canceled'),
            ])->where("{$table}.work_order_id", '!=', $workOrderId)
            ->groupBy("{$table}.work_order_id")->get();

        $out = [];

        foreach ($workorders as $wo) {
            $id = $wo->work_order_id;
            if (!isset($out[$id])) {
                $out[$id]['work_order_id'] = $wo->work_order_id;
                $out[$id]['work_order_number'] = $wo->work_order_number;
                $out[$id]['status'] = $wo->status;
                $out[$id]['trade'] = $wo->trade;
                $out[$id]['trade2'] = $wo->trade2;
                $out[$id]['vendors'] = [];
            }
            $out[$id]['vendors'][] = $wo->vendor;
        }

        return $out;
    }

    /**
     * Get 10 last vendors for the same location as give work order
     *
     * @param  WorkOrder  $wo
     *
     * @return mixed
     */
    protected function getLastLocationVendors(WorkOrder $wo)
    {
        $locationId = $wo->getShopAddressId();
        $workOrderId = $wo->getId();

        $lpRepo = $this->getRepository('LinkPersonWo', 'WorkOrder');

        return $lpRepo->getLastLocationVendors($locationId, $workOrderId, 10);
    }

    /**
     * Get files work Work order with given id and for its vendors
     *
     * @param  int  $id
     * @param  array  $vendorsTechs
     *
     * @return array
     * @deprecated moved to app/Modules/WorkOrder/Services/WorkOrderVendorsService.php
     *
     */
    protected function getFiles($id, array $vendorsTechs)
    {
        $fileRepo = $this->getRepository('File');
        $preFiles = $fileRepo->getForWo($id);

        $workOrderFilesCount = 0;
        $files = [];

        foreach ($preFiles as $f) {
            $f->list_only = (strpos($f->filename, '_signature_')
                !== false) ? 1 : 0;
            $f->type = substr($f->filename, -3);
            if (
                isset($f->type)
                && (in_array(mb_strtolower($f->type), ['jpg', 'gif', 'png']))
            ) {
                $f->is_image = true;
            } else {
                $f->is_image = false;
            }

            if ($f->table_name == 'link_person_wo') {
                $data = [];
                if (isset($vendorsTechs[$f->table_id]['files'])) {
                    $data = $vendorsTechs[$f->table_id]['files'];
                }
                $data[$f->file_id] = $f;
                $vendorsTechs[$f->table_id]['files'] = $data;
            // @todo - separate queries - one for vendors one for workorder
                // other work order files
            } else {
                $files[$f->file_id] = $f;
                ++$workOrderFilesCount;
            }
        }

        return [$vendorsTechs, $files, $workOrderFilesCount];
    }

    /**
     * Get vendors purchase orders
     *
     * @param  array  $vendorsTechs
     * @param  array  $vendorsTechsIds
     *
     * @return array
     * @deprecated moved to app/Modules/WorkOrder/Services/WorkOrderVendorsService.php
     *
     */
    protected function getVendorsPurchaseOrders(
        array $vendorsTechs,
        array $vendorsTechsIds
    ) {
        $orderIds = [];
        $allOrdersTotal = 0;
        $poRepo = $this->getRepository('PurchaseOrder');

        $orders = $poRepo->getForLinkedPersonWo($vendorsTechsIds);
        /** @var \App\Modules\PurchaseOrder\Models\PurchaseOrder $order */
        foreach ($orders as $order) {
            $vendorId = $order->getLinkPersonWoId();
            $purchaseId = $order->getId();

            $po = isset($vendorsTechs[$vendorId]->purchase_orders) ?
                $vendorsTechs[$vendorId]->purchase_orders : null;
            if ($po === null || !isset($po[$purchaseId])) {
                $po[$purchaseId] = (object) [
                    'purchase_order_id'   => $purchaseId,
                    'number'              => $order->getPurchaseOrderNumber(),
                    'purchase_order_date' => $order->purchase_order_date,
                ];
                $orderIds[] = $purchaseId;
            }
            if ($order->purchase_order_entry_id) {
                $po[$purchaseId]->entries[] = (object) [
                    'qty'                     => $order->quantity,
                    'total'                   => $order->total,
                    'item'                    => $order->item,
                    'purchase_order_entry_id' => $order->purchase_order_entry_id,
                ];
            }

            $vendorsTechs[$vendorId]->purchase_orders = $po;
        }

        if ($orderIds) {
            $totals = $poRepo->getTotals($orderIds);
            /** @var \App\Modules\PurchaseOrder\Models\PurchaseOrder $total */
            foreach ($totals as $total) {
                $id = $total->getId();
                $vendorId = $total->lpwo_id;

                $po = isset($vendorsTechs[$vendorId]->purchase_orders) ?
                    $vendorsTechs[$vendorId]->purchase_orders : null;
                $po[$id]->total = $total->total;
                $vendorsTechs[$vendorId]->purchase_orders = $po;

                if (isset($vendorsTechs[$vendorId]->purchase_orders_total)) {
                    $vendorsTechs[$vendorId]->purchase_orders_total += $total->total;
                } else {
                    $vendorsTechs[$vendorId]->purchase_orders_total
                        = $total->total;
                }
                $allOrdersTotal += $total->total;
            }
        }

        return [$vendorsTechs, $allOrdersTotal];
    }

    /**
     * Get Bills summary
     *
     * @param  array  $vendorsTechs
     * @param  array  $billingStatuses
     *
     * @return array
     * @deprecated moved to app/Modules/WorkOrder/Services/WorkOrderVendorsService.php
     *
     */
    protected function getBillsSummary(
        array $vendorsTechs,
        array $billingStatuses
    ) {
        $allBillsTotal = 0;

        if ($vendorsTechs) {
            foreach ($vendorsTechs as $id => $vendor) {
                $btotal = 0.0;

                if (isset($vendor->bills)) {
                    foreach ($vendor->bills as $billId => $bill) {
                        if (
                            !isset($bill['rejections'])
                            || empty($bill['rejections'])
                        ) {
                            $btotal += floatval($bill['amount']);
                        }

                        $curBills = $vendorsTechs[$id]->bills;
                        $curBills[$billId]['qb_sync_status']
                            = isset($billingStatuses[$billId]['sync_status'])
                            ? $billingStatuses[$billId]['sync_status'] : null;

                        $vendorsTechs[$id]->bills = $curBills;
                    }
                }
                $vendorsTechs[$id]->bills_total = $btotal;
                $allBillsTotal += $btotal;
            }
        }

        return [$vendorsTechs, $allBillsTotal];
    }

    /**
     * Get vendors bill rejections
     *
     * @param  array  $vendorTechs
     * @param  array  $vendorTechsIds
     *
     * @return mixed
     * @deprecated moved to app/Modules/WorkOrder/Services/WorkOrderVendorsService.php
     *
     */
    protected function getVendorBillsRejections(
        array $vendorTechs,
        array $vendorTechsIds
    ) {
        $billRej = $this->getRepository('BillRejection', 'Bill');
        $rejections = $billRej->getForLinkedPersonWo($vendorTechsIds);

        /** @var \App\Modules\Bill\Models\BillRejection $rejection */
        foreach ($rejections as $rejection) {
            $vendorId = $rejection['link_person_wo_id'];
            $curBills = $vendorTechs[$vendorId]->bills;
            $curBills[$rejection->getBillId()]['rejections'][] = $rejection;
            $vendorTechs[$vendorId]->bills = $curBills;
        }

        return $vendorTechs;
    }

    /**
     * Get vendors bills
     *
     * @param  array  $vendorTechs
     * @param  array  $vendorTechsIds
     *
     * @return array
     * @deprecated moved to app/Modules/WorkOrder/Services/WorkOrderVendorsService.php
     *
     */
    protected function getVendorsBills(
        array $vendorTechs,
        array $vendorTechsIds
    ) {
        $billIds = [];
        $bookingStatuses = [];

        $receipts = $this->type->getList('bill_entry');

        $bill = $this->getRepository('Bill');
        $bills = $bill->getForLinkedPersonWo($vendorTechsIds);

        /** @var \App\Modules\Bill\Models\Bill $bill */
        foreach ($bills as $bill) {
            $id = $bill->getLinkPersonWoId();
            $billId = $bill->getId();
            if (!isset($vendorTechs[$id]->bills)) {
                $vendorTechs[$id]->bills = [];
            }
            $curBills = $vendorTechs[$id]->bills;

            if (!isset($curBills[$billId])) {
                $supplierName = null;
                if (!empty($bill->getCompanyPersonId())) {
                    $supplierName = $bill->person_name;
                }
                $bill->supplier_name = $supplierName;

                $curBills = array_replace(
                    $curBills,
                    [
                        $billId => [
                            'bill_id'            => $billId,
                            'file_id'            => $bill->file_id,
                            'final'              => $bill->final,
                            'number'             => $bill->getNumber(),
                            'amount'             => $bill->getAmount(),
                            'payment_terms_name' => $bill->payment_terms_name,
                            'bill_date'          => $bill->getBillDate(),
                            'created_date'       => $bill->getCreatedAt(),
                            'supplier_name'      => $supplierName,
                        ],
                    ]
                );

                if (trim($billId) != '') {
                    $billIds[] = trim($billId);
                }
            }
            if ($bill->bill_entry_id) {
                $curBills[$billId]['entries'][] = [
                    'qty'              => $bill->qty,
                    'total'            => $bill->total,
                    'service1'         => $bill->service1,
                    'service2'         => $bill->service2,
                    'item'             => $bill->item,
                    'type_name'        => $bill->type_name,
                    'item_code'        => $bill->item_code,
                    'description'      => $bill->description,
                    'invoice_entry_id' => $bill->invoice_entry_id,
                    'receipt'          => ((isset($bill->receipt)
                        && isset($receipts[$bill->receipt]))
                        ? $receipts[$bill->receipt] : null),
                    'price'            => $bill->price,
                    'approval_status'  => (isset($bill->approval_person_id)
                    && $bill->approval_person_id > 0
                        ? 'bill.approval_status_approved'
                        : 'bill.approval_status_not_approved'),
                ];
            }

            $vendorTechs[$id]->bills = $curBills;
        }

        if ($billIds) {
            $bookingConfig = $this->app->config->get('services.booking');

            if ($bookingConfig['enabled'] === true) {
                $bookingService = $this->app->make($bookingConfig['class']);
                $bookingService->setConnection($bookingConfig['connection']);
                $bookingStatuses
                    = $bookingService->getBillsSyncStatus($billIds);
            }
        }

        return [$vendorTechs, $bookingStatuses];
    }

    /**
     * Get basic list of vendors
     *
     * @param  WorkOrder  $wo
     * @param  array  $vendorSummary
     *
     * @return array
     * @deprecated moved to app/Modules/WorkOrder/Services/WorkOrderVendorsService.php
     *
     */
    protected function getVendorsTechs(WorkOrder $wo, array $vendorSummary)
    {
        $type = $this->getRepository('Type');

        $lpwo = $this->getRepository('LinkPersonWo', 'WorkOrder');

        $data = $lpwo->getVendorsTechs($wo->getId());

        $avendors = [];
        $ids = [];

        $readyToInvoice = false;
        $notCompletedVendors = 0;
        if (count($data)) {
            $readyToInvoice = true;
        }
        foreach ($data as $item) {
            /** @var \App\Modules\WorkOrder\Models\LinkPersonWo $item */

            $item->type = ucfirst($item->getType());

            if (
                !$item->getIsDisabled()
                && ($item->getType() == 'Work' || $item->getType() == 'Recall')
                && ($item->getStatusTypeId()
                    != $type->getIdByKey('wo_vendor_status.completed')
                    && $item->getStatusTypeId()
                    != $type->getIdByKey('wo_vendor_status.canceled'))
            ) {
                ++$notCompletedVendors;
            }
            $item->is_tech = ($item->vendor_type
                == $type->getIdByKey('person.technician'));
            $item->is_supplier = ($item->vendor_type
                == $type->getIdByKey('company.supplier'));

            $numbers = $item->getId() * 37;
            $temp = (string) $numbers;
            $num = 0;
            for ($i = 0; $i < strlen($numbers); $i++) {
                $num += $temp[$i];
            }

            $item->pin = $numbers.($num % 10);

            $item->total_time_cost = $this->calculateVendorTotalTimeCost(
                $item->getId(),
                $vendorSummary
            );

            $item->bill_final_checked = 0;
            if ($item->bill_final != 2) {
                if ($item->bill_final == 1) {
                    $item->bill_final_checked = 1;
                } else {
                    $readyToInvoice = false;
                }
            }
            if ($item->vendor_person_status == $this->type->getIdByKey('company_status.disabled')
            ) {
                $item->mark_as_disabled = true;
            } else {
                $item->mark_as_disabled = false;
            }

            $ids[] = $item->getId();
            $avendors[$item->getId()] = $item;
        }

        return [$avendors, $ids, $readyToInvoice, $notCompletedVendors];
    }

    /**
     * Calculate time cost for vendor with given id
     *
     * @param  int  $id
     * @param  array  $vendorSummary
     *
     * @return float
     */
    protected function calculateVendorTotalTimeCost($id, array $vendorSummary)
    {
        $workSec = isset($vendorSummary['work'][$id]['duration_sec']) ?
            $vendorSummary['work'][$id]['duration_sec'] : 0;

        $travelSec = isset($vendorSummary['travel'][$id]['duration_sec']) ?
            $vendorSummary['travel'][$id]['duration_sec'] : 0;

        return $this->calculateRealCost($workSec, $travelSec);
    }

    /**
     * Get hour cost
     *
     * @return float
     */
    protected function getHourCost()
    {
        return floatval($this->app->config->get('system_settings.hour_cost'));
    }

    /**
     * Calculate cost for work and travel
     *
     * @param $work
     * @param $travel
     *
     * @return float
     */
    protected function calculateRealCost($work, $travel)
    {
        $hourCost = $this->getHourCost();

        return $hourCost * ((intval($work) + intval($travel)) / 3600);
    }

    /**
     * Calculate total time cost for time sheet summary
     *
     * @param  array  $timeSheetSummary
     *
     * @return float
     */
    protected function calculateTotalTimeCost(array $timeSheetSummary)
    {
        $workSec = isset($timeSheetSummary['total']['work']['duration_sec']) ?
            $timeSheetSummary['total']['work']['duration_sec'] : 0;

        $travelSec = isset($timeSheetSummary['total']['travel']['duration_sec'])
            ?
            $timeSheetSummary['total']['travel']['duration_sec'] : 0;

        return $this->calculateRealCost($workSec, $travelSec);
    }

    /**
     * Handle lock status - updates locked_id/record modify date and sets
     * view_only status to 1 if record is locked by someone else
     *
     * @param  WorkOrder  $record
     * @param  array  $output
     *
     * @return array
     */
    protected function handleLockStatus($record, array $output)
    {
        /** @var PersonRepository $personRepo */
        $personRepo = $this->makeRepository('Person', 'Person');

        $woLockTime = $this->app->config->get('system_settings.workorder_lock_limit_minutes', 15) * 60;

        // @todo shouldn't it be here strtotime ?
        if ($record->last_edit_delay > $woLockTime && $record->getLockedId() != getCurrentPersonId()) {
            $record->locked_id = getCurrentPersonId();
            $record->save();
            
            $output['item']['locked_id'] = $record->locked_id;
            $output['item']['locked_id_value'] = getPersonName($record->locked_id);
        }

        if (empty($record->locked_id) || getCurrentPersonId() == $record->locked_id) {
            $record->locked_id = getCurrentPersonId();
            $record->save();
            
            $output['item']['locked_id'] = $record->locked_id;
            $output['item']['locked_id_value'] = getPersonName($record->locked_id);
        }

        if ($record->locked_id != getCurrentPersonId()) {
            $output['item']['view_only'] = 1;
        } else {
            $output['item']['view_only'] = 0;
        }
        
        $output['item']['locked_id_value'] = $personRepo->getPersonName($record->locked_id);


        return [$record, $output];
    }

    /**
     * Add values of shop_address_id and customer_setting_id columns for
     * Work Order record
     *
     * @param  WorkOrder  $wo
     * @param  array  $output
     *
     * @return array
     */
    public function addOtherColumnsValues(WorkOrder $wo, array $output)
    {
        if (isset($output['fields']['shop_address_id']['data'][$wo->getShopAddressId()])) {
            $output['item']['shop_address_id_value']
                =
                $output['fields']['shop_address_id']['data'][$wo->getShopAddressId()];
        } else {
            $address = $this->makeRepository('Address');
            $output['item']['shop_address_id_value']
                = $address->getForPersonWo(
                    $wo->getCompanyPersonId(),
                    $wo->getShopAddressId()
                );
        }

        $output['item']['billing_company_address'] = null;
        if (!empty($output['item']['billing_company_person_id'])) {
            $address = $this->makeRepository('Address');
            $output['item']['billing_company_address'] = $address->getForPersonWo(
                $output['item']['billing_company_person_id'],
                0,
                true
            );
        }

        // customer_setting_id - value is the same as id
        //        $output['item']['customer_setting_id_value']
        //            = ($output['item']['customer_setting_id']) ?: '';

        return $output;
    }

    /**
     * Add type columns values for Work Order record
     *
     * @param  WorkOrder  $wo
     * @param  array  $output
     * @param  bool  $forEdit
     *
     * @return array
     */
    protected function addTypeColumnsValues(
        WorkOrder $wo,
        array $output,
        $forEdit
    ) {
        if ($forEdit) {
            $typeColumns = [
                'wo_status_type_id',
                'bill_status_type_id',
            ];
        } else {
            $typeColumns = [
                'wo_status_type_id',
                'quote_status_type_id',
                'via_type_id',
                'invoice_status_type_id',
                'bill_status_type_id',
                'parts_status_type_id',
                'crm_priority_type_id',
                'cancel_reason_type_id',
                'trade_type_id',
                'tech_trade_type_id',
                'wo_type'
            ];
        }

        $missingColumns = [];
        $zeroValues = 0;

        foreach ($typeColumns as $column) {
            /* Not used = lower number of Type cached results
            if (!$record->$column) {
                continue;
            }
            */

            if (isset($output['fields'][$column]['data'][$wo->$column])) {
                $output['item'][$column.'_value']
                    = $output['fields'][$column]['data'][$wo->$column];
            } else {
                $missingColumns[] = $column;
                if (!$wo->$column) {
                    $zeroValues += 1;
                }
            }
        }

        if ($missingColumns) {
            $ds = $this->app->make(WorkOrderDataServiceContract::class);
            $types = $ds->getTypes($missingColumns);

            foreach ($missingColumns as $column) {
                if ($wo->$column && isset($types[$column][$wo->$column])) {
                    $output['item'][$column.'_value']
                        = $types[$column][$wo->$column];
                } else {
                    $output['item'][$column.'_value'] = '';
                }
            }
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function find(
        $id,
        array $columns = [
            'work_order.*',
            '(UTC_TIMESTAMP()-modified_date) as last_edit_delay',
        ]
    ) {
        $this->setWorkingModel($this->model);
        $this->setModelDetails($columns);

        $data = parent::find($id);
        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Add joins and select columns to working model depending on
     * 'detailed' parameter in url
     *
     * @param  array  $columns
     */
    protected function setModelDetails(array $columns)
    {
        $model = $this->getModel();

        $detailed = $this->request->query('detailed', '');

        $columns[] = 'person_name(pickup_id) AS pickup_id_value';
        $columns[] = 'person_name(company_person_id) AS company_person_id_value';
        $columns[] = 'person_name(billing_company_person_id) AS billing_company_person_id_value';
        
        if ($detailed != '') {
            $columns[] = 'person_name(acknowledged_person_id) AS acknowledged_person_id_value';
            $columns[] = 'person_name(dispatched_to_person_id) AS dispatched_to_person_id_value';
            $columns[] = 'person_name(creator_person_id) AS creator_person_id_value';
            $columns[] = 'person_name(project_manager_person_id) AS project_manager_person_id_value';
            $columns[] = 'person_name(requested_by_person_id) AS requested_by_person_id_value';
            $columns[] = 'person_name(supplier_person_id) AS supplier_person_id_value';
            $columns[] = 'person_name(locked_id) AS locked_id_value';
            $columns[] = 'person_name(owner_person_id) AS owner_person_id_value';
            $columns[] = 'person_name(sales_person_id) AS sales_person_id_value';
        }

        if ($columns) {
            $model = $model->selectRaw(implode(', ', $columns));
        }

        $this->setWorkingModel($model);
    }

    /**
     * Get front-end validation rules
     *
     * @param  string  $type
     *
     * @return array
     */
    public function getRequestRules($type = '')
    {
        if ($type == 'update') {
            $req = new WorkOrderUpdateRequest();
        } elseif ($type == 'basicupdate') {
            $req = new WorkOrderBasicUpdateRequest();
        } else {
            $req = new WorkOrderStoreRequest();
        }

        return $req->getFrontendRules();
    }

    /**
     * Get person data if $personId is valid. Returns billing_company_person_id,
     * project_manager_person_id, addresses and customer_settings (depending
     * on $partial value)
     *
     * @param  int  $personId
     * @param  bool  $partial
     *
     * @return array
     */
    public function getPersonData($personId, $partial = false)
    {
        $personId = (int) $personId;
        if (!$personId) {
            return [];
        }

        if (!$partial) {
            $person = $this->makeRepository('Person', 'Person');
            $data = $person->getPersonData($personId, [
                'custom_8 AS billing_company_person_id',
                'assigned_to_person_id AS project_manager_person_id',
            ]);
        } else {
            $data = new \stdClass();
        }

        if ($data || $partial) {
            if (!$partial) {
                $data->billing_company_id = (int) $data->billing_company_id;
            }

            $address = $this->makeRepository('Address', 'Address');

            $addresses = $address->getForPersonWo($personId);
            foreach ($addresses as $adr) {
                $data->addresses[$adr->getId()] = $adr;
            }

            $cs = $this->makeRepository('CustomerSettings');
            $data->customer_settings = $cs->getIds($personId);
        }

        return $data;
    }

    /**
     * Unlock work order (only locked person can unlock it unless $force is
     * set to true)
     *
     * @param  int  $id
     *
     * @param  bool  $force
     *
     * @return mixed
     */
    public function unlock($id, $force = false)
    {
        $record = parent::find($id);

        if (($record->getLockedId() == getCurrentPersonId()) || $force) {
            $record->locked_id = 0;

            return $record->save();
        }

        return false;
    }

    /**
     * Pick up work order (only locked person can pick up work order) and mark
     * first work order email as read
     *
     * @param  int  $id
     *
     * @return bool
     */
    public function pickup($id)
    {
        $record = parent::find($id);

        if (
            $record->getLockedId() == getCurrentPersonId()
            && !$record->getPickupId()
        ) {
            $record->pickup_id = getCurrentPersonId();
            $record->wo_status_type_id
                = $this->type->getIdByKey('wo_status.picked_up');
            $email = $this->makeRepository('Email');

            $status = false;

            DB::transaction(function () use ($record, $email, &$status) {
                $status = $record->save();
                $email->markWoEmailRead($record->getId());
            });

            return $status;
        }

        return false;
    }

    /**
     * Update expected completion date to given $date
     *
     * @param  string  $date
     *
     * @return bool
     */
    public function updateExpectedCompletionDate($id, $date)
    {
        $record = parent::find($id);

        $record->expected_completion_date = $date;

        return $record->save();
    }

    /**
     * Verifies whether work order extension may be added for work order with
     * given $id
     *
     * @param  int|WorkOrder  $id
     *
     * @return bool
     */
    public function isValidForAddingExtension($id)
    {
        $notAllowedTypes = [
            $this->type->getIdByKey('wo_status.new'),
            $this->type->getIdByKey('wo_status.completed'),
            $this->type->getIdByKey('wo_status.canceled'),
        ];

        if ($id instanceof WorkOrder) {
            return (bool) !in_array($id->getWoStatusTypeId(), $notAllowedTypes);
        }

        $model = $this->getModel();
        $model = $model->whereNotIn('wo_status_type_id', $notAllowedTypes);

        $this->setWorkingModel($model);

        $record = parent::findSoft($id);
        $this->clearWorkingModel();
        if ($record) {
            return true;
        }

        return false;
    }

    /**
     * Get activities for work order
     *
     * @param  int  $id
     *
     * @param  bool  $full  Whether to get also data for edit
     * @param  array  $params
     *
     * @return mixed
     */
    public function getActivities($id, $full = false, array $params)
    {
        /** @var ActivityRepository $aRepo */
        $aRepo = $this->app->make(ActivityRepository::class);

        $workOrder = null;

        $addQuoteStatusTypeId = empty($params['get_quote_status_type_id'])
            ? false : ($params['get_quote_status_type_id'] == 1);

        if ($addQuoteStatusTypeId) {
            /** @var WorkOrder $workOrder */
            $workOrder = $this->basicFind($id);
        }

        // verify if we want data in reverse order
        $reverse = false;
        if (isset($params['reverse']) && $params['reverse'] == 1) {
            $reverse = true;
        }

        $items = $aRepo->getForWo($id, $params, $reverse);
        $total = count($items);

        if (isset($params['limit']) && $params['limit'] > 0) {
            $items = array_slice($items, 0, $params['limit']);
        }

        $data = new LengthAwarePaginator(
            $items,
            $total,
            $items ? $total : 1,
            1,
            [
                'path'  => $this->app->request->url(),
                'query' => $this->app->request->query(),
            ]
        );

        $data = $data->toArray();

        if ($full === false) {
            $detailed = $this->request->query('detailed', '');
            if ($detailed == 1) {
                $full = true;
            }
        }

        if ($full) {
            $e = $this->getEmployees();
            $data['employees'] = [];
            foreach ($e as $employee) {
                $data['employees'][$employee->getId()]
                    = $employee->getCustom3().' '.$employee->getCustom1();
            }
            $actReq = new ActivityRequest();
            $data['activity']['fields'] = $actReq->getFrontendRules();

            $taskReq = new CalendarEventTaskRequest();
            $data['task']['fields'] = $taskReq->getFrontendRules();
        }

        if ($addQuoteStatusTypeId) {
            $data['quote_status_type_id'] = ($workOrder) ?
                (int) $workOrder->getQuoteStatusTypeId() : null;
        }

        //convert items to array
        $data['data'] = json_decode(json_encode($data['data']), true);
        $data['person_status'] = $this->getRepository('TimeSheet')->getPersonStatuses($data['data']);

        $this->addRowNumbers($data, $reverse, $total);
        $this->addPersonStatuses($data);

        return $data;
    }

    /**
     * Get employees
     *
     * @return mixed
     */
    protected function getEmployees()
    {
        $person = $this->getRepository('Person');

        return $person->getEmployees();
    }

    /**
     * Get basic work order for display - this method will be used just to get
     * basic Work Order entry data and use them in other system modules
     *
     * @param  int  $workOrderId
     * @param  array  $extraColumns
     *
     * @return WorkOrder
     */
    public function getBasicForDisplay($workOrderId, array $extraColumns = [])
    {
        $columns = [
            '*',
            'person_name(company_person_id) AS company_person_id_value',
        ];
        if ($columns) {
            $columns = array_merge($columns, $extraColumns);
        }

        return $this->model->selectRaw(implode(', ', $columns))
            ->leftJoin(
                'address',
                'work_order.shop_address_id',
                '=',
                'address.address_id'
            )
            ->where('work_order_id', $workOrderId)->first();
    }

    /**
     * Checks if Work Order is suitable for invoice
     *
     * @param  WorkOrder  $workOrder
     * @param  bool  $vendorsOnly
     *
     * @return bool
     */
    public function isSuitableForInvoice(
        WorkOrder $workOrder,
        $vendorsOnly = false
    ) {
        if (
            strtotime($workOrder->getCreatedAt())
            > strtotime('2015-03-10 23:59:59')
        ) {
            $billRepo = $this->getRepository('Bill');
            $count = $billRepo->getWoVendorsBillCount($workOrder->getId());
            if ($count) {
                return false;
            }
            $count = $billRepo->getWoNotFinalBills(
                $workOrder->getId(),
                $vendorsOnly
            );
            if ($count) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find Work Order by number
     *
     * @param  string  $workOrderNumber
     * @param  bool  $failIfNotFound
     *
     * @return WorkOrder
     *
     * @throws InvalidArgumentException
     */
    public function findByWoNumber($workOrderNumber, $failIfNotFound = false)
    {
        /** @var Builder|WorkOrder $model */
        $model = $this->model;
        $model = $model->where('work_order_number', $workOrderNumber);

        $model = $failIfNotFound ? $model->firstOrFail() : $model->first();

        return $model;
    }

    /**
     * Find Work Order by link person wo id
     *
     * @param  int  $lpWoId
     *
     * @param  string|array  $columns
     *
     * @return WorkOrder
     */
    public function findByLpWoId($lpWoId, $columns = '*')
    {
        return $this->model->where(
            'work_order_id',
            function ($q) use ($lpWoId) {
                $q->select('work_order_id')->from('link_person_wo')
                    ->where('link_person_wo_id', $lpWoId)->take(1);
            }
        )->select($columns)->first();
    }

    /**
     * Update work order status, log status change (if successful)
     * and return result of update (true or false)
     *
     * @param  WorkOrder  $wo
     * @param  int  $status
     *
     * @return bool
     */
    public function updateStatus(WorkOrder $wo, $status)
    {
        $oldStatus = $wo->getWoStatusTypeId();
        $wo->wo_status_type_id = $status;
        $result = $wo->save();
        if ($result) {
            $this->app['logger']->log(
                'WO_ID:'.$wo->getId().
                ', OLD_STATUS:'.$oldStatus.', NEW_STATUS:'.$status,
                'wo_status_log'
            );
        }

        return $result;
    }

    public function paginateCompletionGrid($perPage = 50)
    {
        // @todo later

        //$gridService = new WorkOrderCompletionGridService($this, $this->app);

        //return $gridService->paginate($perPage, $columns, $order);
    }

    /**
     * Add invoice to Work order (save invoice number for Work order and change
     * invoice status for Work order)
     *
     * @param  WorkOrder  $workOrder
     * @param  Invoice  $invoice
     *
     * @return WorkOrder
     */
    public function addInvoice(WorkOrder $workOrder, Invoice $invoice)
    {
        $invoiceNumber
            = $this->config->get('modconfig.work_order.invoice_prefix')
            .$invoice->getId();

        if (!$workOrder->getInvoiceNumber()) {
            $workOrder->invoice_number = $invoiceNumber;
        } else {
            $workOrder->invoice_number .= ','.$invoiceNumber;
        }

        $type = $this->getRepository('Type');
        $workOrder->invoice_status_type_id
            = $type->getIdByKey('wo_billing_status.invoiced');
        $workOrder->save();

        return $workOrder;
    }

    /**
     * Find work order data for mobile devices based on id of work order or
     * link person WO
     *
     * @param  int  $id
     * @param  string  $type  Decides if $id is from work_order or from
     *                     link_person_wo
     * @param  int  $ongoingTimeSheetId
     *
     * @return WorkOrder
     */
    public function findMobile($id, $type, $ongoingTimeSheetId)
    {
        $ongoingTimeSheetId = (int) $ongoingTimeSheetId;
        $personId = (int) getCurrentPersonId();
        $id = (int) $id;

        $this->setAllowGhostLink();

        $columns = [
            'work_order.work_order_id',
            'work_order.work_order_number',
            'work_order.company_person_id',
            'person_name(work_order.company_person_id) as client',
            'work_order.fac_supv AS store_contact',
            'work_order.phone AS store_phone',
            'IFNULL(work_order.quote_status_type_id, 0) as quote_status_type_id',
            'expected_completion_date',
            'datediff(expected_completion_date, NOW()) AS days_to_ecd',
            'work_order.locked_id',
            'work_order.completion_code',
            'work_order.authorization_code',
            't2.orderby AS priority',
            'work_order.description AS wo_description',
            'received_date',
            'work_order.fin_loc as store_number',
            'lpwo.link_person_wo_id',
            'lpwo.confirmed_date',
            'lpwo.type',
            'lpwo.qb_info',
            't1.type_value AS vendor_status',
            'lpwo.person_id',
            "(SELECT file_id FROM file WHERE person_id = {$personId}
                AND table_name='time_sheet' AND table_id = {$ongoingTimeSheetId}
                AND filename like 'signature_%' LIMIT 1) AS has_signature",
            'work_order.shop_address_id',
            'adr.city',
            'adr.state',
            'adr.zip_code',
            'adr.address_1 as address',
            'lpwo.special_type',
            'work_order.customer_setting_id',
        ];

        if ($this->allowGhostLink) {
            $columns[] = 'lpwo.is_ghost';
        }

        $query = $this->model->selectRaw(implode(', ', $columns))
            ->join(
                'link_person_wo AS lpwo',
                'work_order.work_order_id',
                '=',
                'lpwo.work_order_id',
                ($type == 'work_order') ? 'left' : 'right'
            )
            ->leftJoin(
                'address AS adr',
                'work_order.shop_address_id',
                '=',
                'adr.address_id'
            )
            ->leftJoin(
                'type AS t1',
                'lpwo.status_type_id',
                '=',
                't1.type_id'
            )
            ->leftJoin(
                'type AS t2',
                'work_order.crm_priority_type_id',
                '=',
                't2.type_id'
            );

        if ($type == 'work_order') {
            $query = $query->where('work_order.work_order_id', $id);
        } else {
            $query = $query->where('lpwo.link_person_wo_id', $id);
        }

        return $query->firstOrFail();
    }

    /**
     * Update work order supplier person id
     *
     * @param  WorkOrder  $wo
     * @param  int  $supplierId
     *
     * @return WorkOrder
     */
    public function updateSupplierPersonId(WorkOrder $wo, $supplierId)
    {
        $supplierId = (int) $supplierId;
        $wo->supplier_person_id = $supplierId;
        $wo->save();

        return $wo;
    }

    /**
     * Update work order completion code
     *
     * @param  WorkOrder  $wo
     * @param           $completionCode
     *
     * @return WorkOrder
     */
    public function updateCompletionCode(WorkOrder $wo, $completionCode)
    {
        $clearedCode = str_replace('0', '', $completionCode);
        if (!empty($clearedCode) && empty($wo->getCompletionCode())) {
            //todo: check format (TODO MOVED FROM OLD CRM)
            $wo->completion_code = $completionCode;
            $wo->save();
        }

        return $wo;
    }

    /**
     * Update work order actual completion date
     *
     * @param  WorkOrder  $wo
     * @param  Carbon|null  $date  If null current date will be used
     *
     * @return WorkOrder
     */
    public function updateActualCompletionDate(
        WorkOrder $wo,
        Carbon $date = null
    ) {
        if ($date === null) {
            $date = Carbon::now();
        }
        $wo->actual_completion_date = $date->format('Y-m-d H:i:s');

        $wo->save();

        return $wo;
    }

    /**
     * Cancel given work order
     *
     * @param  WorkOrder  $workOrder
     * @param  int  $invoiceStatusTypeId
     * @param  int  $billStatusTypeId
     * @param  int|null  $cancelReasonTypeId
     *
     * @return WorkOrder
     */
    public function cancel(
        WorkOrder $workOrder,
        $invoiceStatusTypeId,
        $billStatusTypeId,
        $cancelReasonTypeId = null
    ) {
        $workOrder->wo_status_type_id = getTypeIdByKey('wo_status.canceled');
        $workOrder->invoice_status_type_id = $invoiceStatusTypeId;
        $workOrder->bill_status_type_id = $billStatusTypeId;

        if ($cancelReasonTypeId !== null) {
            $workOrder->cancel_reason_type_id = $cancelReasonTypeId;
        }
        $workOrder->save();

        return $workOrder;
    }

    /**
     * Update work order invoice status
     *
     * @param  int  $woId
     * @param  int  $invoiceStatusTypeId
     *
     * @return WorkOrder
     */
    public function updateInvoiceStatus($woId, $invoiceStatusTypeId)
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->find($woId);
        $workOrder->invoice_status_type_id = $invoiceStatusTypeId;
        $workOrder->save();

        return $workOrder;
    }

    /**
     * Find work order together with address
     *
     * @param  int  $id
     * @param  array  $columns
     * @param  bool  $soft
     *
     * @return WorkOrder|null
     */
    public function findWithAddress($id, $columns = ['*'], $soft = false)
    {
        $query = $this->model->selectRaw(implode(', ', $columns))
            ->leftJoin(
                'address',
                'work_order.shop_address_id',
                '=',
                'address.address_id'
            );
        if ($soft) {
            return $query->find($id);
        }

        return $query->findOrFail($id);
    }

    /**
     * Get profitability for work order
     *
     * @param  int  $id
     *
     * @param  bool  $full  Whether to get also data for edit
     * @param  array  $params
     *
     * @return mixed
     */
    public function getProfitability($id, $full = false, array $params)
    {
        $data = [];
        $totalCost = $totalSale = 0;
        $data['table']['headers'] = ['SECTION', 'COST', 'SALE', 'GM %', 'GP', 'TARGET %'];
        // Labor
        $sql = <<<"SQL"
SELECT duration FROM time_sheet
LEFT JOIN link_person_wo ON time_sheet.table_id = link_person_wo.link_person_wo_id
WHERE table_name='link_person_wo' AND link_person_wo.work_order_id = {$id} ;
SQL;
        $labor = DB::select(DB::raw($sql));

        $hourCost = 57.00;
        $hourClientCost = 80.00;
        $hours = $minutes = $seconds = 0;
        foreach ($labor as $l) {
            $time = explode(':', $l->duration);
            $hours += $time[0];
            $minutes += $time[0];
            $seconds += $time[0];
        }
        $hourAmount = ($hours + (($minutes + $seconds / 60) / 60));
        $tsHoursCost = number_format($hourAmount * $hourCost, 2, '.', '');
        $tsHoursCostClient = number_format($hourAmount * $hourClientCost, 2, '.', '');
        if ($hourAmount > 0) {
            $totalCost = $totalCost + $tsHoursCost;
            $totalSale = $totalSale + $tsHoursCostClient;
            $percentAmount = number_format(
                ($tsHoursCostClient > 0 ? 100 - ($tsHoursCost / $tsHoursCostClient) * 100 : '0.0'),
                2,
                '.',
                ''
            );
            if ($percentAmount >= 40) {
                $target = '<span style="color:green">'.$percentAmount.'% > 40%'.'</span>';
            } else {
                $target = '<span style="color:red">'.$percentAmount.'% < 40%'.'</span>';
            }
            $data['table']['labor'] = [
                'Labor',
                number_format($tsHoursCost, 2, '.', ''),
                number_format($tsHoursCostClient, 2, '.', ''),
                $percentAmount.'%',
                number_format($tsHoursCostClient - $tsHoursCost, 2, '.', ''),
                $target,
            ];
        } else {
            $data['table']['labor'] = ['Labor', '0.0', '0.0', '0.0%', '0.0', '0.0%'];
        }

        // Materials
        // get all non service from bills where link_person_wo in select link_person_wo_id form link wo
        $sql = <<<"SQL"
SELECT SUM(bill_entry.total) as bill_total_amount FROM bill
LEFT JOIN bill_entry on bill.bill_id = bill_entry.bill_id
LEFT JOIN link_person_wo ON link_person_wo.`link_person_wo_id` = bill.link_person_wo_id
WHERE link_person_wo.work_order_id = {$id} and duplicate <> 1 and service_id = 0 and service_id2 = 0
SQL;

        $material = DB::select(DB::raw($sql));
        $billTotal = 0;
        foreach ($material as $m) {
            $billTotal = $billTotal + $m->bill_total_amount;
        }
        if ($billTotal > 0) {
            $data['table']['material'] = [
                'Material',
                number_format($billTotal, 2, '.', ''),
                '0.0',
                '0.0%',
                '0.0',
                '0.0',
            ];
            $totalCost = number_format($totalCost + $billTotal, 2, '.', '');
        } else {
            $data['table']['material'] = ['Material', '0.0', '0.0', '0.0%', '0.0', '0.0%'];
        }

        // Other
        $data['table']['other'] = ['Other', '0.0', '0.0', '0.0%', '0.0', '0.0%'];
        // Subcontractor
        // get bills from vendors where  service_id > 0

        $sql = <<<"SQL"
SELECT SUM(bill_entry.total) bill_total_amount FROM bill
LEFT JOIN bill_entry on bill.bill_id = bill_entry.bill_id
LEFT JOIN link_person_wo ON link_person_wo.`link_person_wo_id` = bill.link_person_wo_id
LEFT JOIN person on person.person_id = link_person_wo.person_id
WHERE person.kind = 'company' and link_person_wo.work_order_id = {$id} and duplicate <> 1 and service_id > 0
SQL;
        $subcontractor = DB::select(DB::raw($sql));
        $subcontractorTotal = 0;
        foreach ($subcontractor as $s) {
            $subcontractorTotal = $subcontractorTotal + $s->bill_total_amount;
        }
        if ($subcontractorTotal > 0) {
            $data['table']['subcontractor'] = [
                'Subcontractor',
                number_format($subcontractorTotal, 2, '.', ''),
                '0.0',
                '0.0%',
                '0.0',
                '0.0%',
            ];
            $totalCost = number_format($totalCost + $subcontractorTotal, 2, '.', '');
        } else {
            $data['table']['subcontractor'] = ['Subcontractor', '0.0', '0.0', '0.0%', '0.0', '0.0%'];
        }
        // Total
        $percentAmount = number_format(($totalSale > 0 ? 100 - ($totalCost / $totalSale) * 100 : '0.0'), 2, '.', '');
        if ($percentAmount >= 40) {
            $target = '<span style="color:green">'.$percentAmount.'% > 40%'.'</span>';
        } else {
            $target = '<span style="color:red">'.$percentAmount.'% < 40%'.'</span>';
        }
        $data['table']['total'] = [
            'Total',
            number_format($totalCost, 2, '.', ''),
            number_format($totalSale, 2, '.', ''),
            number_format($percentAmount, 2, '.', '').'%',
            number_format($totalSale - $totalCost, 2, '.', ''),
            $target,
        ];

        $sql = <<<"SQL"
SELECT SUM(invoice_entry.`total`+invoice_entry.`tax_amount`) AS invoice_amount
FROM invoice_entry
WHERE invoice_id IN (SELECT invoice_id FROM invoice WHERE work_order_id = {$id})
SQL;
        $invoiceAmount = DB::select(DB::raw($sql));
        $invoiceAmountTotal = 0;
        foreach ($invoiceAmount as $i) {
            $invoiceAmountTotal = $subcontractorTotal + $i->invoice_amount;
        }
        if ($invoiceAmountTotal <= 0) {
            $sql = <<<"SQL"
SELECT total_cost AS invoice_amount
FROM cost_center
WHERE work_order_id = {$id}
SQL;
            $invoiceAmount = DB::select(DB::raw($sql));
            foreach ($invoiceAmount as $i) {
                $invoiceAmountTotal = $subcontractorTotal + $i->invoice_amount;
            }
        }
        $data['invoice_amount'] = number_format($invoiceAmountTotal, 2, '.', '');

        return $data;
    }


    /**
     * Get and prepare work orders data for Fleetmatics API.
     * Data will be send via REST API to endpoint /workorders
     *
     * @param  WorkOrderFleetmaticsApiService  $workOrderFleetmaticsApiService  - API used to get status and type
     *
     * @return array
     */
    public function getFleetmaticsData(WorkOrderFleetmaticsApiService $workOrderFleetmaticsApiService)
    {
        //Columns to get
        $columns = [
            'link_person_wo.*',
            DB::raw("person_name(p.person_id) as personName"),
            'link_person_wo.scheduled_date as lp_scheduled_date',
            'wo.*',
            't.type_value as typeName'
        ];

        //Get work orders data
        $personWorkOrders = LinkPersonWo::join(
            'work_order as wo',
            'wo.work_order_id',
            '=',
            'link_person_wo.work_order_id'
        )
            ->leftJoin('type as t', 't.type_id', '=', 'wo.wo_type_id')
            ->join('person as p', 'p.person_id', '=', 'link_person_wo.person_id')
            //get only work orders with addresses - the address is required to save work orders
            ->join('address as ad', 'ad.address_id', '=', 'wo.shop_address_id')
            //Get records one day ahead - scheduled date can not be null
            //sent_to_fleetmatics_date can be null or less than last modified date
            ->whereRaw('DATE(link_person_wo.scheduled_date) BETWEEN CURDATE() AND (CURDATE() + INTERVAL 1 DAY) AND
            link_person_wo.sent_to_fleetmatics_date IS NULL OR link_person_wo.sent_to_fleetmatics_date < link_person_wo.modified_date AND
            link_person_wo.scheduled_date IS NOT NULL')
            ->take(50) //get max 50 records
            ->get($columns);

        //Prepare send data fro fleetmatics
        $sendData = [];
        foreach ($personWorkOrders as $personWorkOrder) {
            $workOrder = $personWorkOrder->workOrder;
            if ($workOrder) {
                if ($personWorkOrder->statusType) {
                    $status = $workOrderFleetmaticsApiService->getStatus($personWorkOrder->statusType->type_value); //get status from API
                } else {
                    $statuses = $workOrderFleetmaticsApiService->getStatuses();
                    $status = $statuses[0]['WorkOrderStatus'];
                }
                $typeName = $personWorkOrder->typeName ? $personWorkOrder->typeName : 'None';
                $workOrderType = $workOrderFleetmaticsApiService->getType($typeName); //get work order type code from API
                $driverNumber = $workOrderFleetmaticsApiService->getDriverByName($personWorkOrder->personName); //get driver number - may be null
                $scheduledDate = \DateTime::createFromFormat('Y-m-d H:i:s', $personWorkOrder->lp_scheduled_date);
                //Work order fleetmatics number: 'work_order_id'_'person_id'
                $sendData[$workOrder->work_order_id.'_'.$personWorkOrder->person_id] = [
                    'workOrderId'                => $workOrder->work_order_id,
                    //will be used to update sent_to_fleetmatics_date
                    'personId'                   => $personWorkOrder->person_id,
                    //will be used to update sent_to_fleetmatics_date
                    //isNew - is used to check if work order is new
                    'isNew'                      => $personWorkOrder->sent_to_fleetmatics_date == null,
                    'ActualDateUtc'              => null,
                    //can be null - will be filled by fleetmatics
                    'ActualDurationSeconds'      => null,
                    //can be null - will be filled by fleetmatics
                    'Address'                    => [
                        'AddressLine1'       => $workOrder->shopAddress->address_1,
                        'AddressLine2'       => $workOrder->shopAddress->address_2,
                        'Locality'           => $workOrder->shopAddress->city,
                        'AdministrativeArea' => $workOrder->shopAddress->state,
                        'PostalCode'         => $workOrder->shopAddress->zip_code,
                        'Country'            => $workOrder->shopAddress->country == 'US' ? 'USA' : $workOrder->shopAddress->country,
                    ],
                    'ClientCustomerId'           => $workOrder->company_person_id,
                    'Description'                => $personWorkOrder->qb_info,
                    'DriverNumber'               => $driverNumber,
                    'Latitude'                   => $workOrder->shopAddress->latitude,
                    'Longitude'                  => $workOrder->shopAddress->longitude,
                    'OnSiteDurationSeconds'      => $workOrder->estimated_time,
                    'RadiusInKm'                 => 1,
                    'ScheduledDateUtc'           => $scheduledDate->format('c'),
                    'ScheduledDurationSeconds'   => null,
                    //can be null - will be filled by fleetmatics
                    'StatusChangeDateUtc'        => null,
                    //can be null - will be filled by fleetmatics
                    'WorkOrderNumber'            => $workOrder->work_order_number.'_'.$personWorkOrder->person_id,
                    'WorkOrderStatusCode'        => $status['WorkOrderStatusCode'],
                    'WorkOrderStatusType'        => $status['WorkOrderStatusType'],
                    'WorkOrderStatusDescription' => $personWorkOrder->statusType ? $personWorkOrder->statusType->type_value : '',
                    'WorkOrderTypeCode'          => $workOrderType,
                ];
            }
        }

        return $sendData;
    }

    /**
     * Get and prepare work orders data for Fleetmatics API in MGM CRM
     * Data will be send via REST API to endpoint /workorders
     *
     * @param  WorkOrderFleetmaticsApiService  $workOrderFleetmaticsApiService  - API used to get status and type
     *
     * @return array
     */
    public function getFleetmaticsDataMGM(WorkOrderFleetmaticsApiService $workOrderFleetmaticsApiService)
    {
        //Columns to get
        $columns = [
            'la.link_address_truck_order_id',
            'truck_order.truck_order_id',
            'p.person_id as lp_person_id',
            DB::raw('person_name(p.person_id) as person_name'), //person name using mysql function
            't.type_value as type_name', //work order type name
            'truck_order.requested_delivery_date as delivery_scheduled_date',
            'truck_order.pickup_date_from as pickup_scheduled_date',
            'a.*',
            'lpwt.type_value as status_code', //work order actual status name
            'lpw.sent_to_fleetmatics_date', //if has been sent already
            'lpw.modified_date',
            'wo.work_order_number',
            'wo.company_person_id',
            'wo.estimated_time',
            'wo.description',
            'wo.work_order_id',
        ];
        //Get truck work orders query
        $truckWorkOrdersQuery = $this->getFleetmaticsDataTruckQuery();
        //Add additional conditions and get work orders
        $truckWorkOrders = $truckWorkOrdersQuery
            //If type is Pickup then will check pickup date otherwise if is Delivery then will check delivery date
            //Date is checked one day ahead
            ->whereRaw("CASE WHEN t.type_value = 'Pickup' THEN
            (DATE(truck_order.pickup_date_from) BETWEEN CURDATE() AND (CURDATE() + INTERVAL 1 DAY))
            WHEN t.type_value = 'Delivery' THEN
            (DATE(truck_order.requested_delivery_date) BETWEEN CURDATE() AND (CURDATE() + INTERVAL 1 DAY)) END")
            ->whereRaw('lpw.sent_to_fleetmatics_date IS NULL OR lpw.sent_to_fleetmatics_date < lpw.modified_date')
            //Group by link_address_truck_order_id - truck_order has many points (delivery and pickup)
            ->groupBy('la.link_address_truck_order_id')
            ->take(100) //get count of records
            ->get($columns);

        $sendData = [];
        foreach ($truckWorkOrders as $truckWorkOrder) {
            $status = $workOrderFleetmaticsApiService->getStatus($truckWorkOrder->status_code); //get status from API
            $typeName = $truckWorkOrder->type_name ? $truckWorkOrder->type_name : 'None';
            $workOrderType = $workOrderFleetmaticsApiService->getType($typeName); //get work order type code from API
            $driverNumber = $workOrderFleetmaticsApiService->getDriverByName($truckWorkOrder->person_name); //get driver number - may be null

            //Data field - depends on type
            if ($typeName == 'Delivery') {
                $dateField = $truckWorkOrder->delivery_scheduled_date;
            } elseif ($typeName == 'Pickup') {
                $dateField = $truckWorkOrder->pickup_scheduled_date;
            }
            $scheduledDate = \DateTime::createFromFormat('Y-m-d H:i:s', $dateField);

            //Define work order number in Fleetamtics
            $wokrOrderNumber = $truckWorkOrder->work_order_id.'_'.$truckWorkOrder->truck_order_id.'_'
                .ucfirst(substr($typeName, 0, 1)).$truckWorkOrder->link_address_truck_order_id;

            //Define work order send data
            $sendData[$wokrOrderNumber] = [
                'workOrderId'                => $truckWorkOrder->work_order_id,
                //will be used to update sent_to_fleetmatics_date
                'personId'                   => $truckWorkOrder->lp_person_id,
                //will be used to update sent_to_fleetmatics_date
                //isNew - is used to check if work order is new
                'isNew'                      => $truckWorkOrder->sent_to_fleetmatics_date == null,
                'ActualDateUtc'              => null,
                //can be null - will be filled by fleetmatics
                'ActualDurationSeconds'      => null,
                //can be null - will be filled by fleetmatics
                'Address'                    => [
                    'AddressLine1'       => $truckWorkOrder->address_1,
                    'AddressLine2'       => $truckWorkOrder->address_2,
                    'Locality'           => $truckWorkOrder->city,
                    'AdministrativeArea' => $truckWorkOrder->state,
                    'PostalCode'         => $truckWorkOrder->zip_code,
                    'Country'            => $truckWorkOrder->country == 'US' ? 'USA' : $truckWorkOrder->country,
                ],
                'ClientCustomerId'           => $truckWorkOrder->company_person_id,
                'Description'                => $truckWorkOrder->description,
                'DriverNumber'               => $driverNumber,
                'Latitude'                   => $truckWorkOrder->latitude,
                'Longitude'                  => $truckWorkOrder->longitude,
                'OnSiteDurationSeconds'      => $truckWorkOrder->estimated_time,
                'RadiusInKm'                 => 1,
                'ScheduledDateUtc'           => $scheduledDate->format('c'),
                'ScheduledDurationSeconds'   => null,
                //can be null - will be filled by fleetmatics
                'StatusChangeDateUtc'        => null,
                //can be null - will be filled by fleetmatics
                'WorkOrderNumber'            => $wokrOrderNumber,
                'WorkOrderStatusCode'        => $status['WorkOrderStatusCode'],
                'WorkOrderStatusType'        => $status['WorkOrderStatusType'],
                'WorkOrderStatusDescription' => $truckWorkOrder->status_code,
                'WorkOrderTypeCode'          => $workOrderType,
            ];
        }

        return $sendData;
    }

    /**
     * Get truck work orders base query
     *
     * @return array
     */
    public function getFleetmaticsDataTruckQuery()
    {
        //Get work orders query and group by addresses points (delivery and pickup) beacuse truck order has many points (usually 2 - 3)
        $truckWorkOrdersQuery = TruckOrder::join(
            'work_order as wo',
            'wo.work_order_id',
            '=',
            'truck_order.work_order_id'
        )
            ->join('link_person_wo as lpw', 'lpw.work_order_id', '=', 'wo.work_order_id')
            //join with type table to get status code
            ->join('type as lpwt', 'lpwt.type_id', '=', 'lpw.status_type_id')
            ->join('person as p', 'p.person_id', '=', 'lpw.person_id')
            ->join('link_address_truck_order as la', 'la.truck_order_id', '=', 'truck_order.truck_order_id')
            ->join('address as a', 'a.address_id', '=', 'la.address_id')
            //join with type table to get type code
            ->join('type as t', 't.type_id', '=', 'la.type_id')
            ->orderBy('truck_order.requested_delivery_date')
            ->orderBy('truck_order.pickup_date_from');

        return $truckWorkOrdersQuery;
    }

    /**
     * Get link person work orders from dates range
     *
     * @param  \DateTime  $dateFrom
     * @param  \DateTime|null  $dateTo
     * @param  array  $point  - address position data
     *
     * @return Collection
     */
    public function getWorkOrdersFromDates(\DateTime $dateFrom, \DateTime $dateTo = null, $point = [])
    {
        if (!$dateTo) {
            $dateTo = clone($dateFrom);
        }

        $validPoint = isset($point['latitude'], $point['longitude'], $point['distance_to_check']);

        //Columns to get
        $columns = [
            'link_person_wo.link_person_wo_id',
            DB::raw("person_name(p.person_id) as personName"),
            'link_person_wo.scheduled_date as lp_scheduled_date',
            'link_person_wo.work_order_id',
            'ad.external_address_id',
            'ad.latitude',
            'ad.longitude',
            'ad.address_id'
        ];

        //If point is set then calculate distance between addresses and point using geospatial queries
        if ($validPoint) {
            $columns[] = DB::raw("MIN(st_distance_sphere(point({$point['longitude']}, {$point['latitude']}), point(ad.longitude, ad.latitude))) as distance_from_point");
        }

        //Get work orders data
        $query = LinkPersonWo::select($columns)
            ->join('work_order as wo', 'wo.work_order_id', '=', 'link_person_wo.work_order_id')
            ->leftJoin('type as t', 't.type_id', '=', 'wo.wo_type_id')
            ->join('person as p', 'p.person_id', '=', 'link_person_wo.person_id')
            //get only work orders with addresses - the address is required to save work orders
            ->join('address as ad', 'ad.address_id', '=', 'wo.shop_address_id')
            ->whereRaw("DATE(link_person_wo.scheduled_date) BETWEEN '{$dateFrom->format('Y-m-d')}' AND '{$dateTo->format('Y-m-d')}'");

        if ($validPoint) { //also, get only records where distance is less than point allowed distance
            $query
                ->groupBy('wo.work_order_id')
                ->havingRaw("distance_from_point <= {$point['distance_to_check']}")
                ->orderBy('distance_from_point');
        }
        $personWorkOrders = $query->get();

        return $personWorkOrders;
    }


    /**
     * Get non completed Work Orders
     *
     * @param  int  $perPage
     * @param  array  $order
     *
     * @return Collection|LengthAwarePaginator
     */
    public function getWorkOrdersNonCompleted(
        $perPage = 50
    ) {
        $input = $this->getInput();
        // declaration of statuses ID's
        $woCompletedStatusTypeID = getTypeIdByKey('wo_status.completed');
        $woCancelledStatusTypeID = getTypeIdByKey('wo_status.canceled');

        //Columns to get
        $columns = [
            'work_order.work_order_id',
            DB::raw('person_name(work_order.company_person_id) as client'),
            'work_order.received_date',
            'work_order.expected_completion_date',
            'work_order.work_order_number',
            'ad.state',
            'ad.city',
            't1.type_value as wo_type',
            't2.type_value as wo_status'
        ];

        /** @var Builder $model */
        $model = WorkOrder::select($columns)
            ->leftJoin('type as t1', 't1.type_id', '=', 'work_order.wo_type_id')
            ->join('address as ad', 'ad.address_id', '=', 'work_order.shop_address_id')
            ->leftJoin('type as t2', 't2.type_id', '=', 'work_order.wo_status_type_id')
            ->whereRaw('wo_status_type_id not in ('.$woCancelledStatusTypeID.', '.$woCompletedStatusTypeID.')');

        if (!empty($input['sort'])) {
            $order = 'asc';
            if (stripos($input['sort'], '-') === 0 && stripos($input['sort'], '-') !== false) {
                $order = 'desc';
            }
            $orderTerm = str_replace('-', '', $input['sort']);
            $model->orderBy($orderTerm, $order);
        } else {
            $model->orderByDesc('expected_completion_date');
        }

        $this->setWorkingModel($model);

        $data = parent::paginate($perPage, []);

        $this->clearCountModel();
        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Get details from customer assigned to Work Order
     *
     * @param $id
     *
     * @return array
     */
    public function getCustomerDetails($id)
    {
        $emailTypeID = getTypeIdByKey('contact.email');
        $phoneTypeID = getTypeIdByKey('contact.phone');
        $faxTypeID = getTypeIdByKey('contact.fax');

        // create person repository
        $record = $this->find($id);
        $workOrder = $record->toArray();
        $data = [];
        if ($workOrder['company_person_id'] > 0) {
            $columns = [
                'person_id',
                'custom_1 AS name',
                DB::raw('(SELECT `value` FROM contact WHERE contact.`type_id` = '.$emailTypeID.' AND contact.`is_default` = 1 AND contact.`person_id` = person.person_id ORDER BY contact.`is_default` DESC LIMIT 1) AS email'),
                DB::raw('(SELECT `value` FROM contact WHERE contact.`type_id` = '.$phoneTypeID.' AND contact.`is_default` = 1 AND contact.`person_id` = person.person_id ORDER BY contact.`is_default` DESC LIMIT 1) AS phone'),
                DB::raw('(SELECT `value` FROM contact WHERE contact.`type_id` = '.$faxTypeID.' AND contact.`is_default` = 1 AND contact.`person_id` = person.person_id ORDER BY contact.`is_default` DESC LIMIT 1) AS fax'),
                DB::raw('(SELECT CONCAT_WS(",", address.`address_1`, address.`city`, UPPER(address.`state`), address.`zip_code`) FROM address  WHERE (address.`is_default` = 1 OR address.address_name = \'Main Office\') AND address.`person_id` = person.person_id ORDER BY address.`is_default` DESC LIMIT 1) AS address'),

            ];
            $model = Person::select($columns)
                ->whereRaw('person_id = '.$workOrder['company_person_id'].' ')->get();
            $data = $model[0]->toArray();
        }

        $data['customer_note'] = $this->getSlManager()->getCustomerNote($record->work_order_number);

        return $data;
    }

    /**
     * Get details from Site assinged to Work Order
     *
     * @param $id
     *
     * @return array
     */
    public function getSiteDetails($id)
    {
        $emailTypeID = getTypeIdByKey('contact.email');
        $phoneTypeID = getTypeIdByKey('contact.phone');
        $faxTypeID = getTypeIdByKey('contact.fax');
        $record = $this->find($id);
        $addressID = $record->getShopAddressId();
        $data = [];
        if ($addressID > 0) {
            $columns = [
                DB::raw('(SELECT `value` FROM contact WHERE contact.`type_id` = '.$emailTypeID.' AND contact.address_id = address.address_id ORDER BY contact.`is_default` DESC LIMIT 1) AS email'),
                DB::raw('(SELECT `value` FROM contact WHERE contact.`type_id` = '.$phoneTypeID.' AND contact.address_id = address.address_id ORDER BY contact.`is_default` DESC LIMIT 1) AS phone'),
                DB::raw('(SELECT `value` FROM contact WHERE contact.`type_id` = '.$faxTypeID.' AND contact.address_id = address.address_id ORDER BY contact.`is_default` DESC LIMIT 1) AS fax'),
                'address_name as name',
                DB::raw('CONCAT_WS(", ", address.`address_1`, address.`city`, UPPER(address.`state`), address.`zip_code`) AS address'),

            ];
            $model = Address::select($columns)
                ->whereRaw('address_id = '.$addressID.' ')->get();
            $data = $model[0]->toArray();
        }

        $data['site_note'] = $this->getSlManager()->getSiteNote($record->work_order_number);

        return $data;
    }

    /**
     * Get problem Details
     *
     * @param $id
     *
     * @return array
     */
    public function getProblemDetails($id)
    {
        $record = $this->find($id);
        $workOrder = $record->toArray();
        $data = [];
        $data['description'] = $workOrder['description'];

        $addressID = $record->getShopAddressId();
        // get all assets
        $sql = <<<"SQL"
SELECT asset.*, 
 IF (asset.type_id > 0, (SELECT type_value from type where type_id = asset.type_id), '') as type_id_value
 FROM asset WHERE asset.name NOT LIKE '%deleted%' 
AND 
asset.address_id IN (
    SELECT address.address_id FROM address WHERE address.address_name = '{$workOrder['fin_loc']}' OR address.address_id = {$addressID}
)
AND asset.name != '' AND asset.name != 'FieldConnect' AND asset.address_id IS NOT NULL ; 
SQL;
        $data['equipment'] = []; #fixme: need to get equipment assigned to work order
        $list = DB::select(DB::raw($sql));

        foreach ($list as $a) {
            $data['equipment'][] = [
                'name'          => $a->name,
                'asset_id'      => $a->asset_id,
                'id'            => $a->asset_id,
                'type_id_value' => $a->type_id_value,
                'manufacturer'  => $a->manufacturer,
                'model'         => $a->model_number,
                'serial_number' => $a->serial_number,
            ];
        }
        $data['problem_note'] = $this->getSlManager()->getProblemNote($record->work_order_number);

        return $data;
    }

    public function getAssetsByWOPersonID($linkPersonWOID)
    {
    }

    /**
     * Get priorities for dropdown
     *
     * @return array
     */
    public function getPriority()
    {
        $ds = $this->app->make(WorkOrderDataServiceContract::class);
        $types = $ds->getTypes(['crm_priority_type_id']);
        $data = [];
        foreach ($types['crm_priority_type_id'] as $id => $value) {
            $data[] = ['label' => $value, 'value' => $id];
        }

        return $data;
    }

    /**
     * get WO statuses for dropdown
     *
     * @return array
     */
    public function getWoStatus()
    {
        $ds = $this->app->make(WorkOrderDataServiceContract::class);
        $types = $ds->getTypes(['wo_status_type_id']);
        $data = [];
        foreach ($types['wo_status_type_id'] as $id => $value) {
            $data[] = ['label' => $value, 'value' => $id];
        }

        return $data;
    }

    /**
     * get WO statuses for dropdown
     *
     * @return array
     */
    public function getInvoiceStatus()
    {
        $ds = $this->app->make(WorkOrderDataServiceContract::class);
        $types = $ds->getTypes(['invoice_status_type_id']);
        $data = [];
        foreach ($types['invoice_status_type_id'] as $id => $value) {
            $data[] = ['label' => $value, 'value' => $id];
        }

        return $data;
    }


    /**
     * get locations by requested person_id
     *
     * @param $request
     *
     * @return array
     */
    public function getLocations($request)
    {
        $locations = [];

        $phoneTypeID = getTypeIdByKey('contact.phone');
        $faxTypeID = getTypeIdByKey('contact.fax');

        $sql = "SELECT address_1, address_name, city, country, (SELECT contact.value from contact where contact.address_id = address.address_id and type_id = '.$faxTypeID.' limit 1) fax, address_id as id, is_default, (SELECT contact.value from contact where contact.address_id = address.address_id and type_id = '.$phoneTypeID.' limit 1) phone, state, zip_code 
from address where person_id = ".$request['person_id'].' ORDER by address_name asc;';
        $list = DB::select(DB::raw($sql));
        $list2 = [];

        foreach ($list as $l) {
            $list2[] = [
                'label'                 => $l->address_name.' ('.$l->address_1.' '.$l->city.')',
                'value'                 => $l->id,
                'shop_address_id_value' => $l
            ];
        }

        return $list2;
    }

    /**
     * get work order and tech info for array purpose
     *
     * @param $id
     *
     * @return array
     */
    public function getWOReassign($id)
    {
        // load relationships
        $output = ['work_order' => [], 'assigned_to' => []];

        $linkCancelled = getTypeIdByKey('wo_vendor_status.canceled');
        $linkAssigned = getTypeIdByKey('wo_vendor_status.assigned');
        $sql = 'SELECT work_order.*, person_name(company_person_id) as company_person_id_value from work_order where work_order_id = '.$id.';';
        $list = DB::select(DB::raw($sql));
        if (is_array($list)) {
            foreach ($list as $l) {
                $output['work_order'] = $l;
            }
        }
        $sql = 'SELECT person_id, person_name(person_id) AS person_name, status_type_id, (SELECT type_value FROM type WHERE type_id = status_type_id LIMIT 1) AS status_name FROM link_person_wo WHERE work_order_id = '.$id.' AND status_type_id <> '.$linkCancelled.' limit 1;';
        $list = DB::select(DB::raw($sql));
        if (is_array($list)) {
            foreach ($list as $l) {
                if ($l->status_type_id == $linkAssigned) {
                    $l->can_be_reassigned = 0;
                } else {
                    $l->can_be_reassigned = 1;
                }
                $output['assigned_to'] = $l;
            }
        }

        return $output;
    }

    /**
     * Get SL work order manager instance
     *
     * @return SlManager
     */
    private function getSlManager()
    {
        return $this->app[SlManager::class];
    }

    /**
     * Get SL tech link importer instance
     *
     * @return SlTechLinkImporter
     */
    private function getSlTechLinkImporter()
    {
        return $this->app[SlTechLinkImporter::class];
    }

    /**
     * {@inheritdoc}
     */
    public function showBFC($id, $forEdit = false)
    {
        $record = $this->find($id, [
            'work_order.*',
            'IF(work_order.company_person_id > 0, person_name(work_order.company_person_id ), "") as company_person_id_value',
            'IF(work_order.project_manager_person_id > 0, person_name(work_order.project_manager_person_id ), "") as project_manager_person_id_value',
            //'IF(work_order.company_person_id > 0, (SELECT sl_record_id FROM sl_records WHERE table_name = \'person\' AND record_id = work_order.company_person_id), "") as customerID', // too slow
            'IF(work_order.crm_priority_type_id > 0, (SELECT type_value from type where type_id = work_order.crm_priority_type_id), "") as crm_priority_type_id_value',
            'IF(work_order.invoice_status_type_id > 0, (SELECT type_value from type where type_id = work_order.invoice_status_type_id), "") as invoice_status_type_id_value',
            '(UTC_TIMESTAMP()-modified_date) as last_edit_delay',
            "DATE_FORMAT(DATE_SUB(expected_completion_date,
                INTERVAL 1 DAY),'%m/%d/%Y')  as day_before_ecd",
            '(SELECT max(link_person_wo_id) from link_person_wo lpwo where lpwo.work_order_id = work_order.work_order_id and lpwo.is_disabled <> 1) as
                link_person_wo_id',
        ]);
        $output['item'] = $record->toArray();

        $output['item']['id'] = $id;

        //if ($forEdit) {
        // lock status verification

        //CRMBFC-2687 Turn off locking WOs
        //list($record, $output) = $this->handleLockStatus($record, $output);

        // validation rules
        $output['fields'] = $this->getRequestRules('update');
        //}

        $output['link_person_wo'] = $this->getRepository('LinkPersonWo', 'WorkOrder')
            ->getLinkPersonWo($id);

        // pickup date
        $record->load('pickupDate');
        if ($record->pickupDate) {
            $output['item']['pickup_date']
                = $record->pickupDate->getCreatedAt();
        }

        // cancelled data
        $cancelledId = $this->type->getIdByKey('wo_status.canceled');
        if ($output['item']['wo_status_type_id'] == $cancelledId) {
            $hRepo = $this->makeRepository('History');
            $output['item']['canceled']
                = $hRepo->getRecordColumnValueToLastHistory(
                    'work_order',
                    $record->getId(),
                    'wo_status_type_id',
                    $cancelledId
                );
        }

        // set values for all type id columns
        $output = $this->addTypeColumnsValues($record, $output, $forEdit);


        // set values for all type id columns
        $output = $this->addTypeColumnsValues($record, $output, $forEdit);

        // get data for current location and customer_setting_id
        $output = $this->addOtherColumnsValues($record, $output);

        // can be reassigned unless the work order has an open timesheet in CRM
        $output['item']['can_be_reassigned'] = $this->canReassign($id);

        // get attributes from SL
        $slDetails = $this->getSlManager()->getDetails($record->work_order_number);
        $output['item'] = array_merge($output['item'], $slDetails);

        $slRecordsManager = app(SlRecordsManager::class);
        if (!empty($output['item']['assigned_tech_id'])) {
            try {
                $personId = $slRecordsManager->findPersonId($output['item']['assigned_tech_id']);

                $output['item']['assigned_tech_id_name'] = Person::findOrFail($personId)->getName();
            } catch (ModelNotFoundException $e) {
                $output['item']['assigned_tech_id_name'] = null;
            }
        }
        // get SL Customer ID
        if (!empty($output['item']['company_person_id'])) {
            try {
                $output['item']['customerID'] = $slRecordsManager->findSlRecordId(
                    'person',
                    $output['item']['company_person_id']
                );
            } catch (ModelNotFoundException $e) {
                $output['item']['customerID'] = null;
            }
        }


        if (!empty($output['item']['original_tech_id'])) {
            if (!empty($output['item']['assigned_tech_id']) && $output['item']['assigned_tech_id'] === $output['item']['original_tech_id']) {
                $output['item']['original_tech_id_name'] = $output['item']['assigned_tech_id_name'];
            } else {
                try {
                    $personId = $slRecordsManager->findPersonId($output['item']['original_tech_id']);

                    $output['item']['original_tech_id_name'] = Person::findOrFail($personId)->getName();
                } catch (ModelNotFoundException $e) {
                    $output['item']['original_tech_id_name'] = null;
                }
            }
        }

        // get permission flags
        if ($user = $this->app['auth']->user()) {
            $output['item'] = array_merge($output['item'], $this->getBFCPermissionFlags($user));
        }

        if (config('wgln.enabled')) {
            if (!empty($output['item']['assigned_tech_id']) && app(WglnService::class)->hasPermissionsByDriverKeyToWgln($output['item']['assigned_tech_id']) === true) {
                if (trim($output['item']['processed_by']) === 'RoutePlanner') {
                    $output['item']['cannot_edit_assigned_tech'] = true;
                    $output['item']['can_edit_assigned_tech'] = false;
                }
            }

            if (!empty($output['item']['link_person_wo_id'])) {
                $output['item']['root_order_key'] = $this->getRepository('WglnLink', 'Wgln')
                    ->getRootOrderKey('link_person_wo', $output['item']['link_person_wo_id']);
            }
        }

        if (!empty($output['item']['customerID'])) {
            // get external notes if enabled for the customer

            $noteSync = $this->app[SlExternalNoteSync::class];

            if ($noteSync->isCustomerEnabled($output['item']['customerID'])) {
                $output['item']['external_notes'] = $noteSync->getNotes($record->work_order_number);
            }

            // enable external files

            $fileSync = $this->app[SlExternalFileSync::class];

            if ($fileSync->isCustomerEnabled($output['item']['customerID'])) {
                $output['item']['external_files'] = true;
            }
        }

        return $output;
    }

    /**
     * Get permission flags for BFC work order
     *
     * @param  User  $user
     *
     * @return     array
     */
    public function getBFCPermissionFlags($user)
    {
        $slStatuses = $this->getSlTechStatuses();
        $cannotEditStatuses = [];
        foreach ($slStatuses as $status) {
            if (!$user->can('workorder.edit_sl_tech_status_'.strtolower(str_replace(' ', '_', $status['label'])))) {
                $cannotEditStatuses[] = $status;
            }
        }

        $canEditStatuses = [];
        foreach ($slStatuses as $status) {
            if ($user->can('workorder.edit_sl_tech_status_'.strtolower(str_replace(' ', '_', $status['label'])))) {
                $canEditStatuses[] = $status;
            }
        }

        return [
            'cannot_edit_customer_po'      => !$user->can('workorder.edit_customer_po'),
            'cannot_edit_schedule_date'    => !$user->can('workorder.edit_schedule_date'),
            'cannot_edit_assigned_tech'    => !$user->can('workorder.edit_assigned_tech'),
            'cannot_edit_problem_notes'    => !$user->can('workorder.edit_problem_notes'),
            'cannot_edit_call_notes'       => !$user->can('workorder.edit_call_notes'),
            'cannot_edit_customer_note'    => !$user->can('workorder.edit_customer_notes'),
            'cannot_edit_site_note'        => !$user->can('workorder.edit_site_notes'),
            'cannot_edit_sl_tech_statuses' => $cannotEditStatuses,

            'can_edit_customer_po'      => $user->can('workorder.edit_customer_po'),
            'can_edit_schedule_date'    => $user->can('workorder.edit_schedule_date'),
            'can_edit_assigned_tech'    => $user->can('workorder.edit_assigned_tech'),
            'can_edit_problem_notes'    => $user->can('workorder.edit_problem_notes'),
            'can_edit_call_notes'       => $user->can('workorder.edit_call_notes'),
            'can_edit_customer_note'    => $user->can('workorder.edit_customer_notes'),
            'can_edit_site_note'        => $user->can('workorder.edit_site_notes'),
            'can_edit_processed_by'     => $user->can('workorder.edit_processed_by'),
            'can_edit_sl_tech_statuses' => $canEditStatuses,

        ];
    }

    /**
     * Whether work order can be reassigned
     *
     * @param  int  $id
     *
     * @return bool
     */
    public function canReassign($id)
    {
        return !$this->app['db']
            ->table('time_sheet as ts')
            ->join('link_person_wo as lpwo', 'lpwo.link_person_wo_id', '=', 'ts.table_id')
            ->where('ts.table_name', 'link_person_wo')
            ->where('lpwo.work_order_id', $id)
            ->where('lpwo.is_disabled', '<>', 1)
            ->whereNull('ts.entry_date2')
            ->exists();
    }

    /**
     * Update BFC work order from input
     *
     * @param  int  $id
     * @param  array  $input
     *
     * @return void
     */
    public function updateWithIdAndInputBFC($id, array $input)
    {
        /** @var WorkOrder $object */
        $object = $this->getModel()->find($id);

        if ($object === null) {
            throw with(new ModelNotFoundException())
                ->setModel(get_called_class());
        }

        // CRMBFC-2687 Turn off locking WOs - quick fix for locked_id === 0
        if (!config('app.ignore_locks')) {
            if ($object->getLockedId() && $object->getLockedId() != 0 && $object->getLockedId() != getCurrentPersonId()) {
                $exception = $this->app->make(LockedMismatchException::class);
                $exception->setData([
                    'table_name'        => 'work_order',
                    'id'                => $id,
                    'locked_id'         => $object->getLockedId(),
                    'current_person_id' => getCurrentPersonId(),
                ]);

                throw $exception;
            }
        }

        $workOrderNumber = $object->work_order_number;
        $slManager = $this->getSlManager();

        if (isset($input['call_type_id'])) {
            $slManager->updateCallType($workOrderNumber, $input['call_type_id']);
        }
        if (isset($input['sl_wo_status_id'])) {
            $slManager->updateCallStatus($workOrderNumber, $input['sl_wo_status_id']);
        }
        if (isset($input['customer_po'])) {
            $slManager->updateCustomerPo($workOrderNumber, $input['customer_po']);
        }
        if (isset($input['call_note'])) {
            $slManager->updateCallNote($workOrderNumber, $input['call_note']);
        }
        if (isset($input['problem_note'])) {
            $slManager->updateProblemNote($workOrderNumber, $input['problem_note']);
        }
        if (isset($input['comments'])) {
            $slManager->updateComments($workOrderNumber, $input['comments']);
        }
        if (isset($input['processed_by'])) {
            $slManager->updateProcessedBy($workOrderNumber, $input['processed_by']);
        }
        
        if (isset($input['sl_tech_status_id'])) {
            $this->checkCanReassign($id);

            if ($input['sl_tech_status_id'] === 'C' && !empty($input['completed_date'])) {
                try {
                    $date = Carbon::parse($input['completed_date'])->format('Y-m-d H:i:s');

                    $linkPersonWo = app(LinkPersonWoRepository::class)->getLinkPersonWoByWorkOrderId($id);
                    if ($linkPersonWo) {
                        $linkPersonWo->completed_date = $date;
                        $linkPersonWo->save();
                    }
                } catch (\Exception $e) {
                }
            }

            $slManager->updateTechStatus($workOrderNumber, $input['sl_tech_status_id']);

            $this->updateTechLinkFromSl($workOrderNumber, true);

            if (config('wgln.enabled')) {
                /** @var WglnService $wglnService */
                $wglnService = app(WglnService::class);
                $wglnService->sendNewStatusToWglnByWorkOrderNumber($workOrderNumber);
            }
        }
        if (isset($input['assigned_tech_id'])) {
            $this->checkCanReassign($id);

            $slManager->updateAssignedTech($workOrderNumber, $input['assigned_tech_id']);

            $this->updateTechLinkFromSl($workOrderNumber);
        }

        if (isset($input['scheduled_date'])) {
            $this->checkCanReassign($id);

            $slManager->updateScheduledDate($workOrderNumber, $input['scheduled_date']);

            $this->updateTechLinkFromSl($workOrderNumber);
        }
    }

    /**
     * Send note to external customer's system
     * @param  int $id
     * @param  string $note
     * @return string
     */
    public function sendExternalNoteBfc($id, $note)
    {
        $workOrder = $this->find($id);

        return $this->app[SlExternalNoteSync::class]->sendNote($workOrder, $note);
    }

    /**
     * Send file to external customer's system
     * @param  int $id
     * @param  int $fileId
     * @return string
     */
    public function sendExternalFileBfc($id, $fileId)
    {
        $workOrder = $this->find($id);
        $file = File::findOrFail($fileId);

        return $this->app[SlExternalFileSync::class]->uploadFile($workOrder, $file);
    }

    /**
     * Check if work order can be reassigned
     *
     * @param  int  $id
     *
     * @return void
     */
    private function checkCanReassign($id)
    {
        if (!$this->canReassign($id)) {
            throw $this->app->make(
                WoBfcCannotReassignException::class,
                ['errorMessage' => 'Cannot update while there is an open time sheets in the work order']
            );
        }
    }

    /**
     * Update tech link (link_person_wo) from SL
     *
     * @param  string  $workOrderNumber
     *
     * @param  bool  $updateStatuses
     *
     * @return void
     */
    private function updateTechLinkFromSl($workOrderNumber, $updateStatuses = false)
    {
        $importer = $this->getSlTechLinkImporter();

        if ($updateStatuses) {
            $importer->updateStatuses();
        }

        $importer->filterServiceCallId($workOrderNumber);

        $importer->import();
    }

    /**
     * ToDo: function that will store the customer fields from work order request
     *
     * @param $id
     * @param $request
     *
     * @return array
     */
    public function storeCustomerDetails($id, $request)
    {
        $data = [];
        // types
        $emailTypeID = getTypeIdByKey('contact.email');
        $phoneTypeID = getTypeIdByKey('contact.phone');
        $faxTypeID = getTypeIdByKey('contact.fax');

        // wo record
        $record = $this->find($id);
        $companyPersonID = $record->getCompanyPersonId();

        // check and update email
        $email = $request['email'];
        $sql = DB::raw('SELECT * FROM contact WHERE contact.`type_id` = '.$emailTypeID.' AND contact.`is_default` = 1 AND contact.`person_id` = '.$companyPersonID.' ORDER BY contact.`is_default` DESC LIMIT 1;');
        $emailObject = DB::select($sql);
        if (empty($emailObject)) {
            $contact = new Contact();
            $contact->name = 'Email';
            $contact->value = $email;
            $contact->type_id = $emailTypeID;
            $contact->person_id = $companyPersonID;
            $contact->is_default = 1;
            $contact->address_id = 0;
            $contact->user_mobile = 0;
            $contact->system_mobile = 0;
            $contact->save();
        } else {
            if ($email != $emailObject[0]->value) {
                $contact = new Contact();
                $contact = $contact->find($emailObject[0]->contact_id);
                $contact->value = $email;
                $contact->save();
            }
        }

        // check and update phone
        $phone = $request['phone'];
        $sql = DB::raw('SELECT * FROM contact WHERE contact.`type_id` = '.$phoneTypeID.' AND contact.`is_default` = 1 AND contact.`person_id` = '.$companyPersonID.' ORDER BY contact.`is_default` DESC LIMIT 1;');
        $phoneObject = DB::select($sql);
        if (empty($phoneObject)) {
            $contact = new Contact();
            $contact->name = 'Phone';
            $contact->value = $phone;
            $contact->type_id = $phoneTypeID;
            $contact->person_id = $companyPersonID;
            $contact->is_default = 1;
            $contact->address_id = 0;
            $contact->user_mobile = 0;
            $contact->system_mobile = 0;
            $contact->save();
        } else {
            if ($email != $phoneObject[0]->value) {
                $contact = new Contact();
                $contact = $contact->find($phoneObject[0]->contact_id);
                $contact->value = $phone;
                $contact->save();
            }
        }

        // check and update fax
        $fax = $request['fax'];
        $sql = DB::raw('SELECT * FROM contact WHERE contact.`type_id` = '.$faxTypeID.' AND contact.`is_default` = 1 AND contact.person_id = '.$companyPersonID.' ORDER BY contact.`is_default` DESC LIMIT 1;');
        $faxObject = DB::select($sql);
        if (empty($faxObject)) {
            $contact = new Contact();
            $contact->name = 'Fax';
            $contact->value = $fax;
            $contact->type_id = $faxTypeID;
            $contact->person_id = $companyPersonID;
            $contact->is_default = 1;
            $contact->address_id = 0;
            $contact->user_mobile = 0;
            $contact->system_mobile = 0;
            $contact->save();
        } else {
            if ($email != $faxObject[0]->value) {
                $contact = new Contact();
                $contact = $contact->find($faxObject[0]->contact_id);
                $contact->value = $fax;
                $contact->save();
            }
        }

        // check and update customer note
        $customer_note = $request['customer_note'];
        if (!empty($customer_note)) {
            $company = new Company();
            $company = $company->findOrFail($companyPersonID);
            $company->notes = $customer_note;
            $company->save();
        }

        return $this->getCustomerDetails($id);
    }

    /**
     * ToDo: function that will store the Site Details fields from work order request
     *
     * @param $id
     * @param $request
     *
     * @return array
     */
    public function storeSiteDetails($id, $request)
    {
        $data = [];
        // types
        $emailTypeID = getTypeIdByKey('contact.email');
        $phoneTypeID = getTypeIdByKey('contact.phone');
        $faxTypeID = getTypeIdByKey('contact.fax');

        // wo record
        $record = $this->find($id);
        $addressID = $record->getShopAddressId();
        $companyPersonID = $record->getCompanyPersonId();

        // check and update email
        $email = $request['email'];
        $sql = DB::raw('SELECT * FROM contact WHERE contact.`type_id` = '.$emailTypeID.' AND contact.address_id = '.$addressID.' ORDER BY contact.`is_default` DESC LIMIT 1;');
        $emailObject = DB::select($sql);
        if (empty($emailObject)) {
            $contact = new Contact();
            $contact->name = 'Email';
            $contact->value = $email;
            $contact->type_id = $emailTypeID;
            $contact->person_id = $companyPersonID;
            $contact->is_default = 0;
            $contact->address_id = $addressID;
            $contact->user_mobile = 0;
            $contact->system_mobile = 0;
            $contact->save();
        } else {
            if ($email != $emailObject[0]->value) {
                $contact = new Contact();
                $contact = $contact->find($emailObject[0]->contact_id);
                $contact->value = $email;
                $contact->save();
            }
        }

        // check and update phone
        $phone = $request['phone'];
        $sql = DB::raw('SELECT * FROM contact WHERE contact.`type_id` = '.$phoneTypeID.' AND contact.address_id = '.$addressID.' ORDER BY contact.`is_default` DESC LIMIT 1;');
        $phoneObject = DB::select($sql);
        if (empty($phoneObject)) {
            $contact = new Contact();
            $contact->name = 'Phone';
            $contact->value = $phone;
            $contact->type_id = $phoneTypeID;
            $contact->person_id = $companyPersonID;
            $contact->is_default = 0;
            $contact->address_id = $addressID;
            $contact->user_mobile = 0;
            $contact->system_mobile = 0;
            $contact->save();
        } else {
            if ($email != $phoneObject[0]->value) {
                $contact = new Contact();
                $contact = $contact->find($phoneObject[0]->contact_id);
                $contact->value = $phone;
                $contact->save();
            }
        }

        // check and update fax
        $fax = $request['fax'];
        $sql = DB::raw('SELECT * FROM contact WHERE contact.`type_id` = '.$faxTypeID.' AND contact.address_id = '.$addressID.' ORDER BY contact.`is_default` DESC LIMIT 1;');
        $faxObject = DB::select($sql);
        if (empty($faxObject)) {
            $contact = new Contact();
            $contact->name = 'Fax';
            $contact->value = $fax;
            $contact->type_id = $faxTypeID;
            $contact->person_id = $companyPersonID;
            $contact->is_default = 0;
            $contact->address_id = $addressID;
            $contact->user_mobile = 0;
            $contact->system_mobile = 0;
            $contact->save();
        } else {
            if ($email != $faxObject[0]->value) {
                $contact = new Contact();
                $contact = $contact->find($faxObject[0]->contact_id);
                $contact->value = $fax;
                $contact->save();
            }
        }

        $address_note = $request['site_note'];
        if (!empty($address_note)) {
            $address = new Address();
            $address = $address->findOrFail($addressID);
            $address->note = $address_note;
            $address->save();
        }

        return $this->getSiteDetails($id);
    }

    /**
     * Store problem note
     *
     * @param $id
     *
     * @return array
     */
    public function storeProblemNote($id, $request)
    {
        // request - problem_note
        $problemNote = $request['problem_note'];

        $object = $this->getModel()->find($id);

        $this->getSlManager()->updateProblemNote($object->work_order_number, $problemNote);
    }

    /**
     * Store customer note
     *
     * @param $id
     *
     * @return array
     */
    public function storeCustomerNote($id, $request)
    {
        // request - customer_note
        $customerNote = $request['customer_note'];

        $object = $this->getModel()->find($id);

        $this->getSlManager()->updateCustomerNote($object->work_order_number, $customerNote);
    }

    /**
     * Store site note
     *
     * @param $id
     *
     * @return array
     */
    public function storeSiteNote($id, $request)
    {
        // request - site_note
        $siteNote = $request['site_note'];

        $object = $this->getModel()->find($id);

        $this->getSlManager()->updateSiteNote($object->work_order_number, $siteNote);
    }

    /**
     * @return array
     */
    public function storeWOReassign($id, $request)
    {
        $data = [];

        /*
         * #fixme for Karol Wach :)
         * In request : tech_id (int), scheduled_for (date)
         *
         1. check SL if can be changed
         2. check CRM if can be changed
         3. update SL with new EmployeeID
         4. update CRM (link_person_wo) - reassign or cancel and add new link_person_wo
         */
        return $data;
    }

    /**
     * get Call types for dropdown
     *
     * @return array
     */
    public function getSlCallTypes()
    {
        return $this->getSlManager()->getCallTypes();
    }

    /**
     * get SL WO Statuses for dropdown
     *
     * @return array
     */
    public function getSlWoStatuses()
    {
        return $this->getSlManager()->getCallStatuses();
    }

    /**
     * get SL Tech Statuses for dropdown
     *
     * @return array
     */
    public function getSlTechStatuses()
    {
        return $this->getSlManager()->getTechStatuses();
    }

    /**
     * get SL Technicians for dropdown
     *
     * @return array
     */
    public function getSlTechnicians()
    {
        return $this->getSlManager()->getTechnicians();
    }

    /**
     * Get work order history for BFC
     *
     * @param  int  $id
     *
     * @return array
     */
    public function getHistoryBfc($id)
    {
        $object = $this->getModel()->find($id);

        // get CRM history
        $history = $this->app['db']
            ->table('history')
            ->selectRaw(
                'history.history_id AS id, history.person_id, history.tablename,
                history.record_id, history.related_tablename,
                history.related_record_id, history.columnname,
                history.value_from, history.value_to, history.action_type,
                history.date_created as `created_at`,
                person.custom_1, person.custom_3, \'crm\' as source'
            )
            ->leftJoin('person', 'person.person_id', '=', 'history.person_id')
            ->where(function ($q) use ($id) {
                $q
                    ->where('tablename', 'work_order')
                    ->where('record_id', $id);
            })
            ->orWhere(function ($q) use ($id) {
                $q
                    ->where('related_tablename', 'work_order')
                    ->where('related_record_id', $id);
            })
            ->orderByDesc('id')
            ->get();

        if (!is_array($history)) {
            $history = $history->toArray();
        }

        $history = $this->app->make(HistoryRepository::class)->mapData($history);

        // get SL history
        $slHistory = $this->getSlManager()->getHistory($object->work_order_number);

        if (!is_array($slHistory)) {
            $slHistory = $slHistory->toArray();
        }

        // merge and sort by date
        $history = array_merge($history, $slHistory);
        usort($history, function ($a, $b) {
            return strcmp($b->created_at, $a->created_at);
        });

        return ['data' => $history];
    }

    private function addRowNumbers(array &$data, $reverse, $total)
    {
        $index = $reverse ? $total : 1;
        foreach ($data['data'] as $key => $value) {
            $data['data'][$key]['index'] = $reverse ? $index-- : $index++;
        }
    }


    /**
     * Get open work orders
     *
     * @param $input
     *
     * @return Collection|LengthAwarePaginator
     */
    public function getOpenWorkOrders(
        $input
    ) {
        $shopAddressID = 0;
        $currentWorkOrder = [];
        $legacyURL = $this->app->config->get('app.legacy_url');

        $techLocations = [];
        $techHomeLocations = [];

        // work order and related data
        if (isset($input['work_order_id']) && $input['work_order_id'] > 0) {
            $workOrderID = $input['work_order_id'];
            $workOrder = WorkOrder::find($workOrderID);
            $select = " SELECT work_order_id, received_date, work_order_number, t1.type_value AS wo_status, work_order.description, t2.type_value AS trade, t2.color AS trade_color, 
 t3.type_value AS parts_status, t4.type_value AS quote_status, 
  expected_completion_date AS ecd,
  a.latitude, a.longitude
                 FROM work_order 
                  LEFT JOIN `type` t1 ON t1.type_id = work_order.wo_status_type_id 
                   LEFT JOIN `type` t2 ON t2.type_id = work_order.trade_type_id
                   LEFT JOIN `type` t3 ON t3.type_id = work_order.parts_status_type_id 
             LEFT JOIN `type` t4 ON t4.type_id = work_order.quote_status_type_id 
             LEFT JOIN address a ON a.address_id = work_order.`shop_address_id` 
             WHERE work_order.work_order_id = ".$workOrderID."
            ";
            $list = DB::select(DB::raw("$select LIMIT 1"));
            $currentWorkOrder = $list;
            if (isset($list[0])) {
                $l = $list[0];
                $l->assigned_to = [];
                $techs = DB::select(DB::raw("SELECT person_id, person_name(person_id) as tech_name, (t1.type_value) as status from link_person_wo
 LEFT JOIN type t1 on t1.type_id = status_type_id
 where work_order_id = ".$l->work_order_id." "));
                foreach ($techs as $i => $t) {
                    $techLocation = DB::select(DB::raw("SELECT * from gps_location where person_id = {$t->person_id} order by timestamp desc limit 1;"));
                    if (isset($techLocation[0])) {
                        if ($techLocation[0]->latitude != '' && $techLocation[0]->longitude != '') {
                            $t->current_location = [
                                'latitude'  => $techLocation[0]->latitude / 1000000,
                                'longitude' => $techLocation[0]->longitude / 1000000,
                                'last_seen' => date('m/d/y H:i:s', $techLocation[0]->timestamp)
                            ];
                            $techLocations[$t->person_id] = [
                                'latitude'  => $techLocation[0]->latitude / 1000000,
                                'longitude' => $techLocation[0]->longitude / 1000000,
                                'last_seen' => date(
                                    'm/d/y H:i:s',
                                    $techLocation[0]->timestamp
                                )
                            ];
                        }
                    }
                    $techLocation2 = DB::select(DB::raw("SELECT  address_id, latitude, longitude FROM address WHERE person_id = {$t->person_id} ORDER BY is_default DESC LIMIT 1;"));
                    if (isset($techLocation2[0])) {
                        if ($techLocation2[0]->latitude != '' && $techLocation2[0]->longitude != '') {
                            $t->home_location = [
                                'latitude'  => $techLocation2[0]->latitude * 1,
                                'longitude' => $techLocation2[0]->longitude * 1
                            ];
                            $techHomeLocations[$t->person_id] = [
                                'latitude'  => $techLocation2[0]->latitude * 1,
                                'longitude' => $techLocation2[0]->longitude * 1
                            ];
                        }
                    }

                    $l->assigned_to[] = $t;
                }
                $l->wo_url = $legacyURL.'/work_order/edit?work_order_id='.$l->work_order_id;

                $hsl = HTMLToRGBToHSL($l->trade_color);
                $l->trade_font_color = ($hsl->lightness > 118 ? '#000000' : '#FFFFFF');
                $currentWorkOrder = $l;
            }

            $shopAddressID = $workOrder->getShopAddressId();
        }
        // shop address_id
        if (isset($input['shop_address_id']) && $input['shop_address_id'] > 0) {
            $shopAddressID = $input['shop_address_id'];
        } else {
            // throw error ?
        }

        // distance filter
        if (isset($input['distance']) && $input['distance'] > 0) {
            $distance = $input['distance'];
        } else {
            $distance = 0;
        }

        // limit for query
        if (isset($input['limit']) && $input['limit'] > 0) {
            $limit = $input['limit'];
        } else {
            $limit = 10;
        }

        $isGoingHome = 0;
        if (isset($input['is_going_home']) && $input['is_going_home'] == '1') {
            $isGoingHome = 1;
        }

        $showWithActiveTechAssigned = 0;
        if (isset($input['show_with_active_techs']) && $input['show_with_active_techs'] == '1') {
            $showWithActiveTechAssigned = 1;
        }

        // "show unassigned"
        $showUnassignedWO = 0;
        if (isset($input['show_unassigned']) && $input['show_unassigned'] == '1') {
            $showUnassignedWO = 1;
        }

        // "show open"
        $showOpenWO = 0;
        if (isset($input['show_open']) && $input['show_open'] == '1') {
            $showOpenWO = 1;
        }

        // "show unfinished"
        $showUnfinishedWO = 0;
        if (isset($input['show_unfinished']) && $input['show_unfinished'] == '1') {
            $showUnfinishedWO = 1;
        }

        if (!empty($shopAddressID)) {
            $distanceSQL = '';

            if (isset($input['type']) && $input['type'] == 'city') {
                if (isset($input['map_lat']) && isset($input['map_lng']) && !empty($input['map_lat']) && !empty($input['map_lng'])) {
                    $distanceSQL = "(3959  * ACOS( 
            COS( RADIANS(".$input['map_lat'].") ) 
          * COS( RADIANS( a.latitude ) ) 
          * COS( RADIANS( a.longitude ) - RADIANS(".$input['map_lng'].") ) 
          + SIN( RADIANS(".$input['map_lat'].") ) 
          * SIN( RADIANS( a.latitude  ) )
            ) )";
                }
                /*
                 * Old Version: search by name
                 * if ( isset($input['city']) && strlen($input['city']) > 3 ) {
                    // for test only !!
                    $google_api_key = 'AIzaSyC3siq8N7dlNtmOMlpoXeiE2sJKA4L-DbM';
                    //https://maps.googleapis.com/maps/api/geocode/json?address="+markers[i][0]
                    $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($input['city'])  . '&key=' . $google_api_key;
                    $content = file_get_contents($url);
                    $content = json_decode($content, true);

                    if (!empty($content)) {

                        if (isset($content['results'][0]['geometry']["location"])) {
                            $location = $content['results'][0]['geometry']["location"];
                            $input['city_latitude'] = $location['lat'];
                            $input['city_longitude'] = $location['lng'];
                            $distanceSQL = "(3959  * ACOS(
            COS( RADIANS(" . $input['city_latitude']  . ") )
          * COS( RADIANS( a.latitude ) )
          * COS( RADIANS( a.longitude ) - RADIANS(" . $input['city_longitude']  . ") )
          + SIN( RADIANS(" . $input['city_latitude']  . ") )
          * SIN( RADIANS( a.latitude  ) )
            ) )";
                        }
                    }
                }*/
            }
            if ($distanceSQL == '') {
                if (isset($input['type']) && $input['type'] == 'tech_location') {
                    if (isset($input['tech_location']) && $input['tech_location'] > 0) {
                        $techPersonID = (int) $input['tech_location'];
                        if (isset($techLocations[$techPersonID]) && $techLocations[$techPersonID]['latitude'] != '' && $techLocations[$techPersonID]['longitude'] != '') {
                            $latitude = $techLocations[$techPersonID]['latitude'];
                            $longitude = $techLocations[$techPersonID]['longitude'];

                            if ($isGoingHome) {
                                if (isset($techHomeLocations[$techPersonID]) && $techHomeLocations[$techPersonID]['latitude'] != '' && $techHomeLocations[$techPersonID]['longitude'] != '') {
                                    $techLatitude = $techHomeLocations[$techPersonID]['latitude'];
                                    $techLongitude = $techHomeLocations[$techPersonID]['longitude'];

                                    $distanceSQL = "(3959  * ACOS( 
                                        COS( RADIANS(".$latitude.") ) 
                                      * COS( RADIANS( a.latitude ) ) 
                                      * COS( RADIANS( a.longitude ) - RADIANS(".$longitude.") ) 
                                      + SIN( RADIANS(".$latitude.") ) 
                                      * SIN( RADIANS( a.latitude  ) )
                                        ) ) + (3959  * ACOS( 
                                        COS( RADIANS(a.latitude) ) 
                                      * COS( RADIANS( ".$techLatitude.") ) 
                                      * COS( RADIANS( ".$techLongitude." ) - RADIANS(a.longitude) ) 
                                      + SIN( RADIANS(a.latitude) ) 
                                      * SIN( RADIANS( ".$techLatitude."  ) )
                                        ) ) ";
                                }
                            }

                            if (empty($distanceSQL)) {
                                if (!empty($latitude) && !empty($longitude)) {
                                    $distanceSQL = "(3959  * ACOS( 
            COS( RADIANS(".$latitude.") ) 
          * COS( RADIANS( a.latitude ) ) 
          * COS( RADIANS( a.longitude ) - RADIANS(".$longitude.") ) 
          + SIN( RADIANS(".$latitude.") ) 
          * SIN( RADIANS( a.latitude  ) )
            ) )";
                                }
                            }
                        }
                    }
                }
            }

            if ($distanceSQL == '') {
                $address = Address::find($shopAddressID);

                $distanceSQL = "(3959  * ACOS( 
                COS( RADIANS(".$address->getLatitude().") ) 
              * COS( RADIANS( a.latitude ) ) 
              * COS( RADIANS( a.longitude ) - RADIANS(".$address->getLongitude().") ) 
              + SIN( RADIANS(".$address->getLatitude().") ) 
              * SIN( RADIANS( a.latitude  ) )
                ) )";
            }
            // declaration of statuses ID's
            $woCompletedStatusTypeID = getTypeIdByKey('wo_status.completed');
            $woCancelledStatusTypeID = getTypeIdByKey('wo_status.canceled');
            $woNewStatusTypeID = getTypeIdByKey('wo_status.new');
            $woPickedUpStatusTypeID = getTypeIdByKey('wo_status.picked_up');
            $techCompletedStatusTypeID = getTypeIdByKey('wo_vendor_status.completed');
            $techCancelledStatusTypeID = getTypeIdByKey('wo_vendor_status.canceled');
            $companyVendorTypeID = getTypeIdByKey('company.vendor');


            $select = 'SELECT work_order_id, received_date, work_order_number, t1.type_value AS wo_status, work_order.description, t2.type_value AS trade, t2.color AS trade_color, 
 t3.type_value AS parts_status, t4.type_value AS quote_status, 
  expected_completion_date AS ecd,
  a.latitude, a.longitude, 
'.$distanceSQL.'   AS distance
                 FROM work_order 
                  LEFT JOIN `type` t1 ON t1.type_id = work_order.wo_status_type_id 
                   LEFT JOIN `type` t2 ON t2.type_id = work_order.trade_type_id
                   LEFT JOIN `type` t3 ON t3.type_id = work_order.parts_status_type_id 
             LEFT JOIN `type` t4 ON t4.type_id = work_order.quote_status_type_id 
             LEFT JOIN address a ON a.address_id = work_order.`shop_address_id` 
             ';
            //  wo_status_type_id not in (' . $woCompletedStatusTypeID . ', ' . $woCancelledStatusTypeID . ') AND a.`latitude` <> ""  - removed - moved to $showOpenWO
            $whereArray = [];

            $whereArray[] = " a.`latitude` <> '' ";

            if ($workOrderID > 0) {
                $whereArray[] = " work_order.work_order_id <> ".$workOrderID." ";
            }

            if ($distance > 0) {
                $whereArray[] = " ".$distanceSQL." <= $distance ";
            }

            // work order filters
            // "show unassigned"
            if ($showUnassignedWO) {
                $whereArray[] = "work_order.work_order_id NOT IN (
                SELECT work_order_id FROM link_person_wo 
LEFT JOIN person ON link_person_wo.person_id = person.person_id
WHERE (person.kind = 'person' OR (person.kind = 'company' AND person.`type_id` = ".$companyVendorTypeID."))

and link_person_wo.is_disabled = 0 and link_person_wo.`status_type_id` NOT IN (".$techCompletedStatusTypeID.", ".$techCancelledStatusTypeID.") ) and work_order.wo_status_type_id in (".$woNewStatusTypeID.", ".$woPickedUpStatusTypeID.") ";
            }

            // "show open"
            if ($showOpenWO) {
                $whereArray[] = " wo_status_type_id not in (".$woCompletedStatusTypeID.", ".$woCancelledStatusTypeID.")  ";
            }

            // "show unfinished"
            if ($showUnfinishedWO) {
                $whereArray[] = "work_order.work_order_id in (SELECT work_order_id FROM link_person_wo WHERE ".
                    ($workOrderID > 0 ? "work_order_id <> {$workOrderID} and " : '').
                    // ( $techPersonID > 0 ? "link_person_wo.person_id =  {$techPersonID} and ": '').
                    " link_person_wo.`status_type_id` NOT IN (".$techCompletedStatusTypeID.", ".$techCancelledStatusTypeID.")) ";
            }

            if ($showWithActiveTechAssigned == 0) {
                $whereArray[] = " work_order.work_order_id not in (SELECT work_order_id FROM link_person_wo 
LEFT JOIN person ON link_person_wo.person_id = person.person_id
WHERE (person.kind = 'person' OR (person.kind = 'company' AND person.`type_id` = ".$companyVendorTypeID."))  AND link_person_wo.`status_type_id` NOT IN (".$techCompletedStatusTypeID.", ".$techCancelledStatusTypeID.")) ";
            }


            if (count($whereArray) > 0) {
                $select .= " WHERE ".implode(' AND ', $whereArray);
            }

            if (!empty($input['sort'])) {
                $order = 'asc';
                if (stripos($input['sort'], '-') === 0 && stripos($input['sort'], '-') !== false) {
                    $order = 'desc';
                }
                $orderTerm = str_replace('-', '', $input['sort']);
                $orderBy = "ORDER BY ".$orderTerm." ".$order;
            } else {
                $orderBy = "ORDER BY ".$distanceSQL." ASC ";
            }

            $list = DB::select(DB::raw("$select $orderBy LIMIT $limit"));
            $list2 = [];
            foreach ($list as $k => $l) {
                $hsl = HTMLToRGBToHSL($l->trade_color);
                $l->trade_font_color = ($hsl->lightness > 118 ? '#000000' : '#FFFFFF');
                $l->distance = number_format($l->distance, '4', '.', '');
                $l->assigned_to = [];
                $techs = DB::select(
                    DB::raw(
                        "SELECT person_name(person_id) as tech_name, (t1.type_value) as status from link_person_wo
 LEFT JOIN type t1 on t1.type_id = status_type_id
 where work_order_id = ".$l->work_order_id." and status_type_id not in (".$techCompletedStatusTypeID.", ".$techCancelledStatusTypeID.")"
                    )
                );
                foreach ($techs as $i => $t) {
                    $l->assigned_to[] = $t;
                }
                $l->wo_url = $legacyURL.'/work_order/edit?work_order_id='.$l->work_order_id;
                $list2[] = $l;
            }
        } else {
            $list2 = [];
        }

        return ['work_orders' => $list2, 'current_work_order' => $currentWorkOrder, 'request' => $input];
    }

    private function addPersonStatuses(array &$data)
    {
        $personStatuses = [];
        foreach ($data['data'] as $item) {
            if (isset($item['creator_person_id']) && $item['creator_person_id']) {
                $personStatuses[$item['creator_person_id']] = false;
            }
            if (isset($item['assigned_to_person_id']) && $item['assigned_to_person_id']) {
                $personStatuses[$item['assigned_to_person_id']] = false;
            }
        }

        if (count($personStatuses)) {
            $statuses = $this->getRepository('TimeSheet')
                ->checkIsInProgressByPersonIds(array_keys($personStatuses));

            if ($statuses) {
                foreach ($statuses as $status) {
                    $personStatuses[$status->person_id] = true;
                }
            }
        }
        $data['person_status'] = $personStatuses;
    }

    public function getWorkOrderListByDriverKey($driverKey)
    {
        /** @var PersonDataRepository $personDataRepository */
        $personId = app(SlRecordsManager::class)->findPersonId($driverKey);

        $woCanceledStatus = getTypeIdByKey('wo_status.canceled');
        $woCompletedStatus = getTypeIdByKey('wo_status.completed');
        $techStatusCompleted = getTypeIdByKey('tech_status.completed');

        $linkAssignedStatus = getTypeIdByKey('wo_vendor_status.assigned');
        $linkCanceledStatus = getTypeIdByKey('wo_vendor_status.canceled');
        $linkCompletedStatus = getTypeIdByKey('wo_vendor_status.completed');

        $workOrders = $this->model
            ->select([
                'work_order.work_order_id',
                'work_order.work_order_number',
                'link_person_wo.scheduled_date',
                DB::raw('t(link_person_wo.tech_status_type_id) as status'),
                'link_person_wo.tech_status_date',
            ])
            ->join('link_person_wo', function ($join) use ($personId) {
                $join
                    ->on('work_order.work_order_id', '=', 'link_person_wo.work_order_id')
                    ->on('link_person_wo.person_id', '=', DB::raw($personId));
            })
            ->whereNotIn('work_order.wo_status_type_id', [$woCanceledStatus, $woCompletedStatus])
            ->whereNotIn('link_person_wo.tech_status_type_id', [$techStatusCompleted])
            ->whereNotIn(
                'link_person_wo.status_type_id',
                [$linkAssignedStatus, $linkCanceledStatus, $linkCompletedStatus]
            )
            ->where('link_person_wo.is_disabled', 0)
//            ->where(DB::raw('date(link_person_wo.scheduled_date)'), '>=', Carbon::now()->subDay(14)->format('Y-m-d'))
            ->orderBy('scheduled_date')
            ->orderBy('work_order.work_order_number')
            ->get();
        
        $workOrderNumbers = array_column($workOrders->toArray(), 'work_order_number');
        $workOrderNumbers = $this->filterByActiveWorkOrders($workOrderNumbers, $driverKey);
        
        return $workOrders->filter(function ($item) use ($workOrderNumbers) {
            /** @var WorkOrder $item */
            return in_array($item->getWorkOrderNumber(), $workOrderNumbers);
        })->values();
    }

    /**
     * @param $id
     * @param $request
     *
     * @return bool
     */
    public function updateComment($id, $request)
    {
        if (isset($request['comment'])) {
            /** @var WorkOrder $object */
            $object = $this->getModel()->find($id);

            if ($object === null) {
                throw with(new ModelNotFoundException())
                    ->setModel(get_called_class());
            }
            $object->comment = $request['comment'];
            return $object->update();
        } else {
            return 0;
        }
    }

    /**
     * Function for getting linked articles to WorkOrder
     *
     * @return array Array with basic articles data
     * @var int $id WorkOrder identyficator
     *
     */
    public function getLinkedArticles($id)
    {
        $workOrder = new WorkOrder();
        $workOrder = $workOrder->with('articles')->find($id);

        $parsedArticles = [];
        foreach ($workOrder->articles as $article) {
            $person = new Person();
            $person = $person->find($article->pivot->creator_person_id);

            $newArticle = [
                'id'           => $article->id,
                'name'         => $article->name,
                'created_at'   => $article->pivot->created_date,
                'creator_name' => $person->custom_1.' '.$person->custom_3
            ];

            $parsedArticles[] = $newArticle;
        }
        return $parsedArticles;
    }

    /**
     * Function for linking and unlinking article into work order
     *
     * @return boolan Status
     * @var int $articleId Identificator of article
     *
     * @var int $workOrderId Identificator of work order
     */
    public function linkArticle($workOrderId, $articleId)
    {
        $workOrder = new WorkOrder();
        $workOrder = $workOrder->with('articles')->find($workOrderId);

        if ($workOrder->articles()->wherePivot('article_id', $articleId)->exists()) {
            $workOrder->articles()->detach($articleId);
        } else {
            $workOrder->articles()->attach($articleId, ['creator_person_id' => Auth::user()->getPersonId()]);
        }

        return true;
    }

    /**
     * Merge work orders with Each Other
     *
     * @param  WorkOrder  $fromWo
     * @param  WorkOrder  $toWo
     *
     * @return string
     */
    public function merge(WorkOrder $fromWo, WorkOrder $toWo)
    {
        WorkOrderExtension::where('work_order_id', $fromWo->id)
            ->update(['work_order_id' => $toWo->id]);

        LinkArticleWo::where('work_order_id', $fromWo->id)
            ->update(['work_order_id' => $toWo->id]);

        LinkLabtechWo::where('work_order_id', $fromWo->id)
            ->update(['work_order_id' => $toWo->id]);

        Invoice::where('work_order_id', $fromWo->id)
            ->update(['work_order_id' => $toWo->id]);

        Email::where('work_order_id', $fromWo->id)
            ->update(['work_order_id' => $toWo->id]);

        ArticleProgress::where('link_record_id', $fromWo->id)
            ->where('link_tablename', 'work_order')
            ->update(['link_record_id' => $toWo->id]);

        Activity::where('table_name', 'work_order')
            ->where('table_id', $fromWo->id)
            ->update(['table_id' => $toWo->id]);

        CalendarEvent::where('record_id', $fromWo->id)
            ->where('tablename', 'work_order')
            ->update(['record_id' => $toWo->id]);

        DataExchange::where('record_id', $fromWo->id)
            ->where('table_name', 'work_order')
            ->update(['record_id' => $toWo->id]);

        File::where('table_id', $fromWo->id)
            ->where('table_name', 'work_order')
            ->update(['table_id' => $toWo->id]);

        TimeSheet::where('table_id', $fromWo->id)
            ->where('table_name', 'work_order')
            ->update(['table_id' => $toWo->id]);

        LinkAssetWo::where('work_order_id', $fromWo->id)
            ->update(['work_order_id' => $toWo->id]);

        LinkPersonWo::where('work_order_id', $fromWo->id)
            ->update(['work_order_id' => $toWo->id]);

        LinkAssetPersonWo::where('work_order_id', $fromWo->id)
            ->update(['work_order_id' => $toWo->id]);

        Quote::where('table_id', $fromWo->id)
            ->where('table_name', 'work_order')
            ->update(['table_id' => $toWo->id]);

        History::where('record_id', $fromWo->id)
            ->where('tablename', 'work_order')
            ->update(['record_id' => $toWo->id]);

        History::where('related_record_id', $fromWo->id)
            ->where('related_tablename', 'work_order')
            ->update(['related_record_id' => $toWo->id]);

        $mergeHistory = new MergeHistory();
        $mergeHistory->table_name = 'work_order';
        $mergeHistory->object_id = $fromWo->id;
        $mergeHistory->merged_object_id = $toWo->id;
        $mergeHistory->save();

        $activityFrom = new Activity();
        $activityFrom->table_name = "work_order";
        $activityFrom->table_id = $fromWo->id;
        $activityFrom->description = "Work Order has been merged into WO ID: ".$toWo->work_order_number;
        $activityFrom->subject = "WO Merge";
        $activityFrom->save();

        $activityTo = new Activity();
        $activityTo->table_name = "work_order";
        $activityTo->table_id = $toWo->id;
        $activityTo->description = "Work Order ".$fromWo->work_order_number." <".$fromWo->id."> has been merged into this WO.";
        $activityTo->subject = "WO Merge";
        $activityTo->save();

        $fromWo->wo_status_type_id = Type::where('type_key', 'wo_status.canceled')->first()->id;
        $fromWo->save();

        if (!empty($fromWo->comment)) {
            if (!empty($toWo->comment)) {
                $toWo->comment = $fromWo->comment . "\n\n" . $toWo->comment;
            } else {
                $toWo->comment = $fromWo->comment;
            }
            
            $toWo->save();
        }
        
        if (config('app.crm_user') == 'fs') {
            $this->mergeLinkPersonWos($toWo->work_order_id);
        }
        return "success";
    }

    /**
     * Get and run Merge for linkPersonWo's assigned to Work Order
     *
     * @param $workOrderID
     */
    public function mergeLinkPersonWos($workOrderID)
    {
        /**
         * @var LinkPersonWoRepository
         */
        $lpwoRepository = $this->getRepository('LinkPersonWo', 'WorkOrder');
        // get necessary types ( group by person_id, status not cancelled or completed)
        $lpwoCancelledTypeID = getTypeIdByKey('wo_vendor_status.canceled');
        $lpwoCompletedTypeID = getTypeIdByKey('wo_vendor_status.completed');
        // search for link person wos
        $sql = "SELECT link_person_wo_id, work_order_id, person_id, link_person_wo.`created_date`  FROM link_person_wo WHERE
 link_person_wo.`work_order_id` = {$workOrderID} AND status_type_id NOT IN ({$lpwoCancelledTypeID}, {$lpwoCompletedTypeID})
 AND (
 SELECT COUNT(lpwo.link_person_wo_id) FROM link_person_wo lpwo WHERE
 lpwo.`work_order_id` = {$workOrderID} AND lpwo.status_type_id NOT IN ({$lpwoCancelledTypeID}, {$lpwoCompletedTypeID})
 AND lpwo.person_id = link_person_wo.`person_id`
 ) > 1 GROUP BY person_id ORDER BY link_person_wo.`created_date`";
        $lpwos = DB::select(DB::raw($sql));
        if (!empty($lpwos)) {
            foreach ($lpwos as $row) {
                $toLinkPersonWoID = $row->link_person_wo_id;
                $personID = $row->person_id;
                // search for other link person wos (same wo, same person_id, status not cancelled or completed) under that wo
                $sql2 = " SELECT link_person_wo_id, work_order_id, person_id, `created_date`  FROM link_person_wo lpwo WHERE
lpwo.`work_order_id` = {$workOrderID} AND lpwo.status_type_id NOT IN ({$lpwoCancelledTypeID}, {$lpwoCompletedTypeID})
AND lpwo.person_id = $personID and link_person_wo_id <> {$toLinkPersonWoID} ;";
                $lpwosInner = DB::select(DB::raw($sql2));
                if (!empty($lpwosInner)) {
                    foreach ($lpwosInner as $row2) {
                        $row2 = (array) $row2;
                        $fromLinkPersonWoID = $row2['link_person_wo_id'];
                        // Proceed with merge
                        $lpwoRepository->merge($fromLinkPersonWoID, $toLinkPersonWoID);
                    }
                }
            }
        }
    }

    public function getUserActivityDataByWorkOrderIds($workOrderIds, $page = null, $limit = null)
    {
        $calendarEvents = CalendarEvent::select([
            'calendar_event.tablename as table_name',
            'calendar_event.record_id as table_id',
            'calendar_event.calendar_event_id',
            'calendar_event.description',
            DB::raw('person_name(creator_person_id) as person_name'),
            DB::raw('"calendar_event" as activity_type'),
            'calendar_event.is_completed',
            DB::raw('NULL as subject'),
            'calendar_event.type_id',
            'calendar_event.time_start as activity_time',
            'calendar_event.creator_person_id',
            DB::raw('person_name(calendar_event.assigned_to) as assigned_to_name')

        ])
            ->where('tablename', '=', 'work_order')
            ->whereIn('record_id', $workOrderIds)
            ->orderByDesc('activity_time');

        $activity = Activity::select([
            'activity.table_name',
            'activity.table_id',
            'activity.activity_id',
            'activity.description',
            DB::raw('person_name(creator_person_id) as person_name'),
            DB::raw('"activity" as activity_type'),
            DB::raw('NULL as is_completed'),
            'activity.subject',
            'activity.type_id',
            'activity.created_date as activity_time',
            'activity.creator_person_id',
            DB::raw('NULL as assigned_to_name'),
        ])
            ->where('table_name', 'work_order')
            ->whereIn('table_id', $workOrderIds)
            ->orderByDesc('activity_time')
            ->union($calendarEvents);

        if (is_null($limit)) {
            $limit = min(max(isset($input['limit']) ? (int) $input['limit'] : 25, 5), 100);
        }

        $data = $activity
            ->orderByDesc('activity_time')
            ->paginate($limit);

        if (is_null($page)) {
            $page = max(isset($input['page']) ? (int) $input['page'] - 1 : 0, 0);
        }

        foreach ($data->items() as $index => $item) {
            $item->index = $index + ($page * $limit) + 1;
        }

        return $data;
    }

    public function getUserActivityData($companyPersonID)
    {
        $input = $this->getInput();

        $workOrderIds = WorkOrder::where('company_person_id', '=', $companyPersonID)->pluck('work_order_id')->all();

        return $this->getUserActivityDataByWorkOrderIds($workOrderIds);
    }

    public function getFilters()
    {
        return config('app.work_order_filters');
    }

    public function getColumns()
    {
        return config('app.work_order_columns');
    }

    private function setLockedTo($record, &$output)
    {
        $lastModified = $output['item']['updated_at'] ?? $output['item']['modified_date'] ?? null;
        
        if (!empty($output['item']['locked_id']) && $lastModified) {
            $lockLimit = $this->app->config->get('system_settings.workorder_lock_limit_minutes', 15);
            $lockedTo = Carbon::parse($lastModified, 'utc')
                ->addMinutes($lockLimit)
                ->format('Y-m-d H:i:s');

            if ($lockedTo < now('utc')->format('Y-m-d H:i:s')) {
                $output['item']['locked_to'] = null;
            } else {
                $output['item']['locked_to'] = $lockedTo;
            }
        } else {
            $output['item']['locked_to'] = null;
        }

        if (!$output['item']['locked_to']) {
            $output['item']['locked_id'] = null;
            $output['item']['locked_id_value'] = null;

            if ($record->locked_id) {
                $record->locked_id = 0;
                $record->save();
            }
        }
    }

    private function filterByActiveWorkOrders(array $workOrderNumbers, $driverKey)
    {
        if (!$workOrderNumbers) {
            return [];
        }
        
        return app(DatabaseManager::class)
            ->getDynamicsDb()
            ->table('smServFault')
            ->join('smServCall', 'smServCall.ServiceCallID', '=', 'smServFault.ServiceCallId')
            ->where('smServFault.Empid', $driverKey)
            ->whereIn('smServCall.ServiceCallID', $workOrderNumbers)
            ->whereNotIn('smServCall.CallStatus', ['CLOSED', 'CANCELLED'])
//            ->whereNotIn('smServFault.TaskStatus', ['U1', 'U2'])
            ->where(function ($q) {
                $q
                    ->where('smServFault.PromDate', '>', '2000-01-01')
                    ->orWhere('smServCall.ServiceCallDateProm', '>', '2000-01-01');
            })
            ->pluck('smServCall.ServiceCallID')
            ->all();
    }
    
    public function getByAddressIssue(AddressIssue $siteIssue)
    {
        $customerSettingsKey = 'address_issues.send_email_to_customer';

        return $this->model
            ->select(['work_order.*'])
            ->join('address_issues', 'address_issues.work_order_id', '=', 'work_order.work_order_id')
            ->join('customer_settings', 'customer_settings.company_person_id', '=', 'work_order.company_person_id')
            ->join('customer_settings_items', function ($j) use ($customerSettingsKey) {
                $j
                    ->on('customer_settings_items.customer_settings_id', '=', 'customer_settings.customer_settings_id')
                    ->where('customer_settings_items.key', '=', $customerSettingsKey);
            })
            ->where('work_order.work_order_id', $siteIssue->work_order_id)
            ->where('customer_settings_items.value', 1)
            ->first();
    }

    public function getCustomerSettingsIdsByWorkOrderIds($workOrderIds)
    {
        return $this->model
            ->join('customer_settings', 'customer_settings.company_person_id', '=', 'work_order.company_person_id')
            ->whereIn('work_order.work_order_id', $workOrderIds)
            ->pluck('customer_settings.customer_settings_id', 'work_order.work_order_id')
            ->all();
    }

    /**
     * @param  array  $addressIds
     *
     * @return array
     */
    public function getServicedAtForAddresses(array $addressIds)
    {
        if (!$addressIds) {
            return [];
        }

        $groupedServices = [];
        $services = $this->model
            ->select([
                'work_order.shop_address_id',
                'link_person_wo.completed_date'
            ])
            ->join('link_person_wo', function ($join) {
                $join
                    ->on('link_person_wo.work_order_id', '=', 'work_order.work_order_id')
                    ->whereNotNull('link_person_wo.completed_date');
            })
            ->whereIn('work_order.shop_address_id', $addressIds)
            ->get();

        foreach ($services as $service) {
            if (!isset($groupedServices[$service->shop_address_id])) {
                $groupedServices[$service->shop_address_id] = [];
            }

            $groupedServices[$service->shop_address_id][] = $service->completed_date;
        }

        return $groupedServices;
    }
}
