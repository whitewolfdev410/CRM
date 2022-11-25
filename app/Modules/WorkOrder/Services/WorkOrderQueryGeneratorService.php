<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\Type\Repositories\TypeRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;

/**
 * Class WorkOrderQueryGeneratorService
 *
 * Generate main query - join table with other tables and choose columns that
 * should be chosen from result set
 *
 * @package App\Modules\WorkOrder\Services
 */
class WorkOrderQueryGeneratorService
{
    /**
     * Input data
     *
     * @var array
     */
    private $input;

    /**
     * Type repository
     *
     * @var TypeRepository
     */
    private $type;

    /**
     * Initialize input and repository
     *
     * @param array $input
     * @param TypeRepository $type
     */
    public function __construct(array $input, TypeRepository $type)
    {
        $this->input = $input;
        $this->type = $type;
    }

    protected $availableColumns = [
        "bill_total"                =>  "(select sum(bill_amount) from link_person_wo where link_person_wo.work_order_id=work_order.work_order_id limit 1)",
        "address_id"                =>  "adr.address_id",
        "city"                      =>  "adr.city",
        "state"                     =>  "adr.state",
        "bill_status_type_id"       =>  "bill_status_type_id",
        "cancel_reason_type"        =>  "cancel_type.type_value",
        "category"                  =>  "category",
        "hours_until_ecd"           => "datediff(ifnull((select work_order_extension.extended_date
            from work_order_extension where
            work_order_extension.work_order_id = work_order.work_order_id
            and work_order_extension.extended_date >
            work_order.expected_completion_date order by
            work_order_extension.extended_date desc limit 1),
            expected_completion_date),now())",
        "description"               =>  "description",
        "expected_completion_date"  => "expected_completion_date",
        "fin_loc"                   =>  "fin_loc",
        "invoice_status_type_id"    =>  "invoice_status_type_id",
        "not_to_exceed"             =>  "not_to_exceed",
        "priority"                  =>  "work_order.priority",
        "crm_priority_color"        =>  "priority_type.color",
        "crm_priority"              =>  "priority_type.type_value",
        "received_date"             =>  "received_date",
        "trade"                     =>  "trade",
        "crm_trade"                 =>  "trade_type.type_value",
        "via_type_id"               =>  "via_type_id",
        "wo_status_type_id"         =>  "wo_status_type_id",
        "created_date"              =>  "work_order.created_date",
        "id"                        =>  "work_order.work_order_id",
        "work_order_number"         =>  "work_order_number",
        "extended_due_date"         =>  "(select count(calendar_event_id) from calendar_event c
            where c.tablename='work_order' and
            c.record_id = work_order.work_order_id and c.is_completed=0
            and c.type_id=590 limit 1)
            as hot_tasks_count, (select distinct woe.created_date from work_order_extension woe
            where woe.work_order_id=work_order.work_order_id
            order by woe.created_date desc limit 1)
            as extended_date, (select distinct woe.extended_date from work_order_extension woe
            where woe.work_order_id=work_order.work_order_id
            order by woe.created_date desc limit 1)",
        "actual_completion_date"    =>  "actual_completion_date",
        "company_person_id"         =>  "company_person_id",
        "completion_code"           =>  "completion_code",
        "costs"                     =>  "costs",
        "extended_why"              =>  "extended_why",
        "invoice_amount"            =>  "invoice_amount",
        "invoice_id"                =>  "invoice_id",
        "client"                    =>  " person_name(work_order.company_person_id)",
        "shop"                      =>  "shop",
        "tracking_number"           =>  "tracking_number",
        "quote_status_type_id"      =>   "IFNULL(work_order.quote_status_type_id, 0)"
    ];

    /**
     * Generate queries, column list and view type
     *
     * @param \Illuminate\Database\Query\Builder $model
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function generate($model, $customColumns)
    {
        if ($customColumns[0] == '*') {
            $columns = $this->getCommonColumns();
            list($model, $columns) = $this->othersQuery($model, $columns);

            if ($this->isNewOrReadyToInvoice()) {
                list($model, $columns) = $this->newOrReadyToInvoiceQuery(
                    $model,
                    $columns
                );
            } elseif ($this->isPickedUp()) {
                list($model, $columns) = $this->pickedUpQuery($model, $columns);
            } elseif ($this->isAssignedIssuedOrCompleted()) {
                list($model, $columns)
                    = $this->assignedIssuedOrCompletedQuery($model, $columns);
            } else {
                list($model, $columns) = $this->othersQuery($model, $columns);
                list($model, $columns) = $this->setQuoteStatusColumn($model, $columns);
            }
        } else {
            $columns = [];
            foreach ($customColumns as $column) {
                if (Arr::has($this->availableColumns, $column)) {
                    if ($column != "id") {
                        $columns[] = $this->availableColumns[$column].' as '.$column;
                    } else {
                        //fix table error without this
                        $columns[] = $this->availableColumns[$column];
                    }
                }
            }
            list($model, $columns) = [$model, $columns];
        }

        return [$model, $columns];
    }

    /**
     * Get common column list - those columns are used for each query
     *
     * @return array
     */
    protected function getCommonColumns()
    {
        // other columns for BFC
        $crmUser = config('app.crm_user', '');
        if ($crmUser == 'bfc') {
            return [
                '(select sum(bill_amount) from link_person_wo where
                 link_person_wo.work_order_id=work_order.work_order_id limit 1) as bill_total',
                'adr.address_id',
                'adr.city',
                'adr.state',
                'adr.zip_code',
                'adr.address_1',
                'bill_status_type_id',
                'cancel_type.type_value as cancel_reason_type',
                'category',
                'datediff(ifnull((select work_order_extension.extended_date
                    from work_order_extension where
                    work_order_extension.work_order_id = work_order.work_order_id
                    and work_order_extension.extended_date >
                    work_order.expected_completion_date order by
                    work_order_extension.extended_date desc limit 1),
                    expected_completion_date),now()
                ) as hours_until_ecd',
                'description',
                'expected_completion_date',
                'fin_loc',
                'invoice_status_type_id',
                'not_to_exceed',
                'work_order.priority as priority',
                'priority_type.color as crm_priority_color',
                'priority_type.type_value as crm_priority',
                'received_date',
                'trade',
                'trade_type.type_value as crm_trade',
                'via_type_id',
                'wo_status_type_id',
                'work_order.created_date',
                'work_order.work_order_id',
                'work_order_number',
                'work_order.scheduled_date',
                '(select type.type_value from type where type_id = work_order.wo_type_id) as wo_type_id_value'
            ];
        } else {
            return [
                '(select sum(bill_amount) from link_person_wo where
                 link_person_wo.work_order_id=work_order.work_order_id limit 1)
                 as bill_total',
                'adr.address_id',
                'adr.city',
                'adr.state',
                'bill_status_type_id',
                'cancel_type.type_value as cancel_reason_type',
                'category',
                'datediff(ifnull((select work_order_extension.extended_date
                from work_order_extension where
                work_order_extension.work_order_id = work_order.work_order_id
                and work_order_extension.extended_date >
                work_order.expected_completion_date order by
                work_order_extension.extended_date desc limit 1),
                expected_completion_date),now())
                as hours_until_ecd',
                'description',
                'expected_completion_date',
                'fin_loc',
                'invoice_status_type_id',
                'not_to_exceed',
                'work_order.priority as priority',
                'priority_type.color as crm_priority_color',
                'priority_type.type_value as crm_priority',
                'received_date',
                'trade',
                'trade_type.type_value as crm_trade',
                'via_type_id',
                'wo_status_type_id',
                'work_order.created_date',
                'work_order.work_order_id',
                'work_order_number',
            ];
        }
    }

    /**
     * Add joins to query
     *
     * @param Builder $model
     *
     * @return Builder
     */
    public function addJoins($model)
    {
        $model = $this->addAddressJoin($model);
        $model = $this->addTradeTypeJoin($model);
        $model = $this->addCancelTypeJoin($model);
        $model = $this->addPriorityTypeJoin($model);

        return $model;
    }

    /**
     * Add address join to query
     *
     * @param Builder $model
     *
     * @return Builder
     */
    public function addAddressJoin($model)
    {
        return $model->leftJoin(
            'address AS adr',
            'work_order.shop_address_id',
            '=',
            'adr.address_id'
        );
    }

    /**
     * Add trade_type join to query
     *
     * @param Builder $model
     *
     * @return Builder
     */
    public function addTradeTypeJoin($model)
    {
        return $model->leftJoin(
            'type AS trade_type',
            'trade_type.type_id',
            '=',
            'work_order.trade_type_id'
        );
    }

    /**
     * Add cancel_type join to query
     *
     * @param Builder $model
     *
     * @return Builder
     */
    public function addCancelTypeJoin($model)
    {
        return $model->leftJoin(
            'type AS cancel_type',
            'cancel_type.type_id',
            '=',
            'work_order.cancel_reason_type_id'
        );
    }

    /**
     * Add priority_type join to query
     *
     * @param Builder $model
     *
     * @return Builder
     */
    public function addPriorityTypeJoin($model)
    {
        return $model->leftJoin(
            'type AS priority_type',
            'priority_type.type_id',
            '=',
            'work_order.crm_priority_type_id'
        );
    }

    /**
     * Verify whether new or ready to invoice query should be used
     *
     * @return bool
     */
    protected function isNewOrReadyToInvoice()
    {
        return ((isset($this->input['wo_status_type_id'])
                && $this->input['wo_status_type_id']
                == $this->type->getIdByKey('wo_status.new'))
            || (isset($this->input['ready_to_invoice'])
                && $this->input['ready_to_invoice'] == 1));
    }

    /**
     * Verify whether picked up query should be used
     *
     * @return bool
     */
    protected function isPickedUp()
    {
        return (isset($this->input['wo_status_type_id'])
            && $this->input['wo_status_type_id']
            == $this->type->getIdByKey('wo_status.picked_up'));
    }

    /**
     * Verify whether assigned, issued or completed query should be used
     *
     * @return bool
     */
    protected function isAssignedIssuedOrCompleted()
    {
        return (isset($this->input['wo_status_type_id'])
            && in_array(
                isset($this->input['wo_status_type_id']),
                [
                    $this->type->getIdByKey('wo_status.assigned_in_crm'),
                    $this->type->getIdByKey('wo_status.issued_to_vendor_tech'),
                    $this->type->getIdByKey('wo_status.completed'),
                ]
            ));
    }

    /**
     * Generate new or ready to invoice query and column list
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param array $commonColumns
     *
     * @return array
     */
    protected function newOrReadyToInvoiceQuery($model, array $commonColumns)
    {
        $columns = [
            'actual_completion_date',
            'invoice_amount',
            'person_name(adr.person_id) as client',
        ];
        $columns = array_merge($commonColumns, $columns);

        return [$model, $columns];
    }

    /**
     * Generate picked up query and column list
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param array $commonColumns
     *
     * @return array
     */
    protected function pickedUpQuery($model, array $commonColumns)
    {
        $columns = [
            "work_order.pickup_date",
            'person_name(adr.person_id) as client',
            'person_name(work_order.pickup_id) as pickup_person',
        ];
        $columns = array_merge($commonColumns, $columns);

        return [$model, $columns];
    }

    /**
     * Generate assigned, issued or completed query and column list
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param array $commonColumns
     *
     * @return array
     */
    protected function assignedIssuedOrCompletedQuery(
        $model,
        array $commonColumns
    ) {
        $columns = [
            "(select count(calendar_event_id) from calendar_event c
            where c.tablename='work_order'
            and c.record_id = work_order.work_order_id
            and c.is_completed=0 and c.type_id=
            {$this->type->getIdByKey('task.hot')} limit 1)
             as hot_tasks_count",
            "(select created_date from activity
            where table_name='work_order'
            and table_id=work_order.work_order_id
            order by created_date desc limit 1)
            as last_note_date",
            "work_order.completed_date",
            'costs',
            'person_name(adr.person_id) as client',
        ];
        $columns = array_merge($commonColumns, $columns);

        return [$model, $columns];
    }

    /**
     * Generate others query and column list (used when any of above queries
     * are not selected)
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param array $commonColumns
     *
     * @return array
     */
    protected function othersQuery($model, $commonColumns)
    {
        $columns = [
            "(select count(calendar_event_id) from calendar_event c
            where c.tablename='work_order' and
            c.record_id = work_order.work_order_id and c.is_completed=0
            and c.type_id={$this->type->getIdByKey('task.hot')} limit 1)
            as hot_tasks_count",
            '(select distinct woe.created_date from work_order_extension woe
            where woe.work_order_id=work_order.work_order_id
            order by woe.created_date desc limit 1)
            as extended_date',
            '(select distinct woe.extended_date from work_order_extension woe
            where woe.work_order_id=work_order.work_order_id
            order by woe.created_date desc limit 1)
            as extended_due_date',
            'actual_completion_date',
            'company_person_id',
            'completion_code',
            'costs',
            'extended_why',
            'invoice_amount',
            'invoice_id',
            'person_name(work_order.company_person_id) as client',
            'shop',
            'tracking_number',
        ];
        $columns = array_merge($commonColumns, $columns);

        return [$model, $columns];
    }

    /**
     * Set quote_status_type_id column
     *
     * @param array $columns
     *
     * @return array $columns
     */
    protected function setQuoteStatusColumn($model, array $columns)
    {
        return [$model,array_merge(
            $columns,
            ['IFNULL(work_order.quote_status_type_id, 0) as quote_status_type_id']
        )
        ];
    }
}
