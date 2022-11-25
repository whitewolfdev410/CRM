<?php

namespace App\Modules\WorkOrder\Services;

use Illuminate\Contracts\Container\Container;

/**
 * Class WorkOrderBoxCounterService
 *
 * Generate count items data for colour boxes
 *
 * @package App\Modules\WorkOrder\Services
 */
class WorkOrderBoxCounterService implements WorkOrderBoxCounterServiceContract
{
    /**
     * Counter service object
     *
     * @var WorkOrderBoxCounterService
     */
    protected $counter;

    /**
     * @var Container
     */
    protected $app;

    /**
     * Initialize counter
     *
     * @param WorkOrderItemsCounterService $counter
     */
    public function __construct(
        WorkOrderItemsCounterService $counter,
        Container $app
    ) {
        $this->counter = $counter;
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function generate()
    {
        // blue
        $data['all'] = $this->getAll();
        $data['hot'] = $this->getHot();
        $data['wo_status_type_id'] = $this->getType('wo_status_type_id');

        // orange
        $data['quote_status_type_id'] = $this->getType('quote_status_type_id');

        // gray
        $data['bill_status_type_id'] = $this->getType('bill_status_type_id');

        // green
        $data['invoice_status_type_id']
            = $this->getType('invoice_status_type_id');

        //yellow
        $data['custom_1'] = $this->getCustom1();

        // red
        $data['past_work_orders'] = $this->getPastDueWorkOrders();

        // lightgray
        $data['techs_in_progress'] = $this->getTechsInProgress();

        // pink
        $data['quote_status'] = $this->getQuoteStatus();

        return $data;
    }

    /**
     * Generate data for All box
     *
     * @return array
     */
    protected function getAll()
    {
        return [
            [
                'key' => 'all',
                'label' => '',
                'count' => $this->counter->getAllCount(),
                'url' => '',
            ],
        ];
    }

    /**
     * Generate data for Hot box
     *
     * @return array
     */
    protected function getHot()
    {
        return [
            [
                'key' => 'hot',
                'label' => '',
                'count' => $this->counter->getHotCount(),
                'url' => 'hot=1',
            ],
        ];
    }

    /**
     * Generate data for given $type box
     *
     * @param string $type
     *
     * @return array
     */
    protected function getType($type)
    {
        return [
            'counts' => $this->counter->getGivenTypeCount($type),
            'url' => "{$type}=",
        ];
    }

    /**
     * Generate data for custom 1 box
     *
     * @return array
     */
    protected function getCustom1()
    {
        return [
            [
                'key' => 'completed_need_invoice',
                'label' => '',
                'count' => $this->counter->getCompletedNeedInvoiceCount(),
                'url' => 'completed_need_invoice=1',
            ],
            [
                'key' => 'ready_to_quote',
                'label' => '',
                'count' => $this->counter->getReadyToInvoiceCount(),
                'url' => 'ready_to_quote=1',
            ],
            [
                'key' => 'quote_needs_approval',
                'label' => '',
                'count' => $this->counter->getQuoteNeedsApprovalCount(),
                'url' => 'quote_needs_approval=1',
            ],
            [
                'key' => 'quote_approved_need_invoice',
                'label' => '',
                'count' => $this->counter->getNeedInvoiceCount(),
                'url' => 'quote_approved_need_invoice=1',
            ],
            [
                'key' => 'invoice_needs_approval',
                'label' => '',
                'count' => $this->counter->getInvoiceNeedsApprovalCount(),
                'url' => 'invoice_needs_approval=1',
            ],
            [
                'key' => 'invoiced_not_sent',
                'label' => '',
                'count' => $this->counter->getInvoicedNotSentCount(),
                'url' => 'invoiced_not_sent=1',
            ],
            [
                'key' => 'invoice_rejected',
                'label' => '',
                'count' => $this->counter->getInvoiceRejectedCount(),
                'url' => 'invoice_rejected=1',
            ],
            [
                'key' => 'updated_work_orders',
                'label' => '',
                'count' => $this->counter->getUpdatedWorkOrdersCount(),
                'url' => 'updated_work_orders=1',
            ],
        ];
    }

    /**
     * Generate data for Past due work orders box
     *
     * @return array
     */
    protected function getPastDueWorkOrders()
    {
        return [
            [
                'key' => 'past_due_work_orders',
                'label' => '',
                'count' => $this->counter->getPastDueWorkOrdersCount(),
                'url' => 'past_due_work_orders=1',
            ],
        ];
    }

    /**
     * Generate data for Techs in progress box
     *
     * @return array
     */
    protected function getTechsInProgress()
    {
        return [
            [
                'key' => 'techs_in_progress',
                'label' => '',
                'count' => $this->counter->getTechsInProgressCount(),
                'url' => 'techs_in_progress=1',
            ],
        ];
    }

    /**
     * Generate data for quote_status box
     *
     * @return array
     */
    protected function getQuoteStatus()
    {
        return [
            'counts' => $this->counter->getQuoteStatusCount(),
            'url' => 'quote_status=',
        ];
    }
}
