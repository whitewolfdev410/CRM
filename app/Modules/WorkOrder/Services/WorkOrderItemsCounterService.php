<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Support\Facades\DB;

/**
 * Class WorkOrderItemsCounterService
 *
 * Get items count for many criteria in colour boxes
 *
 * @package App\Modules\WorkOrder\Services
 */
class WorkOrderItemsCounterService
{
    /**
     * Work order repository
     *
     * @var WorkOrderRepository
     */
    protected $workOrderRepo;

    /**
     * Work order model
     *
     * @var WorkOrder
     */
    protected $workOrder;

    /**
     * Type repository
     *
     * @var TypeRepository
     */
    protected $type;

    /**
     * Default meta data string to search
     *
     * @var string
     */
    protected $defMetaData;

    /**
     * Initialize repositories and models
     *
     * @param WorkOrderRepository $workOrderRepository
     * @param WorkOrder $workOrder
     * @param TypeRepository $typeRepository
     */
    public function __construct(
        WorkOrderRepository $workOrderRepository,
        WorkOrder $workOrder,
        TypeRepository $typeRepository
    ) {
        $this->workOrderRepo = $workOrderRepository;
        $this->workOrder = $workOrder;

        $this->type = $typeRepository;

        $this->defMetaData = $this->workOrderRepo->getMetaData();
    }

    /**
     * Get all work orders count
     *
     * @return int
     */
    public function getAllCount()
    {
        return $this->workOrder->count();
    }

    /**
     * Get hot work orders count
     *
     * @return int
     */
    public function getHotCount()
    {
        $hotType = $this->type->getIdByKey('task.hot');

        $model = $this->workOrder->whereRaw("
		    0 < (SELECT count(calendar_event_id) FROM calendar_event c
		    WHERE c.tablename='work_order'
		    AND c.record_id = work_order.work_order_id
		    AND c.is_completed=0 AND c.type_id = " . $hotType . ')');

        return $model->count('work_order.work_order_id');
    }

    /**
     * Get count of work orders for each value in $column
     *
     * @param string $column
     * @param bool $notNull
     *
     * @return int
     */
    public function getGivenTypeCount($column, $notNull = false)
    {
        $model
            = $this->workOrder->selectRaw("{$column}, count(work_order_id) AS aggregate");

        if ($notNull) {
            $model = $model->whereNotNull($column);
        }

        return $model->groupBy($column)->pluck('aggregate', $column)->all();
    }

    /**
     * Get count of completed work orders that needs invoice
     *
     * @return int
     */
    public function getCompletedNeedInvoiceCount()
    {
        $upStatus
            = $this->type->getIdByKey('wo_billing_status.update_required');

        $completeStatus = $this->type->getIdByKey('wo_status.completed');

        $model = $this->workOrder->where('invoice_status_type_id', $upStatus)
            ->where('wo_status_type_id', $completeStatus);

        return $model->count('work_order_id');
    }

    /**
     * Get count of ready to invoice work orders
     *
     * @return int
     */
    public function getReadyToInvoiceCount()
    {
        $invReady
            = $this->type->getColumnByKey('wo_billing_status.ready_to_invoice');

        $model = $this->workOrder->where('invoice_status_type_id', $invReady)
            ->whereIn(
                'work_order.client_status',
                ['COMPLETED (confirmed)', 'COMPLETED (pending confirmation)']
            )
            ->whereNotIn('work_order.work_order_id', function ($q) {
                $q->select('quote.table_id')->from('quote')
                    ->where('quote.table_name', 'work_order')
                    ->whereRaw('quote.table_id = work_order.work_order_id');
            })->whereIn('company_person_id', function ($q) {
                $q->select('company_person_id')->from('customer_settings')
                    ->where('meta_data', 'LIKE', $this->defMetaData);
            });

        return $model->count('work_order_id');
    }

    /**
     * Get count of work orders that waiting for invoice approval
     *
     * @return int
     */
    public function getQuoteNeedsApprovalCount()
    {
        $bsReadyToInvoice
            = $this->type->getIdByKey('wo_billing_status.ready_to_invoice');

        $quoteWaitingApproval
            = $this->type->getIdByKey('quote_status.internal_waiting_quote_approval');

        $model = $this->workOrder->where(
            'invoice_status_type_id',
            $bsReadyToInvoice
        )
            ->where('work_order.client_status', 'COMPLETED (confirmed)')
            ->whereIn(
                'work_order.work_order_id',
                function ($q) use ($quoteWaitingApproval) {
                    $q->select('quote.table_id')->from('quote')
                        ->where('quote.table_name', 'work_order')
                        ->whereRaw('quote.table_id = work_order.work_order_id')
                        ->where('quote.type_id', $quoteWaitingApproval);
                }
            )
            ->whereIn('company_person_id', function ($q) {
                $q->select('company_person_id')->from('customer_settings')
                    ->where('meta_data', 'LIKE', $this->defMetaData);
            });

        return $model->count('work_order_id');
    }

    /**
     * Get count of work orders that need invoice
     *
     * @return int
     */
    public function getNeedInvoiceCount()
    {
        $bsReadyToInvoice
            = $this->type->getIdByKey('wo_billing_status.ready_to_invoice');

        $status = [
            $this->type->getIdByKey('quote_status.internal_waiting_quote_approval'),
            $this->type->getIdByKey('quote_status.internal_quote_approved'),
            $this->type->getIdByKey('quote_status.internal_waiting_invoice_approval'),
        ];

        $model = $this->workOrder->where(
            'invoice_status_type_id',
            $bsReadyToInvoice
        )
            ->where('work_order.client_status', 'COMPLETED (confirmed)')
            ->whereIn(
                'work_order.work_order_id',
                function ($q) use ($status) {
                    $q->select('quote.table_id')->from('quote')
                        ->where('quote.table_name', 'work_order')
                        ->whereRaw('quote.table_id = work_order.work_order_id')
                        ->whereIn('quote.type_id', $status);
                }
            )
            ->whereIn('company_person_id', function ($q) {
                $q->select('company_person_id')->from('customer_settings')
                    ->where('meta_data', 'LIKE', $this->defMetaData);
            })->whereNotIn('work_order.work_order_id', function ($q) {
                $q->selecT('invoice.table_id')->from('invoice')
                    ->where('invoice.table_name', 'work_order')
                    ->whereRaw('invoice.table_id = work_order.work_order_id');
            });

        return $model->count('work_order_id');
    }

    /**
     * Get count of work orders which invoice needs approval
     *
     * @return int
     */
    public function getInvoiceNeedsApprovalCount()
    {
        $intWaitingApproval
            = $this->type->getIdByKey('invoice_status.internal_waiting_for_approval');

        $model = $this->workOrder->leftJoin('invoice', function ($q) {
            $q->on('invoice.table_id', '=', 'work_order.work_order_id');
            $q->on('invoice.table_name', '=', DB::raw('"work_order"'));
        })->where('invoice.invoice_id', '>', 0)
            ->where('invoice.status_type_id', $intWaitingApproval);

        return $model->count('work_order.work_order_id');
    }

    /**
     * Get count of work orders which invoice has not been sent
     *
     * @return int
     */
    public function getInvoicedNotSentCount()
    {
        $intAppr = $this->type->getIdByKey('invoice_status.internal_approved');

        $model = $this->workOrder->leftJoin('invoice', function ($q) {
            $q->on('invoice.table_id', '=', 'work_order.work_order_id');
            $q->on('invoice.table_name', '=', DB::raw('"work_order"'));
        })->whereNotNull('invoice.invoice_id')
            ->where('invoice.status_type_id', $intAppr);

        return $model->count('work_order.work_order_id');
    }

    /**
     * Get count of work orders which invoice has been rejected
     *
     * @return int
     */
    public function getInvoiceRejectedCount()
    {
        $intRej = $this->type->getIdByKey('invoice_status.internal_rejected');

        $model = $this->workOrder->leftJoin('invoice', function ($q) {
            $q->on('invoice.table_id', '=', 'work_order.work_order_id');
            $q->on('invoice.table_name', '=', DB::raw('"work_order"'));
        })->whereNotNull('invoice.invoice_id')
            ->where('invoice.status_type_id', $intRej);

        return $model->count('work_order.work_order_id');
    }

    /**
     * Get count of updated work orders
     *
     * @return int
     */
    public function getUpdatedWorkOrdersCount()
    {
        $subQuery = DB::table('email')->selectRaw('count(email_id)')->whereRaw(
            'email.work_order_id = work_order.work_order_id'
        )->limit(1);

        $model = $this->workOrder->whereRaw(
            '1 < (' . $subQuery->toSql() . ')',
            $subQuery->getBindings()
        );

        return $model->count('work_order.work_order_id');
    }

    /**
     * Get count of work orders that should be already completed and are
     * not completed or cancelled
     *
     * @return int
     */
    public function getPastDueWorkOrdersCount()
    {
        $status = [
            $this->type->getIdByKey('wo_status.completed'),
            $this->type->getIdByKey('wo_status.canceled'),
        ];

        $model = $this->workOrder->whereNotIn(
            'work_order.wo_status_type_id',
            $status
        )->where('expected_completion_date', '<', DB::raw('now()'));

        return $model->count('work_order.work_order_id');
    }

    /**
     * Get count of techs that are in progress of work orders
     *
     * @return int
     */
    public function getTechsInProgressCount()
    {
        /** @var LinkPersonWoRepository $lpRepo */
        $lpRepo = $this->workOrderRepo->makeRepository(
            'LinkPersonWo',
            'WorkOrder'
        );

        return $lpRepo->getTechsInProgressCount();
    }

    /**
     * Get counts for each quote status
     *
     * @return int
     */
    public function getQuoteStatusCount()
    {
        $model
            = $this->workOrder->selectRaw(' count(*) as aggregate, quote.type_id')
            ->leftJoin(
                'quote',
                'quote.table_id',
                '=',
                'work_order.work_order_id'
            )
            ->whereRaw("quote.table_name = 'work_order'")
            ->groupBy('quote.type_id');

        return $model->pluck('aggregate', 'type_id')->all();
    }
}
