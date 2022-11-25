<?php

namespace App\Modules\Invoice\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Invoice\Http\Requests\InvoiceEntryRequest;
use App\Modules\Invoice\Models\InvoiceEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * InvoiceEntry repository class
 */
class InvoiceEntryRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [
        'invoice_entry_id',
        'entry_short',
        'entry_long',
        'qty',
        'price',
        'total',
        'unit',
        'entry_date',
        'service_id',
        'service_id2',
        'item_id',
        'person_id',
        'invoice_id',
        'order_id',
        'calendar_event_id',
        'is_disabled',
        'func',
        'tax_rate',
        'tax_amount',
        'discount',
        'packaged',
        'creator_person_id',
        'register_id',
        'created_date',
        'currency',
        'sort_order',
        'updated_at',

        'unitRel',
    ];

    /**
     * Repository constructor
     *
     * @param Container    $app
     * @param InvoiceEntry $invoiceEntry
     */
    public function __construct(Container $app, InvoiceEntry $invoiceEntry)
    {
        parent::__construct($app, $invoiceEntry);
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new InvoiceEntryRequest();

        return $req->getFrontendRules();
    }

    /**
     * Detach invoice entry from any invoice
     *
     * @param InvoiceEntry $invoiceEntry
     */
    public function detach(InvoiceEntry $invoiceEntry)
    {
        $invoiceEntry->invoice_id = 0;
        $invoiceEntry->save();
    }

    /**
     * Detach related entries from invoice entry
     *
     * @param $invoiceEntry
     */
    public function detachLinkedRecords(InvoiceEntry $invoiceEntry)
    {
        /* @todo make sure no actions should be launched when making those
         * update - otherwise each record should be probably updated separately */

        DB::update('UPDATE time_sheet SET invoice_entry_id=0
                    WHERE invoice_entry_id = ?', [$invoiceEntry->getId()]);

        DB::update('UPDATE bill_entry SET invoice_entry_id=0
                    WHERE invoice_entry_id = ?', [$invoiceEntry->getId()]);

        // @todo below method is commented (and not tested) because there is no such table in CRM yet
        /*
                DB::update('UPDATE purchase_order_entry SET invoice_entry_id=0
                            WHERE invoice_entry_id = ?', [$invoiceEntry->getId()]);
        */
    }

    /**
     * Get invoice entries assigned to given Calendar Event id
     *
     * @param int $calendarEventId
     *
     * @return Collection
     */
    public function getForCalendarEventId($calendarEventId)
    {
        return $this->model->where('calendar_event_id', $calendarEventId)
            ->get();
    }

    /**
     * Change is_disabled status for given Invoice Entry
     *
     * @param InvoiceEntry $invoiceEntry
     * @param int          $isDisabled
     *
     * @return InvoiceEntry
     */
    public function changeDisabledStatus(
        InvoiceEntry $invoiceEntry,
        $isDisabled
    ) {
        $invoiceEntry->is_disabled = $isDisabled;
        $invoiceEntry->save();

        return $invoiceEntry;
    }

    /**
     * Pagination - based on query url use either automatic paginator or manual paginator
     *
     * @param int   $perPage
     * @param array $columns
     * @param array $order
     *
     * @return \Illuminate\Database\Eloquent\Collection|Paginator
     */
    public function paginate(
        $perPage = 50,
        array $columns = ['*'],
        array $order = []
    ) {
        /** @var InvoiceEntry|Object $model */
        $model = $this
            ->getModel()
            ->with([
                'item',
                'service' => function ($query) {
                    /** @var Builder $query */
                    $query->select('service_id', 'service_name');
                },
                'unitRel' => function ($query) {
                    /** @var Builder $query */
                    $query->select('type_id', 'type_value');
                },
            ]);

        //$columns[] = 'unitRel as unitRel';

        $this->setWorkingModel($model);

        $result = parent::paginate($perPage, $columns, $order);

        $this->clearWorkingModel();

        foreach ($result->items() as &$item) {
            $item->entry_date = getDateOrNull($item->entry_date);
        }
        
        return $result;
    }

    /**
     * Search invoice entry by text
     *
     * @param string $searchKey
     * @param array  $columns
     *
     * @return InvoiceEntry[]|Collection
     */
    public function search(
        $searchKey,
        array $columns = ['invoice_entry.*']
    ) {
        /** @var Builder|Object|InvoiceEntry $model */
        $model = $this->getModel();

        $columns[] = 'person_name(invoice_entry.creator_person_id) as created_by';

        $model = $model
            ->longEntryContains($searchKey, true)
            ->shortEntryContains($searchKey, true);

        $this->setWorkingModel($model);

        $this->setRawColumns(true);

        $data = parent::paginate(50, $columns, []);

        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Get entries by invoice ID
     *
     * @param $invoiceId
     *
     * @return mixed
     */
    public function getByInvoiceId($invoiceId)
    {
        return $this->getModel()
            ->where('invoice_id', $invoiceId)
            ->get();
    }

    /**
     * Get grouped entries by invoice id
     *
     * @param $invoiceId
     *
     * @return array
     */
    public function getGroupedEntriesByInvoiceId($invoiceId)
    {
        $groups = [
            'service' => ['name' => 'Services', 'total' => 0.0, 'count' => 0, 'entries' => []],
            'item'    => ['name' => 'Items', 'total' => 0.0, 'count' => 0, 'entries' => []]
        ];

        $entries = $this->model
            ->where('invoice_id', $invoiceId)
            ->get();


        /** @var InvoiceEntry $entry */
        foreach ($entries as $entry) {
            $groupTag = $entry->getItemId() > 0 ? 'item' : 'service';
            $groups[$groupTag]['count']++;
            $groups[$groupTag]['total'] += $entry->getTotal();
            $groups[$groupTag]['entries'][] = $entry;
        }

        return $groups;
    }

    /**
     * @param  int  $calendarEventId
     *
     * @return mixed
     */
    public function deleteByCalendarEventId(int $calendarEventId)
    {
        return $this->model
            ->where('calendar_event_id', $calendarEventId)
            ->where('invoice_id', 0)
            ->delete();
    }
}
