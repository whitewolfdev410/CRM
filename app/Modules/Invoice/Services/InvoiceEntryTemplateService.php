<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Invoice\Models\InvoiceEntryTemplate;
use App\Modules\Invoice\Repositories\InvoiceEntryTemplateRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

class InvoiceEntryTemplateService
{
    /**
     * @var Container
     */
    protected $app;
    /**
     * @var InvoiceEntryTemplateRepository
     */
    protected $invoiceEntryTemplateRepository;

    /**
     * Initialize class
     *
     * @param  Container  $app
     * @param  InvoiceEntryTemplateRepository  $invoiceEntryTemplateRepository
     */
    public function __construct(Container $app, InvoiceEntryTemplateRepository $invoiceEntryTemplateRepository)
    {
        $this->app = $app;
        $this->invoiceEntryTemplateRepository = $invoiceEntryTemplateRepository;
    }

    /**
     * Create new invoice entry template
     *
     * @param  array  $input
     *
     * @return InvoiceEntryTemplate|Model
     */
    public function create(array $input)
    {
        return $this->invoiceEntryTemplateRepository->create($input);
    }

    /**
     * Update invoice entry template
     *
     * @param  integer  $id
     * @param  array  $input
     *
     * @return InvoiceEntryTemplate|Model
     */
    public function update($id, $input)
    {
        return $this->invoiceEntryTemplateRepository->updateWithIdAndInput($id, $input);
    }

    /**
     * Create, update or delete invoice entry template based on input
     *
     * @param  array  $input
     *
     * @return bool|Model
     */
    public function save(array $input)
    {
        if (empty($input['id'])) {
            $result = $this->invoiceEntryTemplateRepository->create($input);
        } elseif ((int) $input['id'] > 0) {
            if (empty($input['is_deleted'])) {
                $result = $this->invoiceEntryTemplateRepository->updateWithIdAndInput($input['id'], $input);
            } else {
                $result = $this->invoiceEntryTemplateRepository->destroy($input['id']);
            }
        }

        return $result ?? null;
    }
}
