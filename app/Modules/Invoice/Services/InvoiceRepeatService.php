<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Invoice\Http\Requests\InvoiceRepeatRequest;
use App\Modules\Invoice\Models\InvoiceRepeat;
use App\Modules\Invoice\Repositories\InvoiceRepeatRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceRepeatService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var InvoiceRepeatRepository
     */
    protected $invoiceRepeatRepository;

    /**
     * Initialize class
     *
     * @param  Container  $app
     * @param  InvoiceRepeatRepository  $invoiceRepeatRepository
     */
    public function __construct(Container $app, InvoiceRepeatRepository $invoiceRepeatRepository)
    {
        $this->app = $app;
        $this->invoiceRepeatRepository = $invoiceRepeatRepository;
    }

    /**
     * @return Collection|LengthAwarePaginator
     */
    public function paginate()
    {
        $onPage = (int) request()->get('limit', config()->get('system_settings.invoice_pagination'));

        return $this->invoiceRepeatRepository->paginate($onPage);
    }

    /**
     * @param  int  $id
     *
     * @return array
     */
    public function show(int $id)
    {
        return $this->invoiceRepeatRepository->show($id);
    }

    /**
     * @param  InvoiceRepeatRequest  $invoiceRepeatRequest
     *
     * @return Model|InvoiceRepeat
     */
    public function create(InvoiceRepeatRequest $invoiceRepeatRequest)
    {
        $input = $invoiceRepeatRequest->all();

        return $this->invoiceRepeatRepository->create($input);
    }
    
    /**
     * @param  InvoiceRepeatRequest  $invoiceRepeatRequest
     * @param  int  $id
     *
     * @return Model|InvoiceRepeat
     */
    public function update(InvoiceRepeatRequest $invoiceRepeatRequest, int $id)
    {
        $input = $invoiceRepeatRequest->all();

        return $this->invoiceRepeatRepository->updateWithIdAndInput($id, $input);
    }

    /**
     * @param  int  $id
     *
     * @return bool
     */
    public function destroy(int $id)
    {
        return $this->invoiceRepeatRepository->destroy($id);
    }

    /**
     * @return array
     */
    public function getRequestRules()
    {
        $invoiceRepeatRequest = new InvoiceRepeatRequest();


        $rules = [
            'fields' => $invoiceRepeatRequest->getFrontendRules(),
        ];

        $visibleProperties = [
            'interval_keyword',
            'reminder',
            'next_date'
        ];

        foreach ($visibleProperties as $property) {
            if (!isset($rules['fields'][$property])) {
                $rules['fields'][$property]['rules'] = ['visible'];
            }
        }

        $rules['fields']['interval_keyword']['data'] = [
            ['label' => 'Day', 'value' => 'day'],
            ['label' => 'Week', 'value' => 'week'],
            ['label' => 'Month', 'value' => 'month'],
                        
        ];
        
        return $rules;
    }
}
