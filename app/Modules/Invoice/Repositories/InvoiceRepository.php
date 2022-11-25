<?php

namespace App\Modules\Invoice\Repositories;

use App\Core\AbstractRepository;
use App\Core\DbConfig;
use App\Modules\Activity\Repositories\ActivityRepository;
use App\Modules\Address\Repositories\AddressRepository;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsRepository;
use App\Modules\ExternalServices\Services\Lob\ServiceCondition as LobCondition;
use App\Modules\ExternalServices\Services\ServiceChannel\ServiceCondition as ServiceChannelCondition;
use App\Modules\ExternalServices\Services\Speedway\ServiceCondition as SpeedwayCondition;
use App\Modules\ExternalServices\Services\Verisae\ServiceCondition as VerisaeCondition;
use App\Modules\EzReport\Services\Export\XlsExporter;
use App\Modules\File\Models\File;
use App\Modules\File\Services\FileService;
use App\Modules\Invoice\Http\Requests\InvoiceRequest;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceEntry;
use App\Modules\MsDynamics\InvoiceAttachmentImporter;
use App\Modules\PurchaseOrder\Models\PurchaseOrderEntry;
use App\Modules\System\Repositories\SystemSettingsRepository;
use App\Modules\TimeSheet\Models\TimeSheet;
use App\Modules\Type\Repositories\TypeMemcachedRepository;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Services\WorkOrderService;
use Carbon\Carbon;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Invoice repository class
 */
class InvoiceRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [
        'invoice_id',
        'invoice_number',
        'person_id',
        'date_invoice',
        'date_due',
        'statement_id',
        'paid',
        'creator_person_id',
        'date_created',
        'date_modified',
        'work_order_id',
        'table_name',
        'table_id',
        'customer_request_description',
        'job_description',
        'ship_address_id',
        'currency',
        'billing_person_name',
        'billing_address_line1',
        'billing_address_line2',
        'billing_address_city',
        'billing_address_state',
        'billing_address_zip_code',
        'billing_address_country',
        'shipping_person_name',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_address_city',
        'shipping_address_state',
        'shipping_address_zip_code',
        'shipping_address_country',
        'status_type_id',
        'status_type_id_value',

        'amount_due',
        'creator_name',
        'customer_name',
        'first_sending_attempt',
        'tax_amount',
        'total',
        'work_order_number'
    ];

    /**
     * PDF options - LOB accept only A4 letters, otherwise throw errors
     * PDF option for invoice file
     */
    const INVOICE_PDF_OPTIONS = [
        'density'   => 150,
        'pdfSize'   => [1275, 1650],
        'imageSize' => [1200, 1500],
        'border'    => [50, 50],
        'rotate'    => 'portrait'
    ];

    /**
     * Repository constructor
     *
     * @param  Container  $app
     * @param  Invoice  $invoice
     */
    public function __construct(
        Container $app,
        Invoice $invoice
    ) {
        parent::__construct($app, $invoice);
    }

    /**
     * Creates and stores new Invoice
     *
     * @param  array  $input
     *
     * @return Invoice
     *
     * @throws Exception
     */
    public function create(array $input)
    {
        DB::beginTransaction();

        try {
            $input['status_type_id'] = $this->getInvoiceStatusTypeId();
            $input['person_id'] = $input['company_person_id'] ?? null;
            
            /** @var InvoiceEntry[] $entries */
            $entries = $input['entries'];

            /** @var Invoice $invoice */
            $invoice = parent::create($input);

            $taxRate = app(SystemSettingsRepository::class)->getValueByKey('crm_config.tax_rate', 0);
            
            if (!empty($input['entries'])) {
                /** @var InvoiceEntry $entry */
                foreach ($entries as $entry) {
                    $invoiceEntry = new InvoiceEntry($entry);
                    if (empty($entry['entry_date'])) {
                        $invoiceEntry->setEntryDate(Carbon::now()->format('Y-m-d'));
                    }
                    
                    if (!empty($entry['entry_long'])) {
                        $invoiceEntry->setEntryShort($entry['entry_long']);
                    }
                    
                    if (isset($entry['taxable']) && $entry['taxable']) {
                        $invoiceEntry->setTax(round(($entry['total'] * $taxRate) / 100), $taxRate);
                    } else {
                        $invoiceEntry->setTax(0, 0);
                    }
                    
                    $newEntry = $invoice->entries()->save($invoiceEntry);
                    if (!empty($entry['time_sheet_id'])) {
                        /** @var TimeSheet $timeSheet */
                        $timeSheet = TimeSheet::find($entry['time_sheet_id']);
                        $timeSheet->invoice_entry_id = $newEntry->getId();
                        $timeSheet->save();
                    }

                    //for time sheets combined
                    if (!empty($entry['time_sheet_ids'])) {
                        foreach ($entry['time_sheet_ids'] as $timeSheetId) {
                            if (!empty($timeSheetId)) {
                                /** @var TimeSheet $timeSheet */
                                $timeSheet = TimeSheet::find($timeSheetId);
                                $timeSheet->invoice_entry_id = $newEntry->getId();
                                $timeSheet->save();
                            }
                        }
                    }

                    if (!empty($entry['purchase_order_entry_id'])) {
                        /** @var PurchaseOrderEntry $purchaseOrderEntry */
                        $purchaseOrderEntry = PurchaseOrderEntry::find($entry['purchase_order_entry_id']);
                        $purchaseOrderEntry->invoice_entry_id = $newEntry->getId();
                        $purchaseOrderEntry->save();
                    }

                    //for purchase order entries combined
                    if (!empty($entry['purchase_order_entry_ids'])) {
                        foreach ($entry['purchase_order_entry_ids'] as $purchaseOrderEntryId) {
                            if (!empty($purchaseOrderEntryId)) {
                                /** @var PurchaseOrderEntry $purchaseOrderEntry */
                                $purchaseOrderEntry = PurchaseOrderEntry::find($purchaseOrderEntryId);
                                $purchaseOrderEntry->invoice_entry_id = $newEntry->getId();
                                $purchaseOrderEntry->save();
                            }
                        }
                    }
                }
            }

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();

            throw $exception;
        }

        return $invoice;
    }

    /**
     * Return invoice by given $id with work order
     *
     * @param  int  $id
     * @param  array  $columns
     *
     * @return Invoice|Model
     *
     * @throws ModelNotFoundException
     */
    public function findWithWorkOrder($id, array $columns = ['*'])
    {
        /** @var Invoice|Object $model */
        $model = $this->getModel();

        $model = $model->with('workOrder');

        $this->setWorkingModel($model);

        return $this->findInternal($id, $columns);
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new InvoiceRequest();

        return $req->getFrontendRules();
    }

    /**
     * Display paginated invoices list.
     *
     * @param  int  $perPage
     * @param  array|string  $columns
     * @param  array  $order
     *
     * @return LengthAwarePaginator|array
     *
     * @throws InvalidArgumentException
     */
    public function paginate(
        $perPage = 50,
        array $columns = [
            'invoice.*',
            'person_name(invoice.creator_person_id) as creator_person_name',
            't(invoice.status_type_id) as status_type_id_value',
        ],
        array $order = []
    ) {
        $inputs = $this->getInput();

        $withEntries = (int) $this->request->input('with_entries', 0);
        $withTotal = (int) $this->request->input('with_total', 0);

        // allow to use raw expressions
        $this->setRawColumns(true);

        /** @var Invoice|Object $model */
        $model = $this->model;
        // get allowed columns lists
        $chosenColumns = $this->getColumnsList();

        // set custom columns status (based on input)
        if (is_string($chosenColumns) && $chosenColumns === '*') {
            $customColumns = false;
        } else {
            // assign columns to those selected by user
            $columns = $chosenColumns;
            $customColumns = true;
        }

        // if record_id set, we use table_id condition
        if (!empty($inputs['record_id'])) {
            $model = $model->where('table_id', $inputs['record_id']);
        }

        $aggregates = [];
        $extraColumns = [];
        
        $model = $model->whereRaw('invoice.person_id IS NOT NULL AND invoice.person_id > 0');

        // if custom columns used by user
        if ($customColumns) {
            $collation = $this->app->make(DbConfig::class)->get('collation');

            //$input = $this->getInput();
            foreach ($columns as $key => $value) {
                $columns[$key] = 'invoice.'.$value;
            }

            // if user has chosen assigned_to column, we will also add
            // assigned_to_person_name column
            if (in_array('invoice.creator_person_id', $columns)) {
                $model = $model->leftJoin('person as person_creator', function ($join) {
                    /** @var JoinClause $join */
                    $join->on('invoice.creator_person_id', '=', 'person_creator.person_id');
                });
                $extraColumns[] =
                    'CONCAT(person_creator.custom_1, " ", person_creator.custom_2) AS creator_name';
            }

            if (empty($inputs['customer_name'])) {
                unset($inputs['customer_name']);
            }

            if (empty($inputs['invoice_number'])) {
                unset($inputs['invoice_number']);
            }

            if (empty($inputs['work_order_number'])) {
                unset($inputs['work_order_number']);
            }

            if (in_array('invoice.person_id', $columns)) {
                $model = $model->leftJoin('person as person_customer', function ($join) {
                    /** @var JoinClause $join */
                    $join->on('invoice.person_id', '=', 'person_customer.person_id');
                });
                $extraColumns[] =
                    'CONCAT(person_customer.custom_1, " ", person_customer.custom_2) AS customer_name';
            }

            if (in_array('invoice.work_order_id', $columns)) {
                $model = $model->leftJoin('work_order', function ($join) {
                    /** @var JoinClause $join */
                    $join->on('invoice.work_order_id', '=', 'work_order.work_order_id');
                });
                $extraColumns[] =
                    'work_order.work_order_number AS work_order_number';
            }

            //region Invoice number

            $extraColumns[] = 'invoice.invoice_number as invoice_number';

            if (isset($inputs['invoice_number']) && $inputs['invoice_number']) {
                $model->whereRaw("invoice.invoice_number LIKE '".$inputs['invoice_number']."%'");
            }

            unset($inputs['invoice_number']);

            //endregion

//            //region last_send_date
//            if (!empty($inputs['fields'])) {
//                $fields = explode(',', $inputs['fields']);
//
//                if (in_array('last_send_date', $fields)) {
//                    $model = $model->leftJoin(DB::raw('(
//                        select max(a.created_date) as created_date, a.activity_id, a.table_id, a.subject, a.creator_person_id
//                        from activity a
//                        where a.table_name=\'invoice\' and a.subject=\'Invoice Sent\'
//                        group by a.table_id
//                    ) as ac'), 'ac.table_id', '=', 'invoice.invoice_id');
//
//                    $extraColumns[] =
//                        'ac.created_date AS last_send_date';
//                }
//            }
//
//            //endregion

            //region Status type

            $model = $model
                ->leftJoin('type as statusType', function ($join) {
                    /** @var \Illuminate\Database\Query\JoinClause $join */
                    $join->on('invoice.status_type_id', '=', 'statusType.type_id');
                });

            if (isset($inputs['status_type_id_value']) && $inputs['status_type_id_value']) {
                $model->whereRaw("invoice.status_type_id = '".$inputs['status_type_id_value']."'");
            }

            unset($inputs['status_type_id_value']);

            //region For report

            $this->config->set('database.max_records', 100000);

            $forReport = '';
            if (isset($inputs['for_report']) && $inputs['for_report']) {
                $forReport = "OR statusType.type_key = 'invoice_status.internal_rejected'";
            }

            unset($inputs['for_report']);

            //endregion

            $extraColumns[] = 't(invoice.status_type_id) as status_type_id_value';
            $extraColumns[] = 'invoice.status_type_id as status_type_id';
            $extraColumns[] = "IF(statusType.type_key = 'invoice_status.internal_approved' $forReport, ".
                "(SELECT MIN(queued_job.completed_at) FROM queued_job WHERE queued_job.record_id = invoice.invoice_id), '') 
                as first_sending_attempt";
            $extraColumns[] =
                "IF (statusType.type_key = 'invoice_status.internal_rejected' OR statusType.type_key = 'invoice_status.internal_approved', 
                     (SELECT calendar_event.description FROM calendar_event 
                      WHERE calendar_event.tablename = 'invoice' AND calendar_event.record_id = invoice.invoice_id 
                      ORDER BY calendar_event.time_start DESC LIMIT 1), '') 
                as last_event";

            //endregion

            //region Amount due

            if (isset($inputs['with_amount_due']) && $inputs['with_amount_due']) {
                $smountDueSql = 'IFNULL(
                        (SELECT SUM(invoice_entry.total)
                        FROM invoice_entry
                        WHERE invoice_entry.invoice_id = invoice.invoice_id),
                        0)
                    -
                    IFNULL(
                        (SELECT SUM(payment_invoice.paymentsize) 
                        FROM payment_invoice 
                        WHERE payment_invoice.invoice_id = invoice.invoice_id),
                        0)
                ';

                $extraColumns[] =
                    '(SELECT SUM(invoice_entry.total)
                    FROM invoice_entry
                    WHERE invoice_entry.invoice_id = invoice.invoice_id)
                    as total';

                $extraColumns[] = $smountDueSql.' as amount_due';

                if (!empty($inputs['with_amount_due_exists'])) {
                    $model = $model->whereRaw("ROUND(${smountDueSql}, 2) > 0");
                }
            }

            unset($inputs['with_amount_due']);

            //endregion

            //region Creator name

            if (isset($inputs['creator_name']) && $inputs['creator_name']) {
                $model->whereRaw('person_name(invoice.creator_person_id) '.
                    "COLLATE {$collation} LIKE '%{$inputs['creator_name']}%'");
            }

            unset($inputs['creator_name']);

            //endregion

            //region Customer name

            if (isset($inputs['customer_name']) && $inputs['customer_name']) {
                $model->whereRaw('person_name(invoice.person_id) '.
                    "COLLATE {$collation} LIKE '%{$inputs['customer_name']}%'");
            }

            unset($inputs['customer_name']);

            //endregion

            //region Sending attempt

            if (isset($inputs['sending_attempt']) && $inputs['sending_attempt']) {
                $model->attemptSentAt($inputs['sending_attempt']);
            }

            unset($inputs['sending_attempt']);

            //endregion

            //region Import service

            if (isset($inputs['import_service']) && $inputs['import_service']) {
                $this->filterByService($model, $inputs['import_service']);
            }

            unset($inputs['import_service']);

            //endregion

            //region Person ID

            if (isset($inputs['person_id']) && $inputs['person_id']) {
                $model->whereRaw("invoice.person_id = '{$inputs['person_id']}'");
            }

            unset($inputs['person_id']);

            //endregion


            //region date_invoice

            if (!empty($inputs['date_invoice'])) {
                $dateInvoice = explode(',', $inputs['date_invoice']);
                if (count($dateInvoice) === 2) {
                    $model->whereBetween('invoice.date_invoice', $dateInvoice);
                } else {
                    $model->where('invoice.date_invoice', $inputs['date_invoice']);
                }
            } else {
                //region From date
                if (isset($inputs['from_date']) && $inputs['from_date']) {
                    $model->whereRaw("invoice.date_invoice >= '{$inputs['from_date']}'");
                }

                //endregion

                //region To date

                if (isset($inputs['to_date']) && $inputs['to_date']) {
                    $model->whereRaw("invoice.date_invoice <= '{$inputs['to_date']}'");
                }

                //endregion
            }

            unset($inputs['date_invoice'], $inputs['from_date'], $inputs['to_date']);

            //endregion

            //region paid

            if (isset($inputs['paid'])) {
                $model->whereIn('paid', explode(',', $inputs['paid']));
            }

            unset($inputs['paid']);

            //endregion


            //region invoice_id

            if (isset($inputs['id'])) {
                $model->where('invoice.invoice_id', $inputs['id']);
            }

            unset($inputs['id']);

            //endregion

            if (isset($inputs['created_at'])) {
                $model->where('invoice.date_created', 'LIKE', $inputs['created_at'].'%');

                unset($inputs['created_at']);
            }

            // clear field input - we used it manually above
            unset($inputs['fields']);
            $this->setInput($inputs);

            // Total and Tax
            if (($withEntries !== 1)
                && ($withTotal !== 1)
                && in_array('invoice.currency', $columns)) {
                $this->setCountModel($model);

                $model = clone $model;
//
                $model->leftJoin('invoice_entry', function ($join) {
                    /** @var \Illuminate\Database\Query\JoinClause $join */
                    $join->on('invoice_entry.invoice_id', '=', 'invoice.invoice_id');
                });

                $aggregates[] = 'sum(invoice_entry.total) AS total';
                $aggregates[] = 'sum(invoice_entry.tax_amount) AS tax_amount';

                $model
                    ->groupBy('invoice.invoice_id');
            }

            $columns = array_merge($columns, $extraColumns, $aggregates);

            // set as row query
            $model = $model->selectRaw(implode(', ', $columns));

            $columns = [];
        }

        // if with entries, we need to include them
        if (($withEntries === 1) || ($withTotal === 1)) {
            /** @var InvoiceEntryRepository $invoiceEntryRepo */
            $entryRepo = $this->app->make(InvoiceEntryRepository::class);
            $entryColumns = $entryRepo->getValidColumnsList('entry_fields');
            $model = $model->with([
                'entries' => function ($q) use ($entryColumns) {
                    /** @var Builder $q */

                    // always include invoice_id - this is must to assign
                    // invoice entries to correct invoices
                    if (!in_array('invoice_id', $entryColumns) &&
                        !in_array('*', $entryColumns)
                    ) {
                        $entryColumns[] = 'invoice_id';
                    }
                    // always include total (to calculate invoice total)
                    if (!in_array('total', $entryColumns) &&
                        !in_array('*', $entryColumns)
                    ) {
                        $entryColumns[] = 'total';
                    }
                    // always include total (to calculate invoice tax)
                    if (!in_array('tax_amount', $entryColumns) &&
                        !in_array('*', $entryColumns)
                    ) {
                        $entryColumns[] = 'tax_amount';
                    }
                    // if service_id get service name
                    if (in_array('service_id', $entryColumns) ||
                        in_array('*', $entryColumns)
                    ) {
                        $entryColumns[] =
                            "IF(invoice_entry.service_id, (SELECT service_name
                                FROM service WHERE service.service_id 
                                =invoice_entry.service_id LIMIT 1),'')
                             AS service_id_value";
                    }
                    // if item_id get item name
                    if (in_array('item_id', $entryColumns) ||
                        in_array('*', $entryColumns)
                    ) {
                        $entryColumns[] =
                            "IF(invoice_entry.item_id, (SELECT description
                                FROM item WHERE item.item_id = 
                                invoice_entry.item_id LIMIT 1),'')
                            AS item_id_value";
                    }
                    $q->selectRaw(implode(', ', $entryColumns));
                },
            ]);
        }

        if (empty($inputs['sort']) || !$inputs['sort']) {
            $model = $model
                ->orderByDesc('invoice.date_invoice')/*->orderByDesc('last_event')*/
            ;
        }

        if (config('app.crm_user') == 'bfc') {
            $model = $model
                ->selectRaw('(select max(status_timestamp) from invoice_delivery where invoice_id = invoice.invoice_id and success=1) as last_send_date');
        }

        // set working model
        $this->setWorkingModel($model);

        $data = parent::paginate($perPage, $columns, $order);

        // clear used model to prevent any unexpected actions
        $this->clearWorkingModel();

        // output data modifications
        $invoices = [];

        /** @var Invoice $invoice */
        foreach ($data->items() as $invoice) {
            // change paid to bool
            $invoice->paid = (empty($invoice->getPaid()) ? false : true);

            // if with entries, we want to get invoice total and invoice tax
            if (($withEntries === 1) || ($withTotal === 1)) {
                $invoice->sum_total = 0;
                $invoice->sum_tax_amount = 0;

                /** @var InvoiceEntry $entry */
                foreach ($invoice->entries as $entry) {
                    $invoice->sum_total += $entry->getTotal();
                    $invoice->sum_tax_amount = $entry->getTaxAmount();

                    if (!empty($entry->item_id)) {
                        $entry->service_id = null;
                        $entry->service_id_value = null;
                    }
                }
            }

            if (!empty($invoice->amount_due)) {
                $invoice->amount_due = round($invoice->amount_due, 2);
            }

            $altInvoice = $invoice->toArray();
            if ($withEntries !== 1) {
                unset($altInvoice['entries']);
            }

            $invoices[] = $altInvoice;
        }

        $data = $data->toArray();
        $data['data'] = $invoices;

        return $data;
    }

    /**
     * Export Excel
     *
     * @param $perPage
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function exportExcel(
        $perPage = 50
    ) {
        $input = $this->request->all();

        $availableDataTypes = [
            'id'                    => 'LONG',
            'invoice_number'        => 'VAR_STRING',
            'date_invoice'          => 'DATE',
            'customer_name'         => 'VAR_STRING',
            'work_order_number'     => 'VAR_STRING',
            'sum_total'             => 'LONG',
            'sum_tax_amount'        => 'LONG',
            'status_type_id_value'  => 'VAR_STRING',
            'first_sending_attempt' => 'VAR_STRING',
            'last_event'            => 'VAR_STRING',
            'paid'                  => 'TINY',
            'creator_name'          => 'VAR_STRING',
            'created_at'            => 'DATETIME',
            'updated_at'            => 'DATETIME',
        ];

        $columnNames = [
            "Invoice ID",
            "Invoice #",
            "Invoice Date",
            "Customer",
            "Work order #",
            "Total",
            "Tax amount",
            "Status",
            "First sending attempt",
            "Last event",
            "Paid",
            "Created by",
            "Created at",
            "Updated at"
        ];

        $selectedColumns = array_keys($availableDataTypes);
        $dataTypes = array_values($availableDataTypes);

        /*for ($count = 0, $countMax = count($selectedColumns); $count < $countMax; $count++) {
            $dataTypes[] = $availableDataTypes[$selectedColumns[$count]];
        }*/

        $data = $this->paginate($perPage);
        $dataArray = $data['data'];

        $dataRows = [];
        foreach ($dataArray as $row) {
            $dataRow = [];
            foreach ($selectedColumns as $column) {
                $dataRow[] = isset($row[$column]) ? $row[$column] : null;
            }

            $dataRows[] = $dataRow;
        }

        //print_r(json_decode($input['column_names'], true));
        $result = [
            'columns'    => $columnNames,
            'data_types' => $dataTypes,
            'data'       => $dataRows,
        ];

        //var_dump($result);

        $exporter = $this->app->make(XlsExporter::class);

        $now = new Carbon();

        $name = 'invoice_'.$now->toDateTimeString();

        return [
            'name'        => $name.'.xls',
            'fileContent' => $exporter->export([
                'data' => json_decode(json_encode($result)),
                'name' => $name,
            ])
        ];
    }

    /**
     * Filter query by external service
     *
     * @param  mixed  $query
     * @param  string  $service
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function filterByService($query, $service)
    {
        $conditions = [
            'ServiceChannel' => ServiceChannelCondition::class,
            'Speedway'       => SpeedwayCondition::class,
            'Verisae'        => VerisaeCondition::class,
            'Lob'            => LobCondition::class,
        ];

        if (isset($conditions[$service])) {
            $conditionClass = $conditions[$service];
            $condition = $this->app[$conditionClass];
            //Check if customer settings table exists
            $hasInvoicesSettingsTable = \Schema::hasTable('customer_invoice_settings');
            //If above checked table exists then join with it
            if ($hasInvoicesSettingsTable) {
                $query
                    ->leftJoin('customer_invoice_settings', function ($join) {
                        /** @var JoinClause $join */
                        $join->on('customer_invoice_settings.company_person_id', '=', 'invoice.person_id');
                    });
            }

            //If is lob service and invoice settings table exists then add condition
            if ($condition instanceof LobCondition && $hasInvoicesSettingsTable) {
                $query->where('customer_invoice_settings.delivery_method', '=', 'mail');
            } else {
                if ($hasInvoicesSettingsTable) {
                    $query->whereRaw("customer_invoice_settings.delivery_method IS NULL OR customer_invoice_settings.delivery_method = 'email'");
                }
                $query->whereIn('table_id', function ($query) use ($condition) {
                    /** @var Builder $query */
                    return $condition->applyForWorkOrder(
                        $query
                            ->select('work_order_id')
                            ->from('work_order')
                    );
                });
            }
        }
    }

    /**
     * @inheritdoc
     *
     * @throws ModelNotFoundException
     */
    public function show(
        $id,
        $full = false
    ) {
        if (true) {
            $model = $this->getModel()
                ->with([
                    'person' => function ($query) {
                        $query->select([
                            '*',
                            DB::raw('t(payment_terms_id) as payment_terms_id_value')
                        ]);
                    }, 'workOrder', 'workOrder.shopAddress'
                ]);

            /** @var InvoiceEntryRepository $invoiceEntryRepo */
            $entryRepo = $this->app->make(InvoiceEntryRepository::class);
            $entryColumns = $entryRepo->getValidColumnsList('entry_fields');
            $model = $model->with([
                'entries' => function ($q) use ($entryColumns) {
                    /** @var Builder $q */

                    // always include invoice_id - this is must to assign
                    // invoice entries to correct invoices
                    if (!in_array('invoice_id', $entryColumns) &&
                        !in_array('*', $entryColumns)
                    ) {
                        $entryColumns[] = 'invoice_id';
                    }
                    // always include total (to calculate invoice total)
                    if (!in_array('total', $entryColumns) &&
                        !in_array('*', $entryColumns)
                    ) {
                        $entryColumns[] = 'total';
                    }
                    // always include total (to calculate invoice tax)
                    if (!in_array('tax_amount', $entryColumns) &&
                        !in_array('*', $entryColumns)
                    ) {
                        $entryColumns[] = 'tax_amount';
                    }
                    // if service_id get service name
                    if (in_array('service_id', $entryColumns) ||
                        in_array('*', $entryColumns)
                    ) {
                        $entryColumns[] =
                            "IF(invoice_entry.service_id, (SELECT service_name
                                FROM service WHERE service.service_id 
                                =invoice_entry.service_id LIMIT 1),'')
                             AS service_id_value";
                    }
                    // if item_id get item name
                    if (in_array('item_id', $entryColumns) ||
                        in_array('*', $entryColumns)
                    ) {
                        $entryColumns[] =
                            "IF(invoice_entry.item_id, (SELECT description
                                FROM item WHERE item.item_id = 
                                invoice_entry.item_id LIMIT 1),'')
                            AS item_id_value";
                    }
                    $q->selectRaw(implode(', ', $entryColumns));
                },
            ]);

            $this->setWorkingModel($model);
        }

        $invoice = $this->find($id);

        $output['item'] = $invoice;

        $this->updateShippingAndBillingAddresses($output['item']);
        
        if ($full) {
            $output['fields'] = $this->getRequestRules();
        }

        return $output;
    }

    /**
     * Get invoices for quote
     *
     * @param  int  $quoteId
     * @param  string[]|string  $columns
     * @param  bool  $countOnly
     *
     * @return Invoice|int
     *
     * @throws InvalidArgumentException
     */
    public function getForQuote(
        $quoteId,
        $columns = '*',
        $countOnly = false
    ) {
        if (is_array($columns)) {
            $columns = implode(', ', $columns);
        }

        /** @var Builder|Invoice $model */
        $model = $this->model;

        $model = $model
            ->selectRaw($columns)
            ->where('work_order_id', function ($query) use ($quoteId) {
                /** @var Builder $query */
                $query
                    ->select('table_id')
                    ->from('quote')
                    ->where('quote_id', $quoteId)
                    ->where('table_name', 'work_order');
            });

        if ($countOnly) {
            return $model->count();
        }

        return $model->get();
    }

    /**
     * Get count of invoices for given quote
     *
     * @param  int  $quoteId
     *
     * @return int
     *
     * @throws InvalidArgumentException
     */
    public function getCountForQuote($quoteId)
    {
        return $this->getForQuote($quoteId, '*', true);
    }

    /**
     * Create new invoice from quote
     *
     * @param  WorkOrder  $workOrder
     * @param  Carbon  $createdDate
     * @param  Carbon  $dueDate
     *
     * @return Invoice
     */
    public function createNewInvoiceFromQuote(
        WorkOrder $workOrder,
        Carbon $createdDate,
        Carbon $dueDate
    ) {
        /** @var Invoice $invoice */
        $invoice = $this->newInstance();
        $invoice->setPersonId($workOrder->getRealCompanyPersonId());
        $invoice->setDateInvoice($createdDate->format('Y-m-d'));
        $invoice->setCreatorPersonId($this->getCreatorPersonId());
        $invoice->setDateDue($dueDate->format('Y-m-d'));
        $invoice->setWorkOrderId($workOrder->getId());
        $invoice->setTableLink('work_order', $workOrder->getId());
        $invoice->setPaid(0);
        $invoice->save();

        return $invoice;
    }

    /**
     * Update invoice paid status to given
     *
     * @param  Invoice  $invoice
     * @param  int  $paidStatus
     *
     * @return Invoice
     */
    public function changePaidStatus(Invoice $invoice, $paidStatus)
    {
        $invoice->paid = $paidStatus;
        $invoice->save();

        return $invoice;
    }

    /**
     * Create new invoice for PM work order
     * Single or array work_order_id could be given
     *
     * @param  mixed  $input
     *
     * @return Invoice
     *
     * @throws LogicException
     */
    public function createForPm($input)
    {
        $creatorPersonId = $input['person_id'] ?: $this->getCreatorPersonId();

        /** @var number[] $workOrderIds */
        $workOrderIds = $input['work_order_id'];
        $hasMultipleWOs = is_array($workOrderIds);
        if (empty($workOrderIds)) {
            throw new LogicException('No work order IDs given');
        }

        if ($hasMultipleWOs) {
            $workOrders = [];
            foreach ($workOrderIds as $workOrderId) {
                $workOrders[] = $this->app[WorkOrder::class]->findOrFail($workOrderId);
            }
            if (empty($workOrders)) {
                throw new LogicException('No work orders given');
            }
            $workOrder = reset($workOrders);
        } else { // single WO
            $workOrderId = $input['work_order_id'];
            $workOrder = $this->app[WorkOrder::class]->findOrFail($workOrderId);
            $workOrders = [$workOrder];
        }

        $pmServiceId = $this->app['config']['modconfig.invoice.pm_entry_service_id'];
        if (empty($pmServiceId)) {
            throw new LogicException('PM invoice entry service ID not configured');
        }
        $taxServiceId = $this->app['config']['modconfig.invoice.sales_tax_entry_service_id'];
        if (empty($taxServiceId)) {
            throw new LogicException('Sales Tax invoice entry service ID not configured');
        }

        $invoiceDate = Carbon::now();
        $dueDate = $this->app[CustomerSettingsRepository::class]->getDueDate($workOrder, $invoiceDate);

        /** @var Invoice $invoice */
        $invoice = $this->newInstance();
        $invoice->setDateInvoice($invoiceDate);
        $invoice->setDateDue($dueDate);
        $invoice->setCreatorPersonId($creatorPersonId);
        $invoice->setPersonId($workOrder->getRealCompanyPersonId());
        $invoice->setPaid(0);

        if ($hasMultipleWOs) {
            $invoice->setTableLink('person', $workOrder->getRealCompanyPersonId());
        } else {
            $invoice->setWorkOrderId($workOrder->id);
            $invoice->setTableLink('work_order', $workOrder->id);
        }

        if (isset($input['qb_itemsalestax_listid'])) {
            $invoice->setQBItemSalesTaxListId($input['qb_itemsalestax_listid']);
        }

        $invoice->save();

        foreach ($workOrders as $workOrder) {
            $nte = $workOrder->not_to_exceed;
            $totalAmount = $nte;
            $pmAmount = $totalAmount;
            $taxAmount = 0;
            $taxRate = 0;

            if (isset($input['sales_tax_rate'])) {
                $taxRate = (float) $input['sales_tax_rate'];
                if ($taxRate) {
                    $pmAmount = $totalAmount / ($taxRate / 100 + 1);
                    $taxAmount = $totalAmount - $pmAmount;
                }
            }

            /** @var InvoiceEntry $pmEntry */
            $pmEntry = $this->app[InvoiceEntry::class]->newInstance();
            $pmEntry->setInvoiceId($invoice->getId());
            $pmEntry->setQty(1);
            $pmEntry->setTotalPrice($pmAmount);
            $pmEntry->setServiceId($pmServiceId);
            $pmEntry->setPersonId($workOrder->getRealCompanyPersonId());
            $pmEntry->setCreatorPersonId($creatorPersonId);
            $pmEntry->setTax($taxAmount, $taxRate);

            if ($hasMultipleWOs) {
                $pmEntry->setTableLink('work_order', $workOrder->id);
                $pmEntry->setEntries('Preventive maintenance, WO #'.$workOrder->work_order_number);
            } else {
                $pmEntry->setEntries('Preventive maintenance');
            }

            $pmEntry->save();

            $workOrder->invoice_status_type_id = $this->app[TypeMemcachedRepository::class]->getIdByKey('wo_billing_status.invoiced');
            $workOrder->save();
        }

        return $invoice;
    }

    /**
     * Get activities for invoice
     *
     * @param  int  $id
     * @param  array  $params
     *
     * @return mixed
     *
     * @throws ModelNotFoundException
     */
    public function getActivities(
        $id,
        array $params
    ) {
        /** @var ActivityRepository $aRepo */
        $aRepo = $this->app->make(ActivityRepository::class);

        // verify if we want data in reverse order
        $reverse = false;
        if (isset($params['reverse']) && ((int) $params['reverse'] === 1)) {
            $reverse = true;
        }

        $items = $aRepo->getForInvoice($id, $params, $reverse);

        $data = new LengthAwarePaginator(
            $items,
            count($items),
            $items ? count($items) : 1,
            1,
            [
                'path'  => $this->app->request->url(),
                'query' => $this->app->request->query(),
            ]
        );

        $data = $data->toArray();

        return $data;
    }

    /**
     * Get invoice status type ID
     *
     * @return int
     */
    private function getInvoiceStatusTypeId()
    {
        return $this->app[TypeMemcachedRepository::class]->getIdByKey('invoice_status.internal_approved');
    }

    /**
     * @param $invoiceId
     *
     * @return string
     */
    public function getInvoiceFilePath($invoiceId)
    {
        $invoice = $this->find($invoiceId);
        try {
            $this->app->make(InvoiceAttachmentImporter::class, [
                'invoice'           => $invoice,
                'compressImage'     => false,
                'additionalOptions' => self::INVOICE_PDF_OPTIONS
            ])->import();
        } catch (Exception $e) {
            return false;
        }

        $pattern = '%merge%invoice%';
        $file = $this->app[File::class]
            ->where('table_name', 'work_order')
            ->where('table_id', $invoice['table_id'])
            ->where('filename', 'like', $pattern)
            ->orderByDesc('file_id')
            ->first();

        if (!$file) {
            return false;
        }

        return $this->app[FileService::class]->getTemporaryOriginalFilePath($file);
    }

    private function updateShippingAndBillingAddresses(Model $invoice)
    {
        if(empty($invoice->work_order_id)) {
            return;
        }

        /** @var WorkOrder $workOrder */
        $workOrder = WorkOrder::find($invoice->work_order_id);
        
        $prefixes = ['billing', 'shipping'];
        $fields = [
            'person_name' => 'person_name',
            'address_line1' => 'address_1',
            'address_line2' => 'address_2',
            'address_city' => 'city',
            'address_state' => 'state',
            'address_zip_code' => 'zip_code',
            'address_country' => 'country'
        ];

        foreach($prefixes as $prefix) {
            $empty = 0;

            foreach($fields as $field => $map) {
                $empty += empty($invoice->{$prefix . '_' . $field}) ? 1 : 0;
            }
            
            if($empty === count($fields)) {
                switch($prefix) {
                    case 'billing':
                        $address = AddressRepository::getAddressByPersonIds($workOrder->getBillingCompanyPersonId());
                        break;
                    case 'shipping':
                        $address = AddressRepository::getAddressByPersonIds($workOrder->getCompanyPersonId());
                        break;
                }
                
                if(!empty($address)) {
                    foreach ($fields as $field => $map) {
                        if (!empty($address->$map)) {
                            $invoice->{$prefix.'_'.$field} = $address->$map;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param  int  $workOrderId
     * @param $description
     */
    public function updateDescriptionByWorkOrderId(int $workOrderId, $description)
    {
        $sentTypeId = getTypeIdByKey('invoice_status.sent');
        
        /** @var Invoice $invoice */
        $invoices = $this->model
            ->where('work_order_id', $workOrderId)
            ->where('status_type_id', '!=', $sentTypeId)
            ->orderBy('invoice_id', 'desc')
            ->get();
        
        foreach ($invoices as $invoice) {
            if ($invoice && $invoice->getCustomerRequestDescription() !== $description) {
                $invoice->customer_request_description = $description;
                $invoice->save();
            }
        }
    }
}
