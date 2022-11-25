<?php

namespace App\Modules\Invoice\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Invoice\Models\InvoiceRepeat;
use Illuminate\Container\Container;

/**
 * Invoice Repeat repository class
 */
class InvoiceRepeatRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [
    ];

    /**
     * Repository constructor
     *
     * @param  Container  $app
     * @param  InvoiceRepeat  $invoiceRepeat
     */
    public function __construct(Container $app, InvoiceRepeat $invoiceRepeat)
    {
        parent::__construct($app, $invoiceRepeat);
    }

    public function paginate($perPage = 50, array $columns = ['*'], array $order = [])
    {
        $invoiceClient = 'IF(invoice_repeat.invoice_id > 0, (SELECT person_name(person_id) FROM invoice WHERE invoice.invoice_id = invoice_repeat.invoice_id), (SELECT person_name(person_id) FROM invoice_template WHERE invoice_template.invoice_template_id = invoice_repeat.invoice_template_id))';

        $invoiceTotal = 'IF(invoice_repeat.invoice_id > 0, (SELECT sum(tax_amount + total) FROM invoice_entry WHERE invoice_entry.invoice_id = invoice_repeat.invoice_id), (SELECT sum(tax_amount + total) FROM invoice_entry_template WHERE  invoice_entry_template.invoice_template_id = invoice_repeat.invoice_template_id))';

        $templateClient = 'IF(invoice_repeat.invoice_template_id > 0, (SELECT person_name(person_id) FROM invoice_template WHERE invoice_template.invoice_template_id = invoice_repeat.invoice_template_id), \'\')';

        $templateTotal = 'IF(invoice_repeat.invoice_template_id > 0, (SELECT sum(tax_amount + total) FROM invoice_entry_template WHERE invoice_entry_template.invoice_template_id = invoice_repeat.invoice_template_id), \'\')';

        $this->availableColumns = [
            'invoice_repeat_id'   => 'invoice_repeat.invoice_repeat_id',
            'invoice_id'          => 'invoice_repeat.invoice_id',
            'interval'            => 'invoice_repeat.interval',
            'interval_keyword'    => 'invoice_repeat.interval_keyword',
            'reminder'            => 'invoice_repeat.reminder',
            'next_date'           => 'invoice_repeat.next_date',
            'days_in_advance'     => 'invoice_repeat.days_in_advance',
            'number_remaining'    => 'invoice_repeat.number_remaining',
            'invoice_template_id' => 'invoice_repeat.invoice_template_id',
            'invoice_client' => $invoiceClient,
            'invoice_total' => $invoiceTotal,
            'template_client' => $templateClient,
            'template_total' => $templateTotal
        ];

        $model = $this->model;

        $model = $this->setCustomColumns($model);
        $model = $this->setCustomFilters($model);
        $model = $this->setCustomSort($model);

        $this->setWorkingModel($model);
        $data = parent::paginate($perPage, [], $order);
        $this->clearWorkingModel();

        return $data;
    }

    /**
     * @param  int  $id
     * @param  false  $full
     *
     * @return array
     */
    public function show($id, $full = false)
    {
        return $this->model
            ->select('invoice_repeat.*', 'invoice_template.template_name')
            ->join('invoice_template', 'invoice_template.invoice_template_id', '=', 'invoice_repeat.invoice_template_id')
            ->find($id);
    }
}
