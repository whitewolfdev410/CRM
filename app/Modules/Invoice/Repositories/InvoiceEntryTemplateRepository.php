<?php

namespace App\Modules\Invoice\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Invoice\Models\InvoiceEntryTemplate;
use App\Modules\Invoice\Models\InvoiceTemplate;
use Illuminate\Container\Container;

/**
 * Invoice Entry Template repository class
 */
class InvoiceEntryTemplateRepository extends AbstractRepository
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
     * @param  InvoiceEntryTemplate  $invoiceEntryTemplate
     */
    public function __construct(Container $app, InvoiceEntryTemplate $invoiceEntryTemplate)
    {
        parent::__construct($app, $invoiceEntryTemplate);
    }
}
