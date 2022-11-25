<?php

namespace App\Modules\Invoice\Services;

use App\Core\Crm;
use App\Core\Trans;
use App\Modules\Invoice\Exceptions\InvalidQuoteWorkOrderException;
use App\Modules\Invoice\Exceptions\InvoiceMissingServicesException;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceEntry;
use App\Modules\Invoice\Repositories\InvoiceEntryFromQuoteRepository;
use App\Modules\Invoice\Repositories\InvoiceRepository;
use App\Modules\Quote\Models\Quote;
use App\Modules\Quote\Models\QuoteEntry;
use App\Modules\Quote\Repositories\QuoteRepository;
use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceCloneFromQuoteService
{
    /**
     * Time sheet repository
     *
     * @var InvoiceRepository
     */
    protected $invRepo;

    /**
     * App
     *
     * @var Container
     */
    protected $app;

    /**
     * Config
     *
     * @var mixed
     */
    protected $config;

    /**
     * Translate class
     *
     * @var Trans
     */
    protected $trans;

    /**
     * Initialize fields
     *
     * @param InvoiceRepository $invRepo
     * @param Container         $app
     */
    public function __construct(InvoiceRepository $invRepo, Container $app)
    {
        $this->invRepo = $invRepo;
        $this->app = $app;
        $this->config = $app->config;

        $this->trans = $app->make(Trans::class);
    }

    /**
     * Create new invoice from quote
     *
     * @param int $quoteId
     *
     * @return Invoice
     *
     * @throws \App\Modules\Invoice\Exceptions\InvalidQuoteWorkOrderException
     * @throws \App\Modules\Invoice\Exceptions\InvoiceMissingServicesException
     */
    public function create($quoteId)
    {
        //$response = [];
        /** @var WorkOrderRepository $woRepo */
        $woRepo = $this->getRepository('WorkOrder');

        // getting quote and work order
        $quote = $this->getQuoteWithEntries($quoteId);
        $wo = null;
        if ($quote && $quote->getTableName() == 'work_order') {
            /** @var WorkOrder $wo */
            $wo = $woRepo->findSoft($quote->getTableId());
        }

        // no work order - we won't process
        if (!$wo) {
            throw $this->app->make(InvalidQuoteWorkOrderException::class);
        }

        // get required services and trade type name
        $tradeTypeName = $this->getTradeTypeName($wo);
        $services = $this->getServices($tradeTypeName);

        // not all services exists - we won't process
        $errors = $this->checkServicesErrors($services);
        if ($errors) {
            $exception = $this->app->make(InvoiceMissingServicesException::class);
            $exception->setData(['missing_services_names' => $errors]);

            throw $exception;
        }

        $serviceDates = $this->getTimeSheetDates($wo);
        $createdDate = $this->getCompletedPendingDate($wo);
        $dueDate = $this->getDueDate($wo, $createdDate);

        /** @var Invoice $invoice */
        $invoice = null;

        DB::transaction(function () use (
            $createdDate,
            $dueDate,
            $wo,
            $quote,
            $serviceDates,
            $services,
            $tradeTypeName,
            $woRepo,
            &$invoice
        ) {
            /** @var InvoiceRepository $invoiceRepo */
            $invoiceRepo = $this->getRepository('Invoice');

            /** @var InvoiceEntryFromQuoteRepository $invoiceEntryRepo */
            $invoiceEntryRepo = $this->getRepository(
                'InvoiceEntryFromQuote',
                'Invoice'
            );

            // create invoice from quote
            $invoice = $invoiceRepo->createNewInvoiceFromQuote(
                $wo,
                $createdDate,
                $dueDate
            );

            $sortOrder = 0;

            // create description invoice entry from quote
            $descriptionServiceID = $services['Description'];
            $invoiceEntryRepo->createDescFromQuote(
                $invoice,
                $wo,
                $quote,
                $descriptionServiceID,
                $sortOrder
            );
            ++$sortOrder;

            // getting quote entries
            $quoteEntries = $this->getQuoteEntries($quote, $wo);

            // create all invoice entries from quote entries
            $laborSort = $this->addInvoiceEntriesFromQuoteEntries(
                $quoteEntries,
                $invoice,
                $services,
                $serviceDates,
                $wo,
                $invoiceEntryRepo,
                $tradeTypeName,
                $sortOrder
            );

            // create spare invoice entries
            /** @var Crm $crm */
            $crm = $this->app->make('crm');
            if ($crm->is(['clm', 'rgl'])) {
                $serviceID = $services[$tradeTypeName];
                $this->addInvoiceSpareEntries(
                    $invoice,
                    $serviceDates,
                    $laborSort,
                    $wo,
                    $invoiceEntryRepo,
                    $serviceID
                );
            }

            /**  @var TypeRepository $typeR */
            $typeR = $this->getRepository('Type');
            /**  @var QuoteRepository $qRepo */
            $qRepo = $this->getRepository('Quote');

            // extra changes after successfully adding invoice with entries
            $qRepo->updateQuoteType(
                $quote,
                $typeR->getIdByKey('quote_status.internal_invoice_created')
            );

            $invoice = $invoiceRepo->changePaidStatus($invoice, 0);
            $woRepo->addInvoice($wo, $invoice);
        });

        // load entries to return invoice with entries
        if ($invoice) {
            $invoice->load('entries');
        }

        return $invoice;
    }

    /**
     * Add invoice spare entries
     *
     * @param Invoice                         $invoice
     * @param array                           $serviceDates
     * @param int                             $laborSort
     * @param WorkOrder                       $wo
     * @param InvoiceEntryFromQuoteRepository $invoiceEntryRepo
     * @param int                             $serviceID
     */
    protected function addInvoiceSpareEntries(
        Invoice $invoice,
        array $serviceDates,
        $laborSort,
        WorkOrder $wo,
        InvoiceEntryFromQuoteRepository $invoiceEntryRepo,
        $serviceID
    ) {
        // create spare invoice entries for each service dates except last one
        $serviceDatesCount = count($serviceDates) - 2;
        array_pop($serviceDates);
        $sortOrder = $laborSort + 1;

        while ($serviceDatesCount >= 0) {
            $invoiceEntries = $invoiceEntryRepo
                ->getForInvoiceWithSortOrder($invoice->getId(), $laborSort);

            /** @var InvoiceEntry $ieToUpdate */
            foreach ($invoiceEntries as $ieToUpdate) {
                $invoiceEntryRepo->setSortOrder(
                    $ieToUpdate,
                    $ieToUpdate->getSortOrder() + 1
                );
            }

            $invoiceEntryRepo->createSpareFromQuote(
                $invoice,
                $wo,
                $serviceID,
                $sortOrder,
                $serviceDates[$serviceDatesCount]
            );
            ++$sortOrder;
            $serviceDatesCount--;
            array_pop($serviceDates);
        }
    }

    /**
     * Add invoice entries from quote entries
     *
     * @param Collection                      $quoteEntries
     * @param Invoice                         $invoice
     * @param array                           $services
     * @param array                           $serviceDates
     * @param WorkOrder                       $wo
     * @param InvoiceEntryFromQuoteRepository $invoiceEntryRepo
     * @param int                             $tradeTypeName
     * @param int                             $sortOrder
     *
     * @return int Labor sort
     */
    protected function addInvoiceEntriesFromQuoteEntries(
        Collection $quoteEntries,
        Invoice $invoice,
        array $services,
        array $serviceDates,
        WorkOrder $wo,
        InvoiceEntryFromQuoteRepository $invoiceEntryRepo,
        $tradeTypeName,
        $sortOrder
    ) {
        $serviceID = $services[$tradeTypeName];
        $travelServiceID = $services['Travel'];
        $equipmentServiceID = $services['Equipment'];
        $markupServiceID = $services['Markup'];
        $materialsServiceID = $services['Materials'];

        $laborSort = 0;

        /** @var QuoteEntry $quoteEntry */
        foreach ($quoteEntries as $quoteEntry) {
            switch ($quoteEntry->getStepName()) {
                /* 'cost' step_name records aren't taken from DB. It was verified
                 *  on 18.04.2014 by R.K. that it should work like this */
                case 'cost':
                    $invoiceEntryRepo->createCostFromQuote(
                        $invoice,
                        $wo,
                        $quoteEntry,
                        $serviceID,
                        $sortOrder
                    );
                    ++$sortOrder;
                    break;

                case 'labor':
                    if ($quoteEntry->getMen()) {
                        if ($laborSort == 0) {
                            $laborSort = $sortOrder;
                        }
                        $invoiceEntryRepo->createLaborFromQuote(
                            $invoice,
                            $wo,
                            $quoteEntry,
                            $serviceID,
                            $sortOrder,
                            $tradeTypeName,
                            $serviceDates
                        );
                        ++$sortOrder;
                    }
                    break;

                case 'materials':
                    if ($quoteEntry->getQty()) {
                        if ($laborSort == 0) {
                            $laborSort = $sortOrder;
                        }
                        $invoiceEntryRepo->createMaterialsFromQuote(
                            $invoice,
                            $wo,
                            $quoteEntry,
                            $markupServiceID,
                            $materialsServiceID,
                            $sortOrder
                        );
                        ++$sortOrder;
                    }
                    break;

                case 'freight':
                    if ($quoteEntry->getTotal()) {
                        $invoiceEntryRepo->createFreightFromQuote(
                            $invoice,
                            $wo,
                            $quoteEntry,
                            $materialsServiceID,
                            $sortOrder
                        );
                        ++$sortOrder;
                    }
                    break;

                case 'tax':
                    if ($quoteEntry->getTotal()) {
                        $invoiceEntryRepo->createTaxFromQuote(
                            $invoice,
                            $wo,
                            $quoteEntry,
                            $materialsServiceID,
                            $sortOrder
                        );
                        ++$sortOrder;
                    }
                    break;

                case 'expenses':
                    if ($quoteEntry->getTrips()) {
                        $invoiceEntryRepo->createExpensesFromQuote(
                            $invoice,
                            $wo,
                            $quoteEntry,
                            $travelServiceID,
                            $equipmentServiceID,
                            $sortOrder
                        );
                        ++$sortOrder;
                    }
                    break;

                default:
                    break;
            }
        }

        return $laborSort;
    }

    /**
     * Get Quote together with quote entries
     *
     * @param int $quoteId
     *
     * @return Quote
     */
    protected function getQuoteWithEntries($quoteId)
    {
        $qRepo = $this->getRepository('Quote');

        return $qRepo->findSoftWithEntries($quoteId, [
            'labor',
            'materials',
            'freight',
            'expenses',
            'tax',
        ]);
    }

    /**
     * Get quote entries (for some clients or in some cases) they might be
     * modified comparing to original quote entries
     *
     * @param Quote     $quote
     * @param WorkOrder $wo
     *
     * @return Collection
     */
    protected function getQuoteEntries(Quote $quote, WorkOrder $wo)
    {
        /* @todo if the code at the end of function will be commented  this
         * function may be simplified or event it might be removed */

        // Remove the markup for non-cvs

        $crm = $this->app->make('crm');
        if (!$crm->isClient('cvs', $wo->getCompanyPersonId(), 'clm')) {
            return $quote->entries;
        }

        $entries = $quote->entries;

        /* @todo probably below commented code may be removed - markup is now
         * used in different way - decided to comment on 18.04.2014 by R.K.
         */

        /*        for ($i = 0, $c = count($entries); $i < $c; ++$i) {
            if (!isset($entries[$i])) {
                break;
            }
            if (mb_strpos($entries[$i + 1]->entry_short, "Markup") !== false) {
                // Add the markup to the price of each item
                $entries[$i]->price += $entries[$i + 1]->total
                    / $entries[$i]->qty;

                //remove that markup line
                unset($entries[$i + 1]);

                // Rebase the array so the indexes are sequential
                $entries = array_values($entries);
            }
        }*/

        return $entries;
    }

    /**
     * Get errors of services array (if any)
     *
     * @param array $services
     *
     * @return array
     */
    protected function checkServicesErrors($services)
    {
        $errors = [];
        foreach ($services as $k => $v) {
            if ($v === null) {
                $errors[] = $k;
            }
        }

        return $errors;
    }

    /**
     * Get services list
     *
     * @param string $tradeTypeName
     *
     * @return array
     */
    protected function getServices($tradeTypeName)
    {
        /* @todo very bad - it's based on names - much better would be using
         * again some identifiers as for Type or at least such names should be
         * put into variables/seeds and somehow locked for edit name
         */

        $serviceNames = [
            $tradeTypeName,
            'Travel',
            'Equipment',
            'Markup',
            'Materials',
            'Description',
        ];

        $serviceRepo = $this->getRepository('Service');

        return $serviceRepo->getNamesIdsList($serviceNames);
    }

    /**
     * Get work order trade type name
     *
     * @param WorkOrder $wo
     *
     * @return string
     */
    protected function getTradeTypeName(WorkOrder $wo)
    {
        $t = $this->getRepository('Type');

        return $t->getValueById($wo->getTradeTypeId());
    }

    /**
     * Translate text
     *
     * @param $string
     *
     * @return string
     */
    protected function trans($string)
    {
        return $this->trans->get($string);
    }

    /**
     * Get repository
     *
     * @param string      $repositoryName
     * @param string|null $moduleName
     *
     * @return mixed
     */
    protected function getRepository($repositoryName, $moduleName = null)
    {
        return $this->invRepo->getRepository($repositoryName, $moduleName);
    }

    /**
     * Get time sheets distinct data
     *
     * @param WorkOrder $wo
     *
     * @return array
     */
    protected function getTimeSheetDates(WorkOrder $wo)
    {
        $ts = $this->getRepository('TimeSheet');

        return $ts->getDistinctDatesListForWo($wo->getId());
    }

    /**
     * Get date when work order status has been changed to completed pending
     * or current date if status hasn't been changed to completed pending
     *
     * @param WorkOrder $wo
     *
     * @return Carbon
     */
    protected function getCompletedPendingDate(WorkOrder $wo)
    {
        $ce = $this->getRepository('CalendarEvent');

        $createdDate = $ce->getCompletedPendingDateForWo($wo->getId());

        if ($createdDate === null) {
            $createdDate = Carbon::now();
        }

        // @todo READ LIVE (TODO FROM OLD CRM)

        return $createdDate;
    }

    /**
     * Get due date
     *
     * @param WorkOrder $wo
     * @param  Carbon   $createdDate
     *
     * @return Carbon
     */
    protected function getDueDate(WorkOrder $wo, Carbon $createdDate)
    {
        $cs = $this->getRepository('CustomerSettings');

        return $cs->getDueDate($wo, $createdDate);
    }
}
