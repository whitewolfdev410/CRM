<?php

namespace App\Modules\Invoice\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use App\Modules\Invoice\Http\Requests\InvoiceEntryRequest;
use App\Modules\Invoice\Repositories\InvoiceEntryRepository;
use App\Modules\Item\Repositories\ItemRepository;
use App\Modules\Service\Repositories\ServiceRepository;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class InvoiceEntryController
 *
 * @package App\Modules\Invoice\Http\Controllers
 */
class InvoiceEntryController extends Controller
{
    /**
     * Invoice entry repository
     *
     * @var InvoiceEntryRepository
     */
    private $invoiceEntryRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param InvoiceEntryRepository $invoiceEntryRepository
     */
    public function __construct(InvoiceEntryRepository $invoiceEntryRepository)
    {
        $this->middleware('auth');
        $this->invoiceEntryRepository = $invoiceEntryRepository;
    }

    /**
     * Return list of Invoice Entry
     *
     * @param Config  $config
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws App\Core\Exceptions\NoPermissionException
     * @throws \InvalidArgumentException
     */
    public function index(
        Config $config,
        Request $request
    ) {
        $this->checkPermissions(['invoice.index']);

        $onPage = (int)$request->get('limit', $config->get('system_settings.invoice_entry_pagination'));

        $list = $this->invoiceEntryRepository
            ->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Display the specified Invoice Entry
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function show($id)
    {
        $this->checkPermissions(['invoice.show']);
        $id = (int)$id;

        return response()->json($this->invoiceEntryRepository->show($id));
    }

    /**
     * Return module configuration for store action
     *
     * @param Request           $request
     * @param ItemRepository    $itemRepository
     * @param ServiceRepository $serviceRepository
     *
     * @return JsonResponse
     *
     * @throws App\Core\Exceptions\NoPermissionException
     */
    public function create(
        Request $request,
        ItemRepository $itemRepository,
        ServiceRepository $serviceRepository
    ) {
        $this->checkPermissions(['invoice.store']);

        $personId = $request->get('person_id', 0);

        $rules['fields'] = $this->invoiceEntryRepository->getRequestRules();
        $rules['items'] = $itemRepository->allWithPrices();
        $rules['services'] = $serviceRepository->getEnabledServices($personId, true);

        return response()->json($rules);
    }

    /**
     * Store a newly created Invoice Entry in storage.
     *
     * @param InvoiceEntryRequest $request
     *
     * @return JsonResponse
     *
     * @throws App\Core\Exceptions\NoPermissionException
     * @throws \Exception
     */
    public function store(InvoiceEntryRequest $request)
    {
        $this->checkPermissions(['invoice.store']);
        $model = $this->invoiceEntryRepository->create($request->all());

        return response()->json(['item' => $model], 201);
    }

    /**
     * Display Invoice Entry and module configuration for update action
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function edit($id)
    {
        $this->checkPermissions(['invoice.update']);
        $id = (int)$id;

        return response()->json($this->invoiceEntryRepository->show($id, true));
    }

    /**
     * Update the specified Invoice Entry in storage.
     *
     * @param InvoiceEntryRequest $request
     * @param  int                $id
     *
     * @return JsonResponse
     *
     * @throws App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update(InvoiceEntryRequest $request, $id)
    {
        $this->checkPermissions(['invoice.update']);
        $id = (int)$id;

        $invoiceEntry = $request->all();
        
        $invoiceEntry['total'] = $invoiceEntry['price']*$invoiceEntry['qty'];
        
        $record = $this->invoiceEntryRepository->updateWithIdAndInput($id, $invoiceEntry);

        return response()->json(['item' => $record]);
    }

    /**
     * Remove the specified Invoice Entry from storage.
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws App\Core\Exceptions\NoPermissionException
     */
    public function destroy($id)
    {
        $this->checkPermissions(['invoice.destroy']);

        $id = (int)$id;
        $this->invoiceEntryRepository->destroy($id);

        return response()->json(['success' => true]);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function getSearch(Request $request)
    {
        $this->checkPermissions(['invoice.index']);

        $searchKey = $request->get('search_key');

        $columns = [
            'invoice_entry.invoice_entry_id',
            'invoice_entry.invoice_id',
            'invoice_entry.entry_short',
            'invoice_entry.entry_long',
            'invoice_entry.qty',
            'invoice_entry.total',
        ];

        $results = $this->invoiceEntryRepository
            ->search($searchKey, $columns);

        return response()->json($results);
    }
}
