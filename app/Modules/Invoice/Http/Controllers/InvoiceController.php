<?php

namespace App\Modules\Invoice\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\NoPermissionException;
use App\Core\Exceptions\NoSomePermissionException;
use App\Http\Controllers\Controller;
use App\Jobs\QueuedJobManager;
use App\Modules\Address\Repositories\AddressRepository;
use App\Modules\ClientPortal\Services\DocumentFileService;
use App\Modules\CustomerSettings\Models\CustomerInvoiceSettings;
use App\Modules\ExternalServices\SendInvoiceBatchQueue;
use App\Modules\ExternalServices\SendInvoiceQueue;
use App\Modules\Invoice\Exceptions\InvalidQuoteWorkOrderException;
use App\Modules\Invoice\Exceptions\InvoiceMissingServicesException;
use App\Modules\Invoice\Http\Requests\InvoiceDescriptionRequest;
use App\Modules\Invoice\Http\Requests\InvoiceRequest;
use App\Modules\Invoice\Http\Requests\InvoicesGroupRequest;
use App\Modules\Invoice\Http\Requests\InvoiceStatusRequest;
use App\Modules\Invoice\Http\Requests\InvoiceStoreForPmRequest;
use App\Modules\Invoice\Http\Requests\InvoiceStoreFromQuoteRequest;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceBatchItem;
use App\Modules\Invoice\Repositories\InvoiceBatchRepository;
use App\Modules\Invoice\Repositories\InvoiceRepository;
use App\Modules\Invoice\Services\InvoiceCloneFromQuoteService;
use App\Modules\Invoice\Services\InvoiceService;
use App\Modules\InvoiceImport\Jobs\ImportInvoices;
use App\Modules\Item\Repositories\ItemRepository;
use App\Modules\Queue\Models\QueuedJob;
use App\Modules\Service\Repositories\ServiceRepository;
use App\Modules\Type\Repositories\TypeMemcachedRepository;
use Exception;
use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Modules\InvoiceDelivery\InvoiceDeliveryStats;

/**
 * Class InvoiceController
 *
 * @package App\Modules\Invoice\Http\Controllers
 */
class InvoiceController extends Controller
{
    /**
     * Invoice repository
     *
     * @var InvoiceRepository
     */
    private $invoiceRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param InvoiceRepository $invoiceRepository
     */
    public function __construct(InvoiceRepository $invoiceRepository)
    {
        $this->middleware('auth');
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Return list of Invoice
     *
     * @param Config  $config
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws InvalidArgumentException
     * @throws NoPermissionException
     */
    public function index(
        Config $config,
        Request $request
    ) {
        $this->checkPermissions(['invoice.index']);

        $onPage = (int)$request->get('limit', $config->get('system_settings.invoice_pagination'));

        $list = $this->invoiceRepository
            ->paginate($onPage);

        if ($request->get('with_attempts', false)) {
            $list['attempts'] = QueuedJob
                ::isSendingInvoice()
                ->selectRaw('date(completed_at) as date')
                ->distinct()
                ->orderByDesc('date')
                ->take(10)
                ->get();
        }

        return response()->json($list);
    }

    /**
     * Display the specified Invoice
     *
     * @param int $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function show($id)
    {
        $this->checkPermissions(['invoice.show']);
        $id = (int)$id;

        return response()->json($this->invoiceRepository->show($id));
    }

    /**
     * Return module configuration for store action
     *
     * @param ItemRepository    $itemRepository
     * @param ServiceRepository $serviceRepository
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function create(
        ItemRepository $itemRepository,
        ServiceRepository $serviceRepository
    ) {
        $this->checkPermissions(['invoice.store']);
        $rules['fields'] = $this->invoiceRepository->getRequestRules();

        $rules['items'] = $itemRepository->all();
        $rules['services'] = $serviceRepository->getEnabledServices();

        return response()->json($rules);
    }

    /**
     * Store a newly created Invoice in storage.
     *
     * @param  InvoiceRequest  $request
     * @param  InvoiceService  $invoiceService
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function store(InvoiceRequest $request, InvoiceService $invoiceService)
    {
        $this->checkPermissions(['invoice.store']);
        
        $invoiceService->validateMinQtyForEntry($request);
        $invoiceService->validateTimeSheetInvoiceEntryId($request);
        
        $model = $this->invoiceRepository->create($request->all());

        return response()->json(['item' => $model], 201);
    }

    /**
     * Display Invoice and module configuration for update action
     *
     * @param int $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function edit($id)
    {
        $this->checkPermissions(['invoice.update']);
        $id = (int)$id;

        return response()->json($this->invoiceRepository->show($id, true));
    }

    /**
     * Update the specified Invoice in storage.
     *
     * @param  InvoiceRequest  $request
     * @param  InvoiceService  $invoiceService
     * @param  int  $id
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function update(InvoiceRequest $request, InvoiceService $invoiceService, $id)
    {
        $this->checkPermissions(['invoice.update']);

        $invoiceService->validateMinQtyForEntry($request);
        
        $record = $this->invoiceRepository->updateWithIdAndInput((int)$id, $request->all());

        return response()->json(['item' => $record]);
    }

    /**
     * Remove the specified Invoice from storage.
     *
     * @param int $id
     *
     * @return JsonResponse
     *
     * @throws HttpException
     * @throws NoPermissionException
     */
    public function destroy($id)
    {
        $this->checkPermissions(['invoice.destroy']);
        abort(404);
        exit;

        /* $id = (int) $id;
        $this->invoiceRepository->destroy($id); */
    }

    /**
     * Create new invoice from quote
     *
     * @param InvoiceStoreFromQuoteRequest $request
     * @param Container                    $container
     *
     * @return JsonResponse
     *
     * @throws InvalidQuoteWorkOrderException
     * @throws InvoiceMissingServicesException
     * @throws NoPermissionException
     */
    public function storeFromQuote(
        InvoiceStoreFromQuoteRequest $request,
        Container $container
    ) {
        $this->checkPermissions(['invoice.store-from-quote']);
        $invClone = new InvoiceCloneFromQuoteService($this->invoiceRepository, $container);

        $result = $invClone->create($request->input('quote_id'));

        return response()->json(['item' => $result], 201);
    }

    /**
     * Create new invoice for PM work order
     *
     * @param InvoiceStoreForPmRequest $request
     *
     * @return JsonResponse
     *
     * @throws LogicException
     * @throws NoPermissionException
     */
    public function storeForPm(InvoiceStoreForPmRequest $request)
    {
        $this->checkPermissions(['invoice.store-for-pm']);

        $invoice = $this->invoiceRepository->createForPm($request);

        return response()->json(['item' => $invoice], 201);
    }

    //region Import

    /**
     * Import invoices from zip archive (TWC specific!)
     *
     * @param QueuedJobManager $manager
     * @param Request          $request
     * @param int              $id
     *
     * @return JsonResponse
     *
     * @throws Exception
     * @throws NoPermissionException
     */
    public function import(
        QueuedJobManager $manager,
        $id
    ) {
        $this->checkPermissions(['invoice.store-from-import']);

        $job = new ImportInvoices($id);

        $tracking = $manager->queue($job);

        return response()->json([
            'message'  => 'Invoices are being imported...',
            'tracking' => $tracking,
        ]);
    }

    //endregion

    //region Send

    /**
     * Sends all invoices.
     *
     * @param Request               $request
     * @param SendInvoiceQueue      $sendInvoiceQueue
     *
     * @param SendInvoiceBatchQueue $sendInvoiceBatchQueue
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function send(
        Request $request,
        SendInvoiceQueue $sendInvoiceQueue,
        SendInvoiceBatchQueue $sendInvoiceBatchQueue
    ) {
        $this->checkPermissions(['invoice.send']);

        if ($sendingSince = $sendInvoiceQueue->getRunningSendingSince()) {
            return response()->json([
                'sending_since' => $sendingSince,
            ]);
        }

        if ($sendingSince = $sendInvoiceBatchQueue->getRunningSendingJob()) {
            return response()->json([
                'sending_since' => $sendingSince,
            ]);
        }

        $invoicesIds = $request->get('invoices', null);
        if (!$invoicesIds) {
            $sendInvoiceQueue->queueSendingAll(null);
            $batchQueueResult = $sendInvoiceBatchQueue->queueSendingAll(null);
        } else {
            $groupedInvoices = app(InvoiceService::class)->groupInvoicesBySendingSystem($invoicesIds);

            if ($groupedInvoices['lob_invoices']) {
                $batchQueueResult = $sendInvoiceBatchQueue->queueSendingAll($groupedInvoices['lob_invoices']);
            }
            if ($groupedInvoices['selenium_invoices']) {
                $sendInvoiceQueue->queueSendingAll($groupedInvoices['selenium_invoices']);
            }
        }

        if (isset($batchQueueResult) && $batchQueueResult && $batchQueueResult['rejected_batch_invoices']) {
            $errors = $batchQueueResult['rejected_batch_invoices'];
        }
        $sendingSince = app(InvoiceService::class)->getSendingSince($sendInvoiceQueue, $sendInvoiceBatchQueue);

        return response()->json([
            'sending_since' => $sendingSince,
            'errors'        => isset($errors) ? $errors : null
        ]);
    }

    /**
     * Gets sending status
     *
     * @param SendInvoiceQueue      $sendInvoiceQueue
     *
     * @param SendInvoiceBatchQueue $sendInvoiceBatchQueue
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function sendingStatus(
        SendInvoiceQueue $sendInvoiceQueue,
        SendInvoiceBatchQueue $sendInvoiceBatchQueue
    ) {
        $this->checkPermissions(['invoice.send']);

        $sendingSince = app(InvoiceService::class)->getSendingSince($sendInvoiceQueue, $sendInvoiceBatchQueue);

        return response()->json([
            'sending_since' => $sendingSince,
        ]);
    }

    //endregion

    /**
     * Get invoices by ids and group them by company name
     *
     * @param InvoicesGroupRequest    $request
     * @param TypeMemcachedRepository $types
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function groupInvoices(
        InvoicesGroupRequest $request,
        TypeMemcachedRepository $types
    ) {
        $this->checkPermissions(['invoice.group']);

        /** @var Builder $invoicesQuery */
        $invoicesQuery = Invoice::select(
            'invoice.invoice_id',
            'invoice.invoice_number',
            'invoice.status_type_id',
            'person.custom_1 as company_name',
            'invoice.date_invoice',
            'invoice.person_id as company_id',
            DB::raw('sum(invoice_entry.total) AS total'),
            DB::raw('sum(invoice_entry.tax_amount) AS tax_amount')
        );
        //At first if invoices ids have sent
        if ($request->get('invoices')) {
            $invoicesQuery->whereIn('invoice.invoice_id', $request->get('invoices'));//invoices with ids from request
        } else { //If no invoices send then get all approved invoices
            $typeId = $types->getIdByKey('invoice_status.internal_approved');
            $invoicesQuery->where('invoice.status_type_id', $typeId); //only invoices with this status type
        }

        $invoicesQuery->leftJoin('invoice_entry', function ($join) {
            /** @var \Illuminate\Database\Query\JoinClause $join */
            $join->on('invoice_entry.invoice_id', '=', 'invoice.invoice_id');
        });

        /** @var Collection|Invoice[] $invoices */
        $invoices = $invoicesQuery
            ->join('person', 'person.person_id', '=', 'invoice.person_id')//join with person to get company name
            ->groupBy('invoice.invoice_id')
            ->get();

        //Check if invoices have already sent today - if yes then block sending
        $alreadySent = [];
        $sentStatusId = (int)app(TypeMemcachedRepository::class)->getIdByKey('invoice_status.sent');
        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            if ($invoice->getStatusTypeId() === $sentStatusId) {
                //Check in invoices batch items (e.g LOB)
                $invoiceSendToday = InvoiceBatchItem::where('invoice_id', '=', $invoice->getId())
                    ->whereRaw('DATE(created_at) = CURDATE()')
                    ->count();
                //If not then check in queued job table
                if (!$invoiceSendToday) {
                    $invoiceSendToday = QueuedJob::where('record_id', '=', $invoice->getId())
                        ->havingRaw('DATE(MAX(completed_at)) = CURDATE()')
                        ->count();
                }
                //Add to already sent table
                if ($invoiceSendToday) {
                    $alreadySent[] = [
                        'invoice' => $invoice->toArray(),
                        'msg'     => 'Invoice <b>' . $invoice->getInvoiceNumber() . '</b> has already sent today and cannot be send again.',
                    ];
                }
            }
        }

        $groupedInvoices = [];
        $warnings = [];
        //Group invoices by company name field
        $invoices = array_group_by($invoices->toArray(), 'company_name');
        //Check if companies has set main address, otherwise add info to warnings table
        if (!$alreadySent) {
            /**
             * @var string    $companyName
             * @var Invoice[] $items
             */
            foreach ($invoices as $companyName => $items) {
                if (isset($items[0]['company_id'])) {
                    $companyId = $items[0]['company_id'];
                    $customerInvoiceSettings = CustomerInvoiceSettings::where('company_person_id', $companyId)->first();
                    if ($customerInvoiceSettings && ($customerInvoiceSettings->delivery_method === 'mail')) {
                        $mainAddress = app(AddressRepository::class)->getMainOficeAddress($companyId);
                        if (!$mainAddress) {
                            $warnings[] = [
                                'invoices' => [], // Warning is related to company no to invoice.
                                'msg'      => 'Company <b>' . $companyName . '</b> has no set main address. Please add main company office first.',
                            ];
                        }
                        $files = [];
                        $invoicesWithMissingFiles = [];
                        foreach ($items as $item) {
                            $file = $this->invoiceRepository->getInvoiceFilePath($item['id']);
                            if (!$file) {
                                $invoicesWithMissingFiles[] = $item;
                            } else {
                                $files[] = $file;
                            }
                        }
                        if ($files) {
                            $pdfUrl = app(DocumentFileService::class)->getMergedInvoicesFile($files);
                        }
                        if ($invoicesWithMissingFiles) {
                            $warnings[] = [
                                'invoices' => $invoicesWithMissingFiles,
                                'msg'      => 'Invoices <b>' . $companyName . '</b> has no invoice document and below invoices can not be sent:',
                            ];
                        }
                    }
                }
                $groupedInvoices[$companyName] = [
                    'invoices' => $items,
                    'pdf'      => isset($pdfUrl) ? $pdfUrl : null
                ];
            }
        }

        return response()->json([
            'invoices'    => $groupedInvoices,
            'alreadySent' => $alreadySent,
            'warnings'    => $warnings,
        ]);
    }

    /**
     * Get invices batches paginator
     *
     * @param Request                $request
     * @param Config                 $config
     * @param InvoiceBatchRepository $batchRepository
     *
     * @return JsonResponse
     *
     * @throws InvalidArgumentException
     * @throws NoPermissionException
     */
    public function getBatches(
        Request $request,
        Config $config,
        InvoiceBatchRepository $batchRepository
    ) {
        $this->checkPermissions(['invoice.batches-list']);

        $onPage = (int)$request->get('limit', $config->get('system_settings.invoice_pagination'));
        $list = $batchRepository
            ->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Get batch data
     *
     * @param Request                $request
     * @param InvoiceBatchRepository $batchRepository
     * @param                        $id
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function getBatch(
        Request $request,
        InvoiceBatchRepository $batchRepository,
        $id
    ) {
        $this->checkPermissions(['invoice.batches-get']);

        $batch = $batchRepository->getBatch($id);

        return response()->json($batch);
    }

    /**
     * Get batches statuses list
     *
     * @param InvoiceBatchRepository $batchRepository
     *
     * @return JsonResponse
     */
    public function getBatchesStatuses(InvoiceBatchRepository $batchRepository)
    {
        $types = $batchRepository->getBatchesStatuses();

        return response()->json([
            'statuses' => $types,
        ]);
    }

    /**
     * Update invoice status
     *
     * @param InvoiceStatusRequest $request
     * @param                      $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function updateStatus(InvoiceStatusRequest $request, $id)
    {
        $this->checkPermissions(['invoice.update']);
        $id = (int)$id;

        $record = $this->invoiceRepository->updateWithIdAndInput($id, $request->all());

        return response()->json(['item' => $record]);
    }

    /**
     * Update invoice status
     *
     * @param InvoiceDescriptionRequest $request
     * @param                      $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function updateDescription(InvoiceDescriptionRequest $request, $id)
    {
        $this->checkPermissions(['invoice.update']);
        $id = (int)$id;

        $record = $this->invoiceRepository->updateWithIdAndInput($id, $request->only(['customer_request_description']));

        return response()->json(['item' => $record]);
    }

    
    /**
     * Get activities for invoice
     *
     * @param int     $id
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoSomePermissionException
     */
    public function activities($id, Request $request)
    {
        $permissions = [
            'invoice.activities'           => '',
            'invoice.activities-self-only' => '',
        ];

        // verify if user has permission to any of above permissions
        $statuses = $this->getPermissionsStatus($permissions);

        if (!in_array(true, $statuses)) {
            // user has no permission
            $exp = App::make(NoSomePermissionException::class);
            $exp->setData(['permissions' => $permissions]);
            throw $exp;
        }

        $data = $this->invoiceRepository->getActivities($id, $request->all());

        return response()->json($data);
    }

    /**
     * Get services for invoice
     *
     * @return JsonResponse
     */
    public function services()
    {
        $data = [];
        $services = config('external_services.services', []);

        foreach ($services as $serviceName => $conf) {
            $data[] = [
                'label' => $conf['settings_name'] ?? $serviceName,
                'value' => $serviceName
            ];
        }

        usort($data, function ($a, $b) {
            if ($a['value'] == $b['value']) {
                return 0;
            }
            return ($a['value'] < $b['value']) ? -1 : 1;
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Export list of Invoices to Excel
     *
     * @param Config  $config
     * @param Request $request
     *
     * @return Response
     *
     * @throws InvalidArgumentException
     * @throws NoPermissionException
     */
    public function exportExcel(
        Config $config,
        Request $request
    ) {
        $this->checkPermissions(['invoice.index']);

        $onPage = (int)$request->get('limit', $config->get('system_settings.invoice_pagination'));

        $data = $this->invoiceRepository->exportExcel($onPage);
        $fileName = $data['name'];
        $fileContent = $data['fileContent'];

        return response(
            $fileContent,
            200,
            [
                'Access-Control-Expose-Headers' => 'File-Name',
                'Content-Type'                  => 'application/vnd.ms-excel',
                'Content-Length'                => strlen($fileContent),
                'Content-Disposition'           => 'attachment; filename="' . $fileName . '"',
                'Cache-Control'                 => '',
                'File-Name'                     => $fileName,
                'Pragma'                        => '',
            ]
        );
    }
    
    public function pdf($id, InvoiceService $invoiceService)
    {
//        $this->checkPermissions(['invoice.pdf']);

        $invoiceService->pdfGenerator($id);
    }

    /**
     * Get invoice delivery stats
     * @param  Request $request
     * @return Response
     */
    public function deliveryStats(Request $request)
    {
        $date = $request['date'];
        $stats = InvoiceDeliveryStats::createForBfc()->getMonthly($date);

        return response()->json(['stats' => $stats]);
    }
}
