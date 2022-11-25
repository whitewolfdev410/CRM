<?php

namespace App\Modules\Invoice\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Invoice\Models\InvoiceTemplate;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * Invoice Template repository class
 */
class InvoiceTemplateRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [
        'template_name'
    ];

    /**
     * Repository constructor
     *
     * @param  Container  $app
     * @param  InvoiceTemplate  $invoiceTemplate
     */
    public function __construct(Container $app, InvoiceTemplate $invoiceTemplate)
    {
        parent::__construct($app, $invoiceTemplate);
    }

    /**
     * @param  $id
     * @param  $full
     *
     * @return array
     */
    public function show($id, $full = false)
    {
        return $this->model
            ->select([
                DB::raw('invoice_template_id as id'),
                'invoice_template_id',
                'template_name',
                'person_id',
                DB::raw('person_name(person_id) as person_id_value'),
                'date_invoice_interval',
                'date_due_interval',
                'currency'
            ])
            ->with([
                'entries' => function ($query) {
                    $query->select([
                        'invoice_entry_template_id',
                        'invoice_template_id',
                        DB::raw('entry_long as description'),
                        'qty',
                        'price',
                        'total',
                        'service_id',
                        'item_id',
                        'person_id',
                        DB::raw('IF(tax_amount > 0, 1, 0) as taxable')
                    ]);
                }
            ])
            ->find($id);
    }
}
