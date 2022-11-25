<?php

namespace App\Modules\WorkOrder\Services;

use Illuminate\Support\Str;
use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Support\Facades\DB;

/**
 * Class WorkOrderFilterService
 *
 * Add custom condition filters
 *
 * @package App\Modules\WorkOrder\Services
 */
class WorkOrderFilterService
{
    /**
     * Type repository
     *
     * @var TypeRepository
     */
    private $type;

    /**
     * Work order repository
     *
     * @var WorkOrderRepository
     */
    private $workOrder;

    /**
     * Default meta data string to search
     *
     * @var string
     */
    protected $defMetaData;

    /**
     * Initialize repositories and defMetaData
     *
     * @param WorkOrderRepository $workOrder
     * @param TypeRepository $type
     */
    public function __construct(
        WorkOrderRepository $workOrder,
        TypeRepository $type
    ) {
        $this->type = $type;
        $this->workOrder = $workOrder;

        $this->defMetaData = $this->workOrder->getMetaData();
    }

    /**
     * Add custom condition for $fieldName
     *
     * @param string $fieldName
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     * @param array $input
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function addCustomCondition($fieldName, $model, $value, array $input)
    {
        return $this->{Str::camel('add_' . $fieldName
            . '_condition')}($model, $value, $input);
    }

    /**
     * Add custom condition for client_type_id filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addClientTypeIdCondition($model, $value)
    {
        return $model->whereRaw(
            '(SELECT type_id FROM person client WHERE client.person_id = company_person_id LIMIT 1) = ?',
            [$value]
        );
    }

    /**
     * Add custom condition for person_type_id filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addPersonTypeIdCondition($model, $value)
    {
        return $model->whereRaw(
            ' work_order_id IN (SELECT work_order_id FROM link_person_wo lpwo LEFT JOIN person pp ON lpwo.person_id = pp.person_id WHERE pp.type_id = ?)',
            [$value]
        );
    }

    /**
     * Add custom condition for assigned_to_tech filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addAssignedToTechCondition($model, $value)
    {
        return $model->whereRaw(
            'work_order_id in (select work_order_id from link_person_wo where person_id = ?)',
            [$value]
        );
    }

    /**
     * Add custom condition for assigned_to_vendor filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addAssignedToVendorCondition($model, $value)
    {
        return $model->whereRaw(
            'work_order_id in (select work_order_id from link_person_wo where person_id = ?)',
            [$value]
        );
    }

    /**
     * Add custom condition for state filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addStateCondition($model, $value)
    {
        return $model->where('adr.state', $value);
    }

    /**
     * Add custom condition for country filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addCountryCondition($model, $value)
    {
        return $model->where('adr.country', $value);
    }

    /**
     * Add custom condition for city filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addCityCondition($model, $value)
    {
        return $model->where('adr.city', $value);
    }

    /**
     * Add custom condition for work_order_number filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addWorkOrderNumberCondition($model, $value)
    {
        return $model->where('work_order_number', 'LIKE', '%' . $value . '%');
    }

    /**
     * Add custom condition for fin_loc filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addFinLocCondition($model, $value)
    {
        return $model->where('fin_loc', 'LIKE', '%' . $value . '%');
    }

    /**
     * Add custom condition for created_date_from filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addCreatedDateFromCondition($model, $value)
    {
        return $this->addDateCondition($model, $value, 'created_date', true);
    }

    /**
     * Add custom condition for created_date_to filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addCreatedDateToCondition($model, $value)
    {
        return $this->addDateCondition($model, $value, 'created_date', false);
    }

    /**
     * Add custom condition for expected_completion_date_from filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addExpectedCompletionDateFromCondition($model, $value)
    {
        return $this->addDateCondition(
            $model,
            $value,
            'expected_completion_date',
            true
        );
    }

    /**
     * Add custom condition for expected_completion_date_to filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addExpectedCompletionDateToCondition($model, $value)
    {
        return $this->addDateCondition(
            $model,
            $value,
            'expected_completion_date',
            false
        );
    }

    /**
     * Add custom condition for actual_completion_date_from filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addActualCompletionDateFromCondition($model, $value)
    {
        return $this->addDateCondition(
            $model,
            $value,
            'actual_completion_date',
            true
        );
    }

    /**
     * Add custom condition for actual_completion_date_to filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addActualCompletionDateToCondition($model, $value)
    {
        return $this->addDateCondition(
            $model,
            $value,
            'actual_completion_date',
            false
        );
    }

    /**
     * Add date condition for $column with $operator based on $from parameter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     * @param string $column
     * @param bool $from Whether to use from (>= - for true) or to (<= - for
     *     false)
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addDateCondition($model, $value, $column, $from = true)
    {
        $operator = '<=';
        if ($from) {
            $operator = '>=';
        }

        return $model->whereRaw("{$column} {$operator} ?", [$value]);
    }

    /**
     * Add custom condition for nte_from filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addNteFromCondition($model, $value)
    {
        return $this->addNteCondition($model, $value, true);
    }

    /**
     * Add custom condition for nte_to filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addNteToCondition($model, $value)
    {
        return $this->addNteCondition($model, $value, false);
    }

    /**
     * Add not_to_exceed condition with $operator based on $from parameter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param  string $value
     * @param bool $from Whether to use from (>= - for true) or to (<= - for
     *     false)
     *
     * @return mixed
     */
    protected function addNteCondition($model, $value, $from = true)
    {
        $operator = '<=';
        if ($from) {
            $operator = '>=';
        }

        return $model->whereRaw(
            '0+not_to_exceed ' . $operator . ' ?',
            [floatval($value)]
        );
    }

    /**
     * Add custom condition for opened filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addOpenedCondition($model, $value)
    {
        if ($value == 1) {
            $status = [
                $this->type->getIdByKey('wo_status.canceled'),
                $this->type->getIdByKey('wo_status.completed'),
            ];

            $model = $model->whereNotIn('wo_status_type_id', $status);
        }

        return $model;
    }

    /**
     * Add custom condition for techs_min_1 filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addTechsMin1Condition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        $ids = implode(
            ', ',
            $this->type->getIdByKey('person.employee', true)
        );

        $model = $model->where('techs_assigned', '>', 0);

        $model
            = $model->join(
                DB::raw('
             (select work_order_id as wo_id,
                     count(link_person_wo_id) as techs_assigned
              from link_person_wo where link_person_wo.person_id in
              (SELECT person_id from person where person.type_id IN ('
                . $ids . '))
              GROUP BY work_order_id) techs_count'),
                'techs_count.wo_id',
                '=',
                'work_order.work_order_id'
            );

        return $model;
    }

    /**
     * Add custom condition for vendors_min_1 filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addVendorsMin1Condition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        $ids = implode(', ', [
            $this->type->getIdByKey('company.vendor'),
            $this->type->getIdByKey('company.supplier'),
        ]);

        $model = $model->where('vendors_assigned', '>', 0);

        $model
            = $model->join(
                DB::raw('
            (select work_order_id as wo_id,
               count(link_person_wo_id) as vendors_assigned
            from link_person_wo where link_person_wo.person_id in
            (SELECT person_id from person where person.type_id IN ('
                . $ids . '))
            GROUP BY work_order_id) vendors_count'),
                'vendors_count.wo_id',
                '=',
                'work_order.work_order_id'
            );

        return $model;
    }

    /**
     * Add custom condition for hot filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     * @param array $input
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addHotCondition($model, $value, array $input)
    {
        if ($value != 1) {
            return $model;
        }
        $sqlHotCompleted = '';

        $hotTaskId = $this->type->getIdByKey('task.hot');

        $params = [$hotTaskId];

        if (isset($input['hot_completed_days'])
            && intval($input['hot_completed_days']) > 0
        ) {
            $sqlHotCompleted
                =
                'OR (c.is_completed=1 AND datediff(now(),c.created_date) <= ?)';
            $params[] = intval($input['hot_completed_days']);
        }

        $model = $model->whereRaw("0 <
          (SELECT count(calendar_event_id) FROM calendar_event c
             WHERE c.type_id=? AND c.tablename='work_order'
             AND c.record_id = work_order.work_order_id "
            . "AND ((c.is_completed=0) {$sqlHotCompleted}))", $params);

        return $model;
    }

    /**
     * Add custom condition for completed_need_invoice filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addCompletedNeedInvoiceCondition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        return $model->where(
            'wo_status_type_id',
            $this->type->getIdByKey('wo_status.completed')
        )
            ->where(
                'invoice_status_type_id',
                $this->type->getIdByKey('wo_billing_status.update_required')
            );
    }

    /**
     * Add custom condition for ready_to_quote filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addReadyToQuoteCondition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        $model
            = $model->whereNotIn('work_order_id', function ($q) {
                /** @var \Illuminate\Database\Query\Builder $q */
                $q->select('quote.table_id')->from('quote')
                ->where('quote.table_name', 'work_order')
                ->whereRaw('quote.table_id = work_order_id');
            })
            ->where('client_status', 'COMPLETED (confirmed)')
            ->whereIn('company_person_id', function ($q) {
                /** @var \Illuminate\Database\Query\Builder $q */
                $q->select('company_person_id')->from('customer_settings')
                    ->where('meta_data', 'LIKE', $this->defMetaData);
            })->where(
                'invoice_status_type_id',
                $this->type->getIdByKey('wo_billing_status.ready_to_invoice')
            );

        return $model;
    }

    /**
     * Add custom condition for quote_needs_approval filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addQuoteNeedsApprovalCondition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }
        $type = $this->type;

        $model = $model->whereIn(
            'work_order_id',
            function ($q) use ($type) {
                /** @var \Illuminate\Database\Query\Builder $q */
                $q->select('quote.table_id')->from('quote')
                        ->where('quote.table_name', 'work_order')
                        ->whereRaw('quote.table_id = work_order_id')
                        ->where(
                            'quote.type_id',
                            $this->type->getIdByKey('quote_status.internal_waiting_quote_approval')
                        );
            }
        )->whereIn(
                'client_status',
                ['COMPLETED (confirmed)', 'COMPLETED (pending confirmation)']
            )
            ->whereIn('company_person_id', function ($q) {
                /** @var \Illuminate\Database\Query\Builder $q */
                $q->select('company_person_id')->from('customer_settings')
                    ->where('meta_data', 'LIKE', $this->defMetaData);
            })->where(
                'invoice_status_type_id',
                $this->type->getIdByKey('wo_billing_status.ready_to_invoice')
            );

        return $model;
    }

    /**
     * Add custom condition for quote_approved_need_invoice filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addQuoteApprovedNeedInvoiceCondition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        $status = [
            $this->type->getIdByKey('quote_status.internal_waiting_quote_approval'),
            $this->type->getIdByKey('quote_status.internal_quote_approved'),
            $this->type->getIdByKey('quote_status.internal_waiting_invoice_approval'),
        ];

        $model = $model->whereIn(
            'work_order_id',
            function ($q) use ($status) {
                $q->select('quote.table_id')->from('quote')
                    ->where('quote.table_name', 'work_order')
                    ->whereRaw('quote.table_id = work_order_id')
                    ->whereIn('quote.type_id', $status);
            }
        )->whereNotIn('work_order_id', function ($q) {
            /** @var \Illuminate\Database\Query\Builder $q */
            $q->select('invoice.table_id')->from('invoice')
                ->where('invoice.table_name', 'work_order')
                ->whereRaw('invoice.table_id = work_order_id');
        })->where('client_status', 'COMPLETED (confirmed)')
            ->whereIn('company_person_id', function ($q) {
                /** @var \Illuminate\Database\Query\Builder $q */
                $q->select('company_person_id')->from('customer_settings')
                    ->where('meta_data', 'LIKE', $this->defMetaData);
            })->where(
                'invoice_status_type_id',
                $this->type->getIdByKey('wo_billing_status.ready_to_invoice')
            );

        return $model;
    }

    /**
     * Add custom condition for invoice_needs_approval filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addInvoiceNeedsApprovalCondition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        $subQuery = DB::table('invoice')->selectRaw('count(invoice_id)')
            ->where('invoice.table_name', 'work_order')
            ->whereRaw('invoice.table_id = work_order.work_order_id')
            ->where(
                'invoice.status_type_id',
                $this->type->getIdByKey('invoice_status.internal_waiting_for_approval')
            )
            ->limit(1);

        return $model->whereRaw(
            '0 < (' . $subQuery->toSql() . ')',
            $subQuery->getBindings()
        );
    }

    /**
     * Add custom condition for invoiced_not_sent filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addInvoicedNotSentCondition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        $subQuery = DB::table('invoice')->selectRaw('count(invoice_id)')
            ->where('invoice.table_name', 'work_order')
            ->whereRaw('invoice.table_id = work_order.work_order_id')
            ->where(
                'invoice.status_type_id',
                $this->type->getIdByKey('invoice_status.internal_approved')
            )
            ->limit(1);

        return $model->whereRaw(
            '0 < (' . $subQuery->toSql() . ')',
            $subQuery->getBindings()
        );
    }

    /**
     * Add custom condition for updated_work_orders filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addUpdatedWorkOrdersCondition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        $subQuery = DB::table('email')->selectRaw('count(email_id)')
            ->whereRaw('email.work_order_id = work_order.work_order_id')
            ->limit(1);

        return $model->whereRaw(
            ' 1 < (' . $subQuery->toSql() . ')',
            $subQuery->getBindings()
        );
    }

    /**
     * Add custom condition for past_due_work_orders filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addPastDueWorkOrdersCondition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        return $model->whereNotIn('work_order.wo_status_type_id', [
            $this->type->getIdByKey('wo_status.completed'),
            $this->type->getIdByKey('wo_status.canceled'),

        ])->where('expected_completion_date', '<', DB::raw('now()'));
    }

    /**
     * Add custom condition for techs_in_progress filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addTechsInProgressCondition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        $type = $this->type;

        return $model->whereIn('work_order_id', function ($q) use ($type) {
            /** @var \Illuminate\Database\Query\Builder $q */
            $q->select('work_order_id')->from('link_person_wo')
                ->where(
                    'link_person_wo.status_type_id',
                    $this->type->getIdByKey('wo_vendor_status.in_progress')
                )
                ->distinct();
        });
    }

    /**
     * Add custom condition for quote_status filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addQuoteStatusCondition($model, $value)
    {
        return $model->whereIn('work_order_id', function ($q) use ($value) {
            /** @var \Illuminate\Database\Query\Builder $q */
            $q->select('table_id')->from('quote')
                ->where('table_name', 'work_order')
                ->where('quote.type_id', $value)->distinct();
        });
    }

    /**
     * Add custom condition for ready_to_invoice filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addReadyToInvoiceCondition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        $model = $model->whereIn('work_order_id', function ($q) {
            /** @var \Illuminate\Database\Query\Builder $q */
            $q->select('work_order_id')->from('link_person_wo')->distinct();
        })->whereNotIn('work_order_id', function ($q) {
            /** @var \Illuminate\Database\Query\Builder $q */
            $q->select('work_order_id')->from('link_person_wo')
                ->where('bill_final', 0)->distinct();
        })->where(
            'invoice_status_type_id',
            $this->type->getIdByKey('wo_billing_status.update_required')
        );

        return $model;
    }

    /**
     * Add custom condition for only_recalled filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addOnlyRecalledCondition($model, $value)
    {
        return $model->whereIn('work_order_id', function ($q) {
            /** @var \Illuminate\Database\Query\Builder $q */
            $q->select('lpwo1.work_order_id')->from('link_person_wo AS lpwo1')
                ->where('type', 'recall')->distinct();
        });
    }

    /**
     * Add custom condition for vendors_min_1_additional filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addVendorsMin1AdditionalCondition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        $model = $model->whereNotIn('invoice_status_type_id', [
            $this->type->getIdByKey('wo_billing_status.invoiced'),
            $this->type->getIdByKey('wo_billing_status.paid'),
            $this->type->getIdByKey('wo_billing_status.invoice_sent'),
        ])->where('tkt_assigned.wo_id', '>', 0);

        $model = $model->join(DB::raw(
            "(select `work_order_id` as `wo_id`, `link_person_wo_id` as
             `vendors_without_tkts` from `link_person_wo`
             left join `file` on `link_person_wo`.`link_person_wo_id`
              = `file`.`table_id` where
              ( select COUNT(bill.bill_id) from `bill` where
                 bill.link_person_wo_id = link_person_wo.link_person_wo_id) = 0
            and (`filename` LIKE '%invoice%' or `filename` LIKE '%receipt%')
            and `table_name` = 'link_person_wo' and `link_person_wo`.`person_id`
             in (select `person_id` from `person` where `person_type`
             in ({$this->type->getIdByKey('company.vendor')},
             {$this->type->getIdByKey('company.supplier')}))
              group by `work_order_id`) AS tkt_assigned "
        ), function ($join) {
            $join->on('tkt_assigned.wo_id', '=', 'work_order.work_order_id');
        });

        return $model;
    }

    /**
     * Add custom condition for technicians_with_bills filter
     *
     * @param \Illuminate\Database\Query\Builder $model
     * @param string $value
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addTechniciansWithBillsCondition($model, $value)
    {
        if ($value != 1) {
            return $model;
        }

        $model = $model->whereNotIn('invoice_status_type_id', [
            $this->type->getIdByKey('wo_billing_status.invoiced'),
            $this->type->getIdByKey('wo_billing_status.paid'),
            $this->type->getIdByKey('wo_billing_status.invoice_sent'),
        ])->where('bills_assigned.wo_id', '>', 0);

        $model = $model->join(DB::raw(
            "(SELECT work_order_id AS wo_id, link_person_wo.link_person_wo_id
            AS vendors_with_bills, bill.creator_person_id, file.`file_id`
                FROM link_person_wo
                LEFT JOIN `bill` ON
                link_person_wo.link_person_wo_id = bill.link_person_wo_id
                LEFT JOIN `file` ON file.`table_id` = bill.bill_id
                WHERE bill.creator_person_id = link_person_wo.person_id
                AND bill.final = 0 AND
                file.table_name ='bill' AND link_person_wo.person_id
                IN(SELECT person_id FROM person WHERE person.type_id
                IN ({$this->type->getIdByKey('person.technician')},
                {$this->type->getIdByKey('person.employee')}))
                GROUP BY work_order_id) AS bills_assigned "
        ), function ($join) {
            $join->on('bills_assigned.wo_id', '=', 'work_order.work_order_id');
        });

        return $model;
    }

    /**
     * Add only_sl_work_orders condition
     * @param \Illuminate\Database\Query\Builder $model
     * @param mixed $value
     * @return \Illuminate\Database\Query\Builder
     */
    protected function addOnlySlWorkOrdersCondition($model, $value)
    {
        if (!$value) {
            return $model;
        }

        return $model->join('sl_records', function ($j) {
            $j
            ->where('sl_records.table_name', '=', 'work_order')
            ->on('sl_records.record_id', '=', 'work_order.work_order_id');
        });
    }
}
