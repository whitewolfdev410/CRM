<?php

namespace App\Modules\Invoice\Services;

use App\Core\Exceptions\ValidationException;
use App\Modules\CustomerSettings\Models\CustomerInvoiceSettings;
use App\Modules\ExternalServices\SendInvoiceBatchQueue;
use App\Modules\ExternalServices\SendInvoiceQueue;
use App\Modules\Invoice\Http\Requests\InvoiceRequest;
use App\Modules\File\Models\File;
use App\Modules\Invoice\Jobs\MergeInvoiceWithElectronicSignatureJob;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceEntry;
use App\Modules\Invoice\Repositories\InvoiceEntryRepository;
use App\Modules\Invoice\Repositories\InvoiceRepository;
use App\Modules\Invoice\Services\Builder\Pdf;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\PricingStructure\Services\PricingMatrixService;
use App\Modules\System\Repositories\SystemSettingsRepository;
use App\Modules\TimeSheet\Repositories\TimeSheetRepository;
use App\Modules\WorkOrder\Services\WorkOrderDataService;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    /**
     * @var Container
     */
    protected $app;
    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * @var InvoiceEntryRepository
     */
    protected $invoiceEntryRepo;

    /**
     * Initialize class
     *
     * @param Container              $app
     * @param InvoiceRepository      $invoiceRepo
     * @param InvoiceEntryRepository $invoiceEntryRepo
     */
    public function __construct(
        Container $app,
        InvoiceRepository $invoiceRepo,
        InvoiceEntryRepository $invoiceEntryRepo
    ) {
        $this->app = $app;
        $this->invoiceRepo = $invoiceRepo;
        $this->invoiceEntryRepo = $invoiceEntryRepo;
    }

    /**
     * Detach any related invoice entries from invoice
     *
     * @param Invoice $invoice
     */
    public function detachEntries(Invoice $invoice)
    {
        $invoiceEntries = $invoice->entries;

        /** @var InvoiceEntry $invoiceEntry */
        foreach ($invoiceEntries as $invoiceEntry) {
            $this->invoiceEntryRepo->detach($invoiceEntry);
            $this->detachLinkedRecords($invoiceEntry);
        }
    }

    /**
     * Detach records related to invoice entry
     *
     * @param InvoiceEntry $invoiceEntry
     */
    public function detachLinkedRecords(InvoiceEntry $invoiceEntry)
    {
        $this->invoiceEntryRepo->detachLinkedRecords($invoiceEntry);
    }

    /**
     * @param SendInvoiceQueue      $sendInvoiceQueue
     * @param SendInvoiceBatchQueue $sendInvoiceBatchQueue
     *
     * @return mixed|string|null
     */
    public function getSendingSince(SendInvoiceQueue $sendInvoiceQueue, SendInvoiceBatchQueue $sendInvoiceBatchQueue)
    {
        $sendingSinceSendInvoiceQueue = $sendInvoiceQueue->getRunningSendingSince();
        $sendingSinceSendBatchInvoicesQueue = $sendInvoiceBatchQueue->getRunningSendingJob();

        if ($sendingSinceSendInvoiceQueue) {
            $sendingSince = $sendingSinceSendInvoiceQueue;
        } elseif ($sendingSinceSendBatchInvoicesQueue) {
            $sendingSince = $sendingSinceSendBatchInvoicesQueue;
        } else {
            $sendingSince = null;
        }

        return $sendingSince;
    }

    /**
     * @param array $invoicesIds
     *
     * @return array
     */
    public function groupInvoicesBySendingSystem($invoicesIds)
    {
        $lobInvoices = [];
        $otherInvoices = [];
        if ($invoicesIds) {
            foreach ($invoicesIds as $invoiceId) {
                $invoiceObject = $this->invoiceRepo->find($invoiceId);
                if (!$invoiceObject) {
                    continue;
                }
                $customerInvoiceSettings = CustomerInvoiceSettings::where(
                    'company_person_id',
                    $invoiceObject->person_id
                )->first();
                if ($customerInvoiceSettings && ($customerInvoiceSettings->delivery_method === 'mail')
                    && ($customerInvoiceSettings->active == 1)) {
                    $lobInvoices[] = $invoiceObject;
                } else {
                    $otherInvoices[] = $invoiceId;
                }
            }
        }

        return [
            'lob_invoices'      => $lobInvoices,
            'selenium_invoices' => $otherInvoices
        ];
    }

    public function pdfGenerator($invoiceId)
    {
        return app(Pdf::class)->create($invoiceId);
    }

    /**
     * @param  InvoiceRequest  $invoiceRequest
     *
     * @throws ValidationException
     */
    public function validateMinQtyForEntry(InvoiceRequest $invoiceRequest)
    {
        /** @var PricingMatrixService $pricingMatrixService */
        $pricingMatrixService = app(PricingMatrixService::class);

        $pricingList = $pricingMatrixService->getMinimumQtyListByWorkOrderId($invoiceRequest->get('work_order_id'));
        if ($pricingList) {
            $errors = [];
            
            $entries = $invoiceRequest->get('entries');
            foreach ($entries as $index => $entry) {
                if (empty($entry['item_id']) && empty($entry['service_id'])) {
                    continue;
                }

                $minQty = null;
                if (!empty($entry['item_id']) && isset($pricingList['items'][$entry['item_id']])) {
                    $minQty = $pricingList['items'][$entry['item_id']];
                }

                if (!empty($entry['service_id']) && isset($pricingList['services'][$entry['service_id']])) {
                    $minQty = $pricingList['services'][$entry['service_id']];
                }
                
                if ($minQty && (float)$minQty > (float)$entry['qty']) {
                    $errors["entries.{$index}.qty"] = [
                        "The entries.{$index}.qty field value is less than the minimum value {$minQty}"
                    ];
                }
            }
            
            if ($errors) {
                /** @var ValidationException $validationException */
                $validationException = app(ValidationException::class);
                $validationException->setFields($errors);
                
                throw $validationException;
            }
        }
    }

    public function validateTimeSheetInvoiceEntryId(InvoiceRequest $invoiceRequest)
    {
        $timeSheetIds = [];
        $entries = $invoiceRequest->get('entries');
        foreach ($entries as $index => $entry) {
            if (!empty($entry['time_sheet_id'])) {
                $timeSheetIds[$entry['time_sheet_id']] = $index;
            }
        }
        
        if ($timeSheetIds) {
            $this->validateTimeSheetByIds($timeSheetIds);
        }

        //for time sheets combined
        $timeSheetIds = [];
        foreach ($entries as $index => $entry) {
            if (!empty($entry['time_sheet_ids'])) {
                foreach ($entry['time_sheet_ids'] as $timeSheetId) {
                    $timeSheetIds[$timeSheetId] = $index;
                }
            }
        }

        if ($timeSheetIds) {
            $this->validateTimeSheetByIds($timeSheetIds, 'time_sheet_ids');
        }
    }

    /**
     * @param $timeSheetIds
     * @param  string  $property
     *
     * @throws ValidationException
     */
    private function validateTimeSheetByIds($timeSheetIds, $property = 'time_sheet_id')
    {
        /** @var TimeSheetRepository $timeSheetRepository */
        $timeSheetRepository = app(TimeSheetRepository::class);
        $timeSheetEntries = $timeSheetRepository->getTimeSheetsWithInvoice(array_keys($timeSheetIds));

        if ($timeSheetEntries) {
            $errors = [];

            foreach ($timeSheetEntries as $timeSheetId => $invoice) {
                $index = $timeSheetIds[$timeSheetId];

                $errors["entries.{$index}.{$property}"] = [
                    "The entries.{$index}.{$property} ({$timeSheetId}) is already assigned to the invoice {$invoice}"
                ];
            }

            if ($errors) {
                /** @var ValidationException $validationException */
                $validationException = app(ValidationException::class);
                $validationException->setFields($errors);

                throw $validationException;
            }
        }
    }

    /**
     * Merge invoice with electronic signature and save as img
     *
     * @param  File  $file
     */
    public function mergeInvoiceWithElectronicSignature(File $file)
    {
        /** @var WorkOrderDataService $workOrderDataService */
        $workOrderDataService = app(WorkOrderDataService::class);

        $workOrder = $workOrderDataService->getWorkOrderByFile($file);
        if ($workOrder) {
            $job = new MergeInvoiceWithElectronicSignatureJob($workOrder, $file);

            $result = $this->addDownloadInvoiceToQueue($workOrder->getWorkOrderNumber(), $workOrder->getCompanyPersonId());
            if ($result) {
                $job->delay(1800);
            }

            /** @var Dispatcher $dispatcher */
            $dispatcher = app(Dispatcher::class);
            $dispatcher->dispatch($job);
        } else {
            Log::warning('Cannot find work order for merge the invoice with electronic signature', [
                'file_id' => $file->getId(),
                'table_name' => $file->getTableName(),
                'table_id' => $file->getTableId()
            ]);
        }
    }

    /**
     * @param $workOrderNumber
     *
     * @return false|string
     */
    public static function getPdfInvoicePath($workOrderNumber)
    {
        $invoiceFile = config('invoice_letter.invoice_pdf_folder') . DIRECTORY_SEPARATOR . $workOrderNumber . '.pdf';
        if (file_exists($invoiceFile)) {
            return $invoiceFile;
        }

        return false;
    }

    /**
     * @param  string  $workOrderNumber
     * @param $companyPersonId
     *
     * @return bool
     */
    private function addDownloadInvoiceToQueue($workOrderNumber, $companyPersonId)
    {
        if (self::getPdfInvoicePath($workOrderNumber)) {
            return false;
        } else {
            $existingId = DB::table('letter_invoice')
                ->where('invoice_number', $workOrderNumber)
                ->where('invoice_id', 0)
                ->value('id');

            if ($existingId) {
                DB::table('letter_invoice')
                    ->where('id', $existingId)
                    ->update(['has_pdf' => 0, 'success' => 0, 'sent_at' => null]);
            } else {
                DB::table('letter_invoice')
                    ->insert([
                        'invoice_id'        => 0,
                        'invoice_number'    => $workOrderNumber,
                        'company_person_id' => $companyPersonId,
                        'has_pdf'           => 0,
                        'created_at'        => Carbon::now()
                    ]);
            }

            return true;
        }
    }
}
