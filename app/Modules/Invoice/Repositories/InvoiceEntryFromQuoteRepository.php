<?php

namespace App\Modules\Invoice\Repositories;

use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceEntry;
use App\Modules\Quote\Models\Quote;
use App\Modules\Quote\Models\QuoteEntry;
use App\Modules\WorkOrder\Models\WorkOrder;
use Illuminate\Support\Collection;

class InvoiceEntryFromQuoteRepository extends InvoiceEntryRepository
{
    /**
     * Create description invoice entry
     *
     * @param Invoice $invoice
     * @param WorkOrder $wo
     * @param Quote $quote
     * @param int $serviceID
     * @param int $sortOrder
     *
     * @return InvoiceEntry
     */
    public function createDescFromQuote(
        Invoice $invoice,
        WorkOrder $wo,
        Quote $quote,
        $serviceID,
        $sortOrder
    ) {
        $invoiceEntry = $this->newInstance();
        $invoiceEntry = $this->setCommonEntryFromQuoteFields(
            $invoiceEntry,
            $invoice,
            $wo,
            $sortOrder,
            $serviceID
        );

        $invoiceEntry->entry_short = $quote->getDescription();
        $invoiceEntry->entry_long = $invoiceEntry->entry_short;
        $invoiceEntry->qty = '';
        $invoiceEntry->price = '';
        $invoiceEntry->total = '';
        $invoiceEntry->save();

        return $invoiceEntry;
    }

    /**
     * Create cost invoice entry
     *
     * @param Invoice $invoice
     * @param WorkOrder $wo
     * @param QuoteEntry $quoteEntry
     * @param int $serviceID
     * @param int $sortOrder
     *
     * @return InvoiceEntry
     */
    public function createCostFromQuote(
        Invoice $invoice,
        WorkOrder $wo,
        QuoteEntry $quoteEntry,
        $serviceID,
        $sortOrder
    ) {
        $invoiceEntry = $this->newInstance();
        $invoiceEntry = $this->setCommonEntryFromQuoteFields(
            $invoiceEntry,
            $invoice,
            $wo,
            $sortOrder,
            $serviceID
        );

        // description
        $invoiceEntry->entry_short
            = 'Costs incurred to date( ' . $quoteEntry->getDesc() . ')';
        $invoiceEntry->entry_long = $invoiceEntry->entry_short;

        // other fields
        $invoiceEntry->qty = 1;
        $invoiceEntry->price = $quoteEntry->getTotal();
        $invoiceEntry->total = $quoteEntry->getTotal();
        $invoiceEntry->entry_date = date('Y-m-d');
        $invoiceEntry->save();

        return $invoiceEntry;
    }

    /**
     * Create labor invoice entry
     *
     * @param Invoice $invoice
     * @param WorkOrder $wo
     * @param QuoteEntry $quoteEntry
     * @param int $serviceID
     * @param int $sortOrder
     * @param int $tradeTypeName
     * @param array $serviceDates
     *
     * @return InvoiceEntry
     */
    public function createLaborFromQuote(
        Invoice $invoice,
        WorkOrder $wo,
        QuoteEntry $quoteEntry,
        $serviceID,
        $sortOrder,
        $tradeTypeName,
        array $serviceDates
    ) {
        $invoiceEntry = $this->newInstance();
        $invoiceEntry = $this->setCommonEntryFromQuoteFields(
            $invoiceEntry,
            $invoice,
            $wo,
            $sortOrder,
            $serviceID
        );

        // description
        $desc = str_replace('Helper', '', $quoteEntry->getDesc());
        $invoiceEntry->entry_short = $tradeTypeName . ' ' . $desc;
        if ($quoteEntry->getMen() > 1) {
            $invoiceEntry->entry_short .= ' (' . $quoteEntry->getMen()
                . ' men labor)';
        }
        $invoiceEntry->entry_long = $invoiceEntry->entry_short;

        // other fields
        $invoiceEntry->qty = $quoteEntry->getMen() * $quoteEntry->getHrs();
        $invoiceEntry->price = $quoteEntry->getRate();
        $invoiceEntry->total = $quoteEntry->getTotal();
        if ($serviceDates) {
            $invoiceEntry->entry_date = end($serviceDates);
        }

        $invoiceEntry->save();

        return $invoiceEntry;
    }

    /**
     * Create materials invoice entry
     *
     * @param Invoice $invoice
     * @param WorkOrder $wo
     * @param QuoteEntry $quoteEntry
     * @param int $markupServiceID
     * @param int $materialsServiceID
     * @param int $sortOrder
     *
     * @return InvoiceEntry
     */
    public function createMaterialsFromQuote(
        Invoice $invoice,
        WorkOrder $wo,
        QuoteEntry $quoteEntry,
        $markupServiceID,
        $materialsServiceID,
        $sortOrder
    ) {
        $serviceID = $materialsServiceID;
        if (mb_strpos($quoteEntry->getDesc(), 'Markup') !== false
            || mb_strpos($quoteEntry->getDesc(), 'Mark up') !== false
        ) {
            $serviceID = $markupServiceID;
        }

        $invoiceEntry = $this->newInstance();
        $invoiceEntry = $this->setCommonEntryFromQuoteFields(
            $invoiceEntry,
            $invoice,
            $wo,
            $sortOrder,
            $serviceID,
            $quoteEntry
        );

        $invoiceEntry->qty = $quoteEntry->getQty() * $quoteEntry->getUnit();
        $invoiceEntry->price
            = $quoteEntry->getPrice() + (($quoteEntry->getPrice()
                    * $quoteEntry->getMarkup()) / 100);
        $invoiceEntry->total = $quoteEntry->getTotal();
        $invoiceEntry->save();

        return $invoiceEntry;
    }

    /**
     * Create freight invoice entry
     *
     * @param Invoice $invoice
     * @param WorkOrder $wo
     * @param QuoteEntry $quoteEntry
     * @param int $serviceID
     * @param int $sortOrder
     *
     * @return InvoiceEntry
     */
    public function createFreightFromQuote(
        Invoice $invoice,
        WorkOrder $wo,
        QuoteEntry $quoteEntry,
        $serviceID,
        $sortOrder
    ) {
        $invoiceEntry = $this->newInstance();
        $invoiceEntry = $this->setCommonEntryFromQuoteFields(
            $invoiceEntry,
            $invoice,
            $wo,
            $sortOrder,
            $serviceID,
            $quoteEntry
        );

        $invoiceEntry->qty = 1;
        $invoiceEntry->price = $quoteEntry->getTotal();
        $invoiceEntry->total = $quoteEntry->getTotal();
        $invoiceEntry->save();

        return $invoiceEntry;
    }

    /**
     * Create tax invoice entry
     *
     * @param Invoice $invoice
     * @param WorkOrder $wo
     * @param QuoteEntry $quoteEntry
     * @param int $serviceID
     * @param int $sortOrder
     *
     * @return InvoiceEntry
     */
    public function createTaxFromQuote(
        Invoice $invoice,
        WorkOrder $wo,
        QuoteEntry $quoteEntry,
        $serviceID,
        $sortOrder
    ) {
        $invoiceEntry = $this->newInstance();
        $invoiceEntry = $this->setCommonEntryFromQuoteFields(
            $invoiceEntry,
            $invoice,
            $wo,
            $sortOrder,
            $serviceID,
            $quoteEntry
        );

        $invoiceEntry->qty = 1;
        $invoiceEntry->price = $quoteEntry->getTotal();
        $invoiceEntry->total = $quoteEntry->getTotal();
        $invoiceEntry->save();

        return $invoiceEntry;
    }

    /**
     * Create expenses invoice entry
     *
     * @param Invoice $invoice
     * @param WorkOrder $wo
     * @param QuoteEntry $quoteEntry
     * @param int $travelServiceID
     * @param int $equipmentServiceID
     * @param int $sortOrder
     *
     * @return InvoiceEntry
     */
    public function createExpensesFromQuote(
        Invoice $invoice,
        WorkOrder $wo,
        QuoteEntry $quoteEntry,
        $travelServiceID,
        $equipmentServiceID,
        $sortOrder
    ) {
        $serviceID = $equipmentServiceID;
        if (mb_strpos($quoteEntry->getDesc(), 'Trave') !== false) {
            $serviceID = $travelServiceID;
        }
        $invoiceEntry = $this->newInstance();
        $invoiceEntry = $this->setCommonEntryFromQuoteFields(
            $invoiceEntry,
            $invoice,
            $wo,
            $sortOrder,
            $serviceID,
            $quoteEntry
        );

        $invoiceEntry->qty = $quoteEntry->getTrips();
        $invoiceEntry->price = $quoteEntry->getCharge();
        $invoiceEntry->total = $quoteEntry->getTotal();
        $invoiceEntry->save();

        return $invoiceEntry;
    }

    /**
     * Create spare line invoice entry
     *
     * @param Invoice $invoice
     * @param WorkOrder $wo
     * @param int $serviceID
     * @param int $sortOrder
     * @param int $entryDate
     *
     * @return InvoiceEntry
     */
    public function createSpareFromQuote(
        Invoice $invoice,
        WorkOrder $wo,
        $serviceID,
        $sortOrder,
        $entryDate
    ) {
        $invoiceEntry = $this->newInstance();
        $invoiceEntry = $this->setCommonEntryFromQuoteFields(
            $invoiceEntry,
            $invoice,
            $wo,
            $sortOrder,
            $serviceID
        );

        $invoiceEntry->entry_short = 'spare line';
        $invoiceEntry->entry_long = $invoiceEntry->entry_short;
        $invoiceEntry->qty = 0;
        $invoiceEntry->price = 0;
        $invoiceEntry->total = 0;
        $invoiceEntry->entry_date = $entryDate;
        $invoiceEntry->save();

        return $invoiceEntry;
    }

    /**
     * Set common fields for invoice entry (without saving) and returns modified
     * invoice entry
     *
     * @param InvoiceEntry $invoiceEntry
     * @param Invoice $invoice
     * @param WorkOrder $wo
     * @param int $sortOrder
     * @param int $serviceID
     * @param QuoteEntry $quoteEntry
     *
     * @return InvoiceEntry
     */
    protected function setCommonEntryFromQuoteFields(
        InvoiceEntry $invoiceEntry,
        Invoice $invoice,
        WorkOrder $wo,
        $sortOrder,
        $serviceID,
        QuoteEntry $quoteEntry = null
    ) {
        if ($quoteEntry !== null) {
            $invoiceEntry->entry_short = $quoteEntry->getDesc();
            $invoiceEntry->entry_long = $quoteEntry->getDesc();
        }

        $invoiceEntry->service_id = $serviceID;
        $invoiceEntry->service_id2 = 0;
        $invoiceEntry->item_id = 0;

        $invoiceEntry->person_id = $wo->getRealCompanyPersonId();
        $invoiceEntry->invoice_id = $invoice->getId();
        $invoiceEntry->order_id = 0;
        $invoiceEntry->sort_order = $sortOrder;
        $invoiceEntry->creator_person_id = $this->getCreatorPersonId();

        return $invoiceEntry;
    }

    /**
     * Get invoice entries for given invoice with sort_order greater than given
     *
     * @param int $invoiceId
     * @param int $sortOrder
     *
     * @return Collection
     */
    public function getForInvoiceWithSortOrder($invoiceId, $sortOrder)
    {
        return $this->model->where('invoice_id', $invoiceId)
            ->where('sort_order', '>', $sortOrder)->get();
    }

    /**
     * Set sort order for given invoice entry, save record and return
     * modified record
     *
     * @param InvoiceEntry $invoiceEntry
     * @param  int $sortOrder
     *
     * @return InvoiceEntry
     */
    public function setSortOrder(InvoiceEntry $invoiceEntry, $sortOrder)
    {
        $invoiceEntry->sort_order = $sortOrder;
        $invoiceEntry->save();

        return $invoiceEntry;
    }
}
