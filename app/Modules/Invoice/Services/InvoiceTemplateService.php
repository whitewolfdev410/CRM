<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Invoice\Http\Requests\InvoiceTemplateRequest;
use App\Modules\Invoice\Repositories\InvoiceTemplateRepository;
use App\Modules\System\Repositories\SystemSettingsRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceTemplateService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var InvoiceTemplateRepository
     */
    protected $invoiceTemplateRepository;

    /**
     * @var InvoiceEntryTemplateService
     */
    protected $invoiceEntryTemplateService;

    /**
     * Initialize class
     *
     * @param  Container  $app
     * @param  InvoiceTemplateRepository  $invoiceTemplateRepository
     * @param  InvoiceEntryTemplateService  $invoiceEntryTemplateService
     */
    public function __construct(
        Container $app,
        InvoiceTemplateRepository $invoiceTemplateRepository,
        InvoiceEntryTemplateService $invoiceEntryTemplateService
    ) {
        $this->app = $app;
        $this->invoiceTemplateRepository = $invoiceTemplateRepository;
        $this->invoiceEntryTemplateService = $invoiceEntryTemplateService;
    }

    /**
     * @return Collection|LengthAwarePaginator
     */
    public function paginate()
    {
        $onPage = (int) request()->get('limit', config()->get('system_settings.invoice_pagination'));

        return $this->invoiceTemplateRepository->paginate($onPage);
    }

    /**
     * @param  int  $id
     *
     * @return array
     */
    public function show(int $id)
    {
        return ['item' => $this->invoiceTemplateRepository->show($id)];
    }

    /**
     * @param  InvoiceTemplateRequest  $invoiceTemplateRequest
     * @param  int|null  $id
     *
     * @return array
     * @throws \Exception
     */
    public function save(InvoiceTemplateRequest $invoiceTemplateRequest, int $id = null)
    {
        $input = $invoiceTemplateRequest->all();

        DB::beginTransaction();

        try {
            list($invoiceTemplate, $invoiceTemplateEntries) = $this->parseInvoiceTemplateAndEntries($input);

            if (empty($id)) {
                //create invoice template if id is empty
                $invoiceTemplateModel = $this->invoiceTemplateRepository->create($invoiceTemplate);
            } else {
                //update invoice template
                $invoiceTemplateModel = $this->invoiceTemplateRepository->updateWithIdAndInput($id, $invoiceTemplate);
            }

            foreach ($invoiceTemplateEntries as $invoiceTemplateEntry) {
                $invoiceTemplateEntry['invoice_template_id'] = $invoiceTemplateModel->id;
                $invoiceTemplateEntry['person_id'] = $invoiceTemplateModel->person_id;

                //save purchase order entry
                $this->invoiceEntryTemplateService->save($invoiceTemplateEntry);
            }

            DB::commit();

            return $this->show($invoiceTemplateModel->id);
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * @param  int  $id
     *
     * @return bool
     */
    public function destroy(int $id)
    {
        return $this->invoiceTemplateRepository->destroy($id);
    }

    /**
     * @return array
     */
    public function getRequestRules()
    {
        $invoiceTemplateRequest = new InvoiceTemplateRequest();

        $configs = app(SystemSettingsRepository::class)->getByGroup('crm_config', true);

        $rules = [
            'fields'  => $invoiceTemplateRequest->getFrontendRules(),
            'configs' => [
                'tax_rate' => (float)$configs['tax_rate'],
                'profit_service_id' => (int)$configs['profit_service_id'],
                'profit_rate' => (float)$configs['profit_rate']
            ]
        ];

        $range = [
            ['label' => 'day(s)', 'value' => 'DAY'],
            ['label' => 'week(s)', 'value' => 'WEEK'],
            ['label' => 'month(s)', 'value' => 'MONTH']
        ];

        $period = [
            ['label' => 'earlier', 'value' => '-'],
            ['label' => 'later', 'value' => '+'],
            ['label' => 'custom', 'value' => 'custom', 'extra_options' => [
                ['label' => 'end_of_month', 'value' => 'LAST_MONTH_DAY']
            ]]
        ];

        $rules['fields']['date_due_interval_range']['data'] = $range;
        $rules['fields']['date_due_interval_period']['data'] = $period;
        $rules['fields']['date_invoice_interval_range']['data'] = $range;
        $rules['fields']['date_invoice_interval_period']['data'] = $period;

        $visibleProperties = [
            'date_due_interval',
            'date_invoice_interval',
        ];

        foreach ($visibleProperties as $property) {
            if (!isset($rules['fields'][$property])) {
                $rules['fields'][$property]['rules'] = ['visible'];
            }
        }

        return $rules;
    }

    private function parseInvoiceTemplateAndEntries(array $invoiceTemplate)
    {
        $taxRate = app(SystemSettingsRepository::class)->getValueByKey('crm_config.tax_rate', 0);

        $invoiceTemplateEntries = [];

        if (isset($invoiceTemplate['entries'])) {
            $invoiceTemplateEntries = $invoiceTemplate['entries'];

            foreach ($invoiceTemplateEntries as $index => $invoiceTemplateEntry) {
                $invoiceTemplateEntries[$index]['entry_long'] = $invoiceTemplateEntry['description'] ?? null;
                $invoiceTemplateEntries[$index]['qty'] = $invoiceTemplateEntry['qty'] ?? 0;
                $invoiceTemplateEntries[$index]['total'] = $invoiceTemplateEntry['qty'] * $invoiceTemplateEntry['price'];
                $invoiceTemplateEntries[$index]['tax_rate'] = $taxRate;

                if (!empty($invoiceTemplateEntry['taxable'])) {
                    $invoiceTemplateEntries[$index]['tax_amount'] = round(
                        ($invoiceTemplateEntries[$index]['total'] * $taxRate) / 100,
                        2
                    );
                } else {
                    $invoiceTemplateEntries[$index]['tax_amount'] = 0;
                }
            }

            unset($invoiceTemplate['entries']);
        }

        return [$invoiceTemplate, $invoiceTemplateEntries];
    }
}
