<?php

namespace App\Modules\Invoice\Repositories;

use App\Core\AbstractRepository;
use App\Core\DbConfig;
use App\Modules\ExternalServices\Models\ExternalLetter;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Type\Repositories\TypeMemcachedRepository;
use App\Modules\WorkOrder\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsRepository;
use App\Modules\Invoice\Models\InvoiceBatch;
use App\Modules\Invoice\Models\InvoiceBatchItem;
use Illuminate\Support\Facades\DB;
use App\Modules\Type\Models\Type;

/**
 * Invoice batch repository class
 */
class InvoiceBatchRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [
        'invoice_batch_id',
        'invoices_count',
        'company_name',
        'status_type_value'
    ];

    protected $sortable = [
        'invoice_batch_id',
        'invoices_count',
        'company_name',
        'status_type_value',
        'created_at',
        'updated_at'
    ];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param Invoice $invoice
     */
    public function __construct(
        Container $app,
        InvoiceBatch $batch
    ) {
        parent::__construct($app, $batch);
    }

    /**
     * Display paginated batches list.
     *
     * @param int $perPage
     * @param array $columns
     * @param array $order
     *
     * @return LengthAwarePaginator
     *
     * @throws \InvalidArgumentException
     */
    public function paginate(
        $perPage = 50,
        array $columns
        = [
            'invoice_batch.*'
        ],
        array $order = []
    ) {
        $inputs = $this->getInput();

        $model = $this->model;

        $this->setRawColumns(true);

        $invoicesCountQuery = DB::raw("(SELECT COUNT(bi.invoice_batch_item_id) FROM invoice_batch_item bi WHERE bi.invoice_batch_id = invoice_batch.invoice_batch_id)");
        $columns[] = $invoicesCountQuery . " as invoices_count";
        $columns[] = 'person.custom_1 as company_name';
        $columns[] = 'type.type_value as status_type_value';

        $model = $model->join('person', 'person.person_id', '=', 'invoice_batch.person_id');
        $model = $model->join('type', 'type.type_id', '=', 'invoice_batch.status_type_id');

        $model = $model->with('status'); //add status

        if (isset($inputs['status_type_id']) && $inputs['status_type_id']) {
            $model->where('invoice_batch.status_type_id', (int)$inputs['status_type_id']);
        }

        if (isset($inputs['invoices_count']) && $inputs['invoices_count']) {
            $invoicesCount = $inputs['invoices_count'];
            unset($inputs['invoices_count']);
            $this->setInput($inputs);
            $model->whereRaw("$invoicesCountQuery = $invoicesCount");
        }

        if (isset($inputs['company_name']) && $inputs['company_name']) {
            $companyName = $inputs['company_name'];
            unset($inputs['company_name']);
            $this->setInput($inputs);
            $model->whereRaw("person.custom_1 LIKE '%$companyName%'");
        }

        if (isset($inputs['status_type_value']) && $inputs['status_type_value']) {
            $statusName = $inputs['status_type_value'];
            unset($inputs['status_type_value']);
            $this->setInput($inputs);
            $model->whereRaw("type.type_value LIKE '%$statusName%'");
        }

        if (isset($inputs['batch_id'])) {
            $q = trim(($inputs['batch_id']));
            unset($inputs['batch_id']);
            $this->setInput($inputs);
            if (is_numeric($q)) {
                $model->whereRaw("invoice_batch.invoice_batch_id = $q");
            } else {
                $model->where('person.custom_1', 'like', '%' . $q . '%');
            }
        }

        if (empty($inputs['sort']) || !$inputs['sort']) {
            $model = $model
                ->orderByDesc('invoice_batch.invoice_batch_id');
        }

        // set working model
        $this->setWorkingModel($model);

        $data = parent::paginate($perPage, $columns, $order);

        // clear used model to prevent any unexpected actions
        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Get batch data
     *
     * @param $id
     * @return InvoiceBatch
     */
    public function getBatch($id)
    {
        $batch = $this->model
            ->where('invoice_batch_id', $id)
            ->with('items.invoice')//add invoices
            ->with('items.invoice.entries')//add invoices
            ->with('person')//add person
            ->with('status')//add status
            ->first();

        //Remove items and add only invoices
        $invoices = [];
        foreach ($batch->items as &$item) {
            //count total of invoice entries
            $entries = $item->invoice->entries;
            $total = 0;
            foreach ($entries as $entry) {
                $total += $entry->total;
            }
            $invoices[] = [
                'invoice_id' => $item->invoice->id,
                'invoice_number' => $item->invoice->invoice_number,
                'date_invoice' => $item->invoice->date_invoice,
                'total' => $total
            ];
        }
        $batch->invoices = $invoices;
        unset($batch->items);

        //Replace customer with only needed data
        $batch->customer = [
            'person_id' => $batch->person_id,
            'company_name' => $batch->person->custom_1
        ];
        unset($batch->person);

        //Replace status with only needed data
        $status = [
            'id' => $batch->status->id,
            'type_name' => $batch->status->type_value
        ];
        unset($batch->status);
        $batch->status = $status;

        if ($batch->table_name = (new ExternalLetter())->getTable()) {
            if ($batch->letter) {
                $batch->letter->events; //add letter with events to batch
                $letter = [
                    'letter_id' => $batch->letter->id,
                    'letter_file_url' => $batch->letter->lob_file_url,
                    'expected_delivery_date' => $batch->letter->expected_delivery_date,
                ];
                $events = [];
                foreach ($batch->letter->events as $event) {
                    $events[] = [
                        'id' => $event->id,
                        'name' => $event->name,
                        'zip' => $event->location_zip_code,
                        'registered_date' => \DateTime::createFromFormat('Y-m-d H:i:s', $event->registered_date)->format('m/d/Y H:i')
                    ];
                }
                unset($batch->letter);
                $batch->letter = $letter;
                $batch->events = $events;
            }
        }

        return $batch;
    }

    /**
     * Get invoices batches statuses collection
     *
     * @return Collection
     */
    public function getBatchesStatuses()
    {
        return Type::select('type_value as type_name', 'type_id')
            ->where('type', 'invoices_batches')
            ->get();
    }
}
