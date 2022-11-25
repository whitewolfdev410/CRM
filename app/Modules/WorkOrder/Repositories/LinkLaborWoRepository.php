<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\File\Models\File;
use App\Modules\File\Repositories\FileRepository;
use App\Modules\File\Services\FileService;
use App\Modules\MsDynamics\Services\MsDynamicsService;
use App\Modules\WorkOrder\Http\Requests\AcceptLaborsRequest;
use App\Modules\WorkOrder\Models\LinkLaborWo;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * LinkLaborWo repository class
 */
class LinkLaborWoRepository extends AbstractRepository
{
    const PER_PAGE = 50;
    /**
     * {@inheritdoc}
     */
    protected $searchable = [
        'work_order_id',
        'person_id',
        'inventory_id',
        'name',
        'description',
        'quantity',
        'quantity_from_sl'
    ];

    protected $availableColumns = [
        'id'                => 'link_labor_wo.link_labor_wo_id',
        'work_order_id'     => 'link_labor_wo.work_order_id',
        'person_id'         => 'link_labor_wo.person_id',
        'person_name'       => 'person_name(link_labor_wo.person_id)',
        'inventory_id'      => 'link_labor_wo.inventory_id',
        'name'              => 'link_labor_wo.name',
        'description'       => 'link_labor_wo.description',
        'quantity'          => 'link_labor_wo.quantity',
        'quantity_from_sl'  => 'link_labor_wo.quantity_from_sl',
        'is_deleted'        => 'link_labor_wo.is_deleted',
        'comment'           => 'link_labor_wo.comment',
        'reason_type_value' => 't(link_labor_wo.reason_type_id)',
        'updated_at'        => 'link_labor_wo.updated_at',
    ];

    /**
     * Columns that might be used for sorting
     *
     * @var array
     */
    protected $sortable = [];

    /**
     * Repository constructor
     *
     * @param Container   $app
     * @param LinkLaborWo $linkLaborWo
     */
    public function __construct(Container $app, LinkLaborWo $linkLaborWo)
    {
        parent::__construct($app, $linkLaborWo);
    }

    /**
     * {@inheritdoc}
     */
    public function paginate($perPage = 50, array $columns = ['*'], array $order = [])
    {
        /** @var LinkLaborWo|Builder $model */
        $model = $this->model;

        $inputs = $this->getInput();

        /*
         * Custom filters, sort, and select created using availableColumns.
         */
        $model = $this->setCustomColumns($model);
        $model = $this->setCustomSort($model);
        $model = $this->setCustomFilters($model);

        if(!empty($inputs['site_id'])) {
            $siteId = $inputs['site_id'];

            $model = $model->join('work_order', function($join) use ($siteId) {
                $join
                    ->on('work_order.work_order_id', '=' , 'link_labor_wo.work_order_id')
                    ->where('work_order.fin_loc', $siteId);
                });
        }        
        
        if(!empty($inputs['limit'])) {
            $model = $model->limit(min($inputs['limit'], 100));
        }
        
        /*
         * Paginate with empty $columns, using old paginate.
         */
        $this->setWorkingModel($model);
        $data = parent::paginateSimple($perPage, [], $order);
        
        $this->clearWorkingModel();
        
        if (!empty($inputs['with_files'])) {
            /** @var FileRepository $fileRepository */
            $fileRepository = $this->app->make(FileRepository::class);
            /** @var Asset $item */
            $fileRepository->getFilesForLinkLaborWos($data);
        }
        
        $data = $data->toArray();
        
        foreach ($data['data'] as $index => $values) {
            $data['data'][$index]['aforementioned_reason'] = $values['reason_type_value'];
            if (!empty($values['comment'])) {
                $data['data'][$index]['aforementioned_reason'] .= ': ' . $values['comment'];
            }
        }
        
        return $data;
    }
    
    public function getCustomerPortalPaginator($billingCompanyPersonIds, array $filters = [], $perPage = self::PER_PAGE)
    {
        $this->input = $this->request->all();
        
        /** @var LinkLaborWo|Object $model */
        $model = $this->getModel();

        //Get only needed fields
        $query = DB::connection('mysql-utf8')->table($model->getTable())
            ->select(
                'link_labor_wo.link_labor_wo_id',
                'link_labor_wo.inventory_id',
                'link_labor_wo.name',
                'link_labor_wo.description',
                'link_labor_wo.quantity',
                'link_labor_wo.created_at',
                'work_order.work_order_number',
                'address.address_name',
                'address.address_1',
                'address.address_2',
                'address.city',
                'address.zip_code',
                'address.state',
                'address.site_id',
                'address.site_name',
                'address.latitude',
                'address.longitude',
                DB::raw('person_name(link_labor_wo.person_id) as creator')
            )
            ->join('work_order', 'work_order.work_order_id', '=', 'link_labor_wo.work_order_id')
            ->join('address', 'work_order.shop_address_id', '=', 'address.address_id')
            ->join('person as cr', 'link_labor_wo.person_id', '=', 'cr.person_id')
            ->leftJoin('person as cmp', 'address.person_id', '=', 'cmp.person_id')
            
            ->whereNotNull('link_labor_wo.accepted_at');
        
        if (!empty($billingCompanyPersonIds)) {
            //only records with this company id
            $query->whereRaw("cmp.custom_8 IN (" . implode(',', $billingCompanyPersonIds) . ")");
        }

        //If filters exist
        if (!empty($filters)) {
            if (!empty($filters['customer'])) {
                $query->where('cmp.person_id', '=', $filters['customer']);
            }

            if (!empty($filters['name'])) {
                $query->where('link_labor_wo.name', 'like', $filters['name'].'%');
            }
            
            if (!empty($filters['work_order_number'])) {
                $query->where('work_order.work_order_number', 'like', $filters['work_order_number'].'%');
            }
            
            if (!empty($filters['address_name'])) {
                $query->where(function ($q) use ($filters) {
                    // fix for copying two columns next to each other search
                    $filters['address_name'] = str_replace("\t", ' ', $filters['address_name']);

                    // explode to address parts
                    $parts = explode(' ', $filters['address_name']);

                    foreach ($parts as $part) {
                        $part = trim($part, ',');

                        if (strlen($part)) {
                            // search in all address columns
                            $q->where(function ($q2) use ($part) {
                                $q2->where('address.address_name', 'like', '%'.$part.'%')
                                    ->orWhere('address.site_id', 'like', '%'.$part.'%')
                                    ->orWhere('address.site_name', 'like', '%'.$part.'%');
                            });
                        }
                    }
                });
            }

            if (!empty($filters['date_from'])) {
                $dateFrom = \DateTime::createFromFormat('Y-m-d', $filters['date_from']);
                if ($dateFrom) {
                    $dateFrom->setTime(0, 0, 0);
                    $query->where('link_labor_wo.created_at', '>=', $dateFrom->format('Y-m-d H:i:s'));
                }
            }
            
            if (!empty($filters['date_to'])) {
                $dateTo = \DateTime::createFromFormat('Y-m-d', $filters['date_to']);
                if ($dateTo) {
                    $dateTo->setTime(23, 59, 59);
                    $query->where('link_labor_wo.created_at', '<=', $dateTo->format('Y-m-d H:i:s'));
                }
            }
            
            if (!empty($filters['sort_direction'])) {
                if (!in_array($filters['sort_direction'], ['asc', 'desc'])) {
                    $sortDirection = 'asc';
                } else {
                    $sortDirection = $filters['sort_direction'];
                }
                //sort fields map -values with table aliases
                $orderMap = [
                    'address_id' => 'address.address_id',
                    'address_name' => 'address.address_name',
                    'address_1' => 'address.address_1',
                    'creator' => ['c.custom_1', 'c.custom_3'],
                    'company' => 'person.custom_1',
                    'created_at' => 'address.created_at',
                ];
                //If sort by is not empty then loop on field records and add order by to query
                if (!empty($filters['sort_by']) && isset($orderMap[$filters['sort_by']])) {
                    if (is_array($orderMap[$filters['sort_by']])) {
                        foreach ($orderMap[$filters['sort_by']] as $sortBy) {
                            $query->orderBy($sortBy, $sortDirection);
                        }
                    } else {
                        $query->orderBy($orderMap[$filters['sort_by']], $sortDirection);
                    }
                } else {
                    $query->orderBy('link_labor_wo.link_labor_wo_id', $sortDirection);
                }
            } else {
                // default order by date DESC
                $query->orderByDesc('link_labor_wo.created_at');
            }
        } else {
            // default order by date DESC
            $query->orderByDesc('link_labor_wo.created_at');
        }

        //Get records count
        $recordCount = $query->count();

        if (!empty($filters['export'])) {
            // export all
            $labors = $query->get();
        } else {
            //Paginate query
            $labors = $query->paginate($perPage);
        }

        return [
            'perPage' => $perPage,
            'recordCount' => $recordCount,
            'labors' => $labors,
            'query' => $query->toSql()
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function toAcceptPaginate($perPage = 10, array $columns = ['*'], array $order = [])
    {
        $inputs = $this->getInput();

        /** @var LinkLaborWo|Builder $model */
        $model = $this->model
            ->select([
                'work_order.work_order_id',
                'work_order.work_order_number',
                'work_order.company_person_id',
                'work_order.fin_loc',
                'customer_settings.customer_settings_id',
                'link_person_wo.tech_status_date as serviced_at',
                'link_labor_wo.person_id',
                DB::raw('max(link_labor_wo.updated_at) as updated_at'),
                DB::raw('person_name(link_labor_wo.person_id) as person_name'),
                DB::raw('person_name(work_order.company_person_id) as company_name'),
                DB::raw('sl_records.sl_record_id')
            ])
            ->join('work_order', 'link_labor_wo.work_order_id', '=', 'work_order.work_order_id')
            ->join('link_person_wo', function ($join) {
                $join
                    ->on('link_person_wo.work_order_id', '=', 'link_labor_wo.work_order_id')
                    ->on('link_person_wo.person_id', '=', 'link_labor_wo.person_id')
                    ->on('link_person_wo.is_disabled', '=', DB::raw('0'));
            })
            ->leftJoin('sl_records', function ($join) {
                $join
                    ->on('sl_records.record_id', '=', 'work_order.company_person_id')
                    ->where('sl_table_name', '=', 'Customer');
            })
            ->leftJoin('customer_settings', 'customer_settings.company_person_id', '=', 'work_order.company_person_id')
            ->whereNull('link_labor_wo.accepted_person_id')
            ->groupBy('link_labor_wo.work_order_id')
            ->groupBy('link_labor_wo.person_id');
        
        if (empty($inputs['sort'])) {
            $model = $model->orderBy('link_person_wo.tech_status_date');
        } else {
            $model = $this->setCustomSort($model, [
                'serviced_at' => 'link_person_wo.tech_status_date',
                'updated_at' => 'max(link_labor_wo.updated_at)',
                'customer_id' => 'sl_records.sl_record_id',
                'fin_loc' => 'work_order.fin_loc',
            ]);
        }
        
        if (!empty($inputs['customer_id'])) {
            $model = $model->where('sl_records.sl_record_id', 'like', $inputs['customer_id']);

            unset($inputs['customer_id']);
            $this->setInput($inputs);
        }
        
        if (!empty($inputs['person_id'])) {
            $model = $model->where('link_labor_wo.person_id', $inputs['person_id']);

            unset($inputs['person_id']);
            $this->setInput($inputs);
        }

        if (!empty($inputs['work_order_number'])) {
            $model = $model->where('work_order.work_order_number', 'like', $inputs['work_order_number']);

            unset($inputs['work_order_number']);
            $this->setInput($inputs);
        }

        if (!empty($inputs['site_id'])) {
            $model = $model->where('work_order.fin_loc', 'like', $inputs['site_id']);

            unset($inputs['site_id']);
            $this->setInput($inputs);
        }

        if (!empty($inputs['updated_at'])) {
            $model = $model->where(DB::raw('DATE(link_labor_wo.updated_at)'), $inputs['updated_at']);

            unset($inputs['updated_at']);
            $this->setInput($inputs);
        }

        if (!empty($inputs['serviced_at'])) {
            $model = $model->where(DB::raw('DATE(link_person_wo.tech_status_date)'), $inputs['serviced_at']);

            unset($inputs['serviced_at']);
            $this->setInput($inputs);
        }

        if (!empty($inputs['labor'])) {
            $inventoryId = explode(',', $inputs['labor']);

            $model = $model->whereIn('link_labor_wo.inventory_id', $inventoryId);

            unset($inputs['labor']);
            $this->setInput($inputs);
        }

        
        
        $workOrders = $model->get();

        $paginate = new LengthAwarePaginator($workOrders, $workOrders->count(), $perPage, $inputs['page'] ?? 1, [
            'path'  => request()->url(),
            'query' => request()->query()
        ]);

        $paginate = $paginate->toArray();

        $workOrderIds = array_column($paginate['data'], 'work_order_id');
        $workOrderNumbers = array_column($paginate['data'], 'work_order_number');
        $siteIds = array_column($paginate['data'], 'fin_loc');
            
        /** @var MsDynamicsService $msDynamicsService */
        $msDynamicsService = app(MsDynamicsService::class);
        
        $invoices = $msDynamicsService->getInvoiceSummaryByWorkOrderNumbers($workOrderNumbers);
        
        $workOrderInvoices = [];
        foreach ($invoices as $invoice) {
            $workOrderInvoices[$invoice->work_order_number] = [
                'total_before_changes' => (float)$invoice->total,
                'total_after_changes' => (float)$invoice->total
            ];
        }

        $labors = $this->model->newInstance()
            ->select([
                'link_labor_wo.link_labor_wo_id',
                'link_labor_wo.work_order_id',
                'link_labor_wo.person_id',
                'link_labor_wo.inventory_id',
                'link_labor_wo.seq_number',
                'link_labor_wo.accepted_person_id',
                'link_labor_wo.is_deleted',
                'link_labor_wo.comment',
                'link_labor_wo.reason_type_id',
                DB::raw('t(link_labor_wo.reason_type_id) as reason_type_value'),
                DB::raw('round(link_labor_wo.unit_price, 2) as unit_price'),
                DB::raw('link_labor_wo.quantity_from_sl as quantity_before'),
                DB::raw('link_labor_wo.quantity as quantity_after'),
                DB::raw('link_labor_wo.description as labor'),
                DB::raw('link_labor_wo.name'),
                DB::raw('work_order.fin_loc as site_id')
            ])
            ->join('work_order', 'work_order.work_order_id', '=', 'link_labor_wo.work_order_id')
            ->whereIn('link_labor_wo.work_order_id', $workOrderIds)
            ->where(function ($query) {
                $query
                    ->whereNull('link_labor_wo.accepted_person_id')
                    ->orWhere('link_labor_wo.accepted_person_id', '!=', '-1');
            })
//            ->
//            ->orderBy('link_labor_wo.seq_number')
            ->orderBy('link_labor_wo.name')
            ->get();

        //$pricing = $msDynamicsService->getPricingBySiteIds($siteIds);

        //CRMBFC-3323 - change of query for the pricing
        $inventoryIds = array_unique(array_column($labors->toArray(), 'inventory_id'));
        $pricing = $msDynamicsService->getPricingBySiteIdsAndInventoryIds($siteIds, $inventoryIds);

        $linkLaborWoIds = array_column($labors->toArray(), 'link_labor_wo_id');

        /** @var FileService $fileService */
        $fileService = app(FileService::class);
        
        /** @var FileRepository $fileRepository */
        $fileRepository = app(FileRepository::class);
        
        $fileLinks = [];
        $files = $fileRepository->getFilesForLinkLaborWoIds($linkLaborWoIds);
        if ($files->count()) {
            $mapFileIdToTableId = [];
            /** @var File $file */
            foreach ($files as $file) {
                $mapFileIdToTableId[$file->getId()] = $file->getTableId();
            }
            
            $links = $fileService->getS3Link($files);
            foreach ($links as $fileId => $link) {
                $tableId = $mapFileIdToTableId[$fileId];
                
                if (!isset($fileLinks[$tableId])) {
                    $fileLinks[$tableId] = [];
                }
                
                $fileLinks[$tableId][] = $link;
            }
        }
               
        
//        $workOrderIds = array_column($labors->toArray(), 'work_order_id');
        
        
//        $invoicePhotos = $fileService->getLinks($workOrderIds, 'link_person_wo')
        
        $workOrderLabors = [];
        foreach ($labors as $labor) {
            $inventory = trim($labor->inventory_id) . '_' . trim($labor->asset_name);

            if (isset($pricing[$labor->site_id][$inventory])) {
                $labor->unit_price = (float)$pricing[$labor->site_id][$inventory];
            } else {
                $labor->unit_price = (float)$labor->unit_price;
            }

            $labor->quantity_before = (int)$labor->quantity_before;
            $labor->quantity_after = (int)$labor->quantity_after;
            $labor->quantity_after_orig = $labor->quantity_after;
            $labor->total_before = $labor->unit_price ? $labor->unit_price * $labor->quantity_before : 0;
            $labor->total_after = $labor->unit_price ? $labor->unit_price * $labor->quantity_after : 0;
            $labor->disabled = false;
            
            $workOrderLabors[$labor->work_order_id][$labor->person_id][] = $labor;
            
            $labor->file_links = isset($fileLinks[$labor->getId()]) ? $fileLinks[$labor->getId()] : [];

            $labor->aforementioned_reason = $labor->reason_type_value;
            if (!empty($labor->comment)) {
                $labor->aforementioned_reason .= ': ' . $labor->comment;
            }
        }

        foreach ($paginate['data'] as $index => $workOrder) {
            $paginate['data'][$index]['invoice_file_link'] = $fileService->getInvoicePhotoByWorkOrderIdAndPersonId($workOrder['work_order_id'], $workOrder['person_id']);
            $paginate['data'][$index]['company_name'] = $paginate['data'][$index]['company_name'] . ' (' . $paginate['data'][$index]['sl_record_id'] . ')';
            $paginate['data'][$index]['labors'] = $workOrderLabors[$workOrder['work_order_id']][$workOrder['person_id']];
            $paginate['data'][$index]['invoice'] = isset($workOrderInvoices[$workOrder['work_order_number']])
                ? $workOrderInvoices[$workOrder['work_order_number']]
                : ['total_before_changes' => 0, 'total_after_changes' => 0];


            $difference = 0;
            foreach ($workOrderLabors[$workOrder['work_order_id']][$workOrder['person_id']] as $labor) {
                $difference += ($labor->quantity_before - $labor->quantity_after) * $labor->unit_price;
            }

            $paginate['data'][$index]['invoice']['total_after_changes'] -= $difference;
        }

        if (!empty($inputs['sort']) && strpos($inputs['sort'], 'total_before_changes') !== false) {
            $desc = substr($inputs['sort'], 0, 1) === '-';

            usort($paginate['data'], function ($a, $b) use ($desc) {
                $cmp = (float)$a['invoice']['total_before_changes'] <=> (float)$b['invoice']['total_before_changes'];
                
                return $desc ? -$cmp : $cmp;
            });
        }
        
        return $paginate;
    }

    public function acceptLabors(AcceptLaborsRequest $acceptLaborsRequest)
    {
        $formLabors = [];
        foreach ($acceptLaborsRequest['labors'] as $labor) {
            if (isset($labor['is_new']) && $labor['is_new']) {
                $labor = $this->createLabor($labor);
            }
            
            $formLabors[$labor['id']] = $labor;
        }
        
        $laborIds = array_keys($formLabors);
        
        $labors = $this->model
            ->whereIn('link_labor_wo_id', $laborIds)
            ->get();

        /** @var MsDynamicsService $msDynamicsService */
        $msDynamicsService = app(MsDynamicsService::class);
        
        /** @var LinkLaborWo $labor */
        foreach ($labors as $labor) {
            $originalLabor = clone $labor;
            
            $changeInventory = false;
            
            $formLabor = $formLabors[$labor->getId()];
            if ($formLabor['inventory_id'] !== $labor->inventory_id) {
                $changeInventory = true;

                $labor->inventory_id = $formLabor['inventory_id'];
            }

            if ($formLabor['name'] !== $labor->name) {
                $changeInventory = true;

                $labor->name = $formLabor['name'];
            }
            
            $labor->accepted_quantity = (int)$formLabor['quantity_after'];
            $labor->accepted_at = date('Y-m-d H:i:s');
            $labor->accepted_person_id = Auth::user()->getPersonId();
            $labor->is_deleted = $formLabor['is_deleted'] ?? 0;
            $labor->reason_type_id = $formLabor['reason_type_id'] ?? null;
            $labor->comment = $formLabor['comment'] ?? null;
            $labor->save();
            $labor->refresh();

            $undelete = $originalLabor->is_deleted && !$labor->is_deleted;
            
            if ($labor->is_deleted) {
                $msDynamicsService->removeLaborFromSmPmModel($originalLabor);
            } elseif ($labor->getQuantity() !== $labor->getAcceptedQuantity() || $changeInventory || $undelete) {
                if ($undelete) {
                    if (!$msDynamicsService->getLabor($labor)) {
                        $msDynamicsService->createLaborInSmPmModel($labor);
                    }
                } else {
                    $labor->quantity = (int) $formLabor['quantity_after'];
                    $labor->quantity_from_sl = -1;

                    $msDynamicsService->updateLaborInSmPmModel($labor, $originalLabor);
                }
            }
        }

        $msDynamicsService->updateInvoice($acceptLaborsRequest['work_order_number']);
        
        return [
            'success' => true
        ];
    }

    private function createLabor($labor)
    {
        /** @var LinkLaborWo $linkLaborWo */
        $linkLaborWo = $this->create([
            'work_order_id' => $labor['work_order_id'],
            'person_id' => Auth::user()->getPersonId(),
            'inventory_id' => $labor['inventory_id'],
            'name' => clean_string($labor['name']),
            'description' => clean_string($labor['labor']),
            'quantity_from_sl' => null,
            'quantity' => $labor['quantity_after'],
            'unit_price' => $labor['unit_price']
        ]);

        app(MsDynamicsService::class)->createLaborInSmPmModel($linkLaborWo);

        $labor['id'] = $linkLaborWo->getId();
        
        return $labor;
    }

    /**
     * Marking the duplicated record as accepted so that it does not get sent to the SL database.
     *
     * @param $workOrderId
     * @param $inventoryId
     * @param $seqNumber
     * @param $name
     */
    public function disableActiveDuplicate($workOrderId, $inventoryId, $seqNumber, $name)
    {
        try {
            $this->model
                ->whereNull('accepted_person_id')
                ->where('work_order_id', $workOrderId)
                ->where('inventory_id', $inventoryId)
                ->where('seq_number', $seqNumber)
                ->where('name', $name)
                ->update([
                    'accepted_person_id' => -1,
                    'accepted_at'        => Carbon::now()->format('Y-m-d H:i:s')
                ]);
        } catch (\Exception $e) {
        }
    }

    /**
     * @param  array  $linkLaborWoIds
     *
     * @return mixed
     */
    public function getByLinkLaborWoIds(array $linkLaborWoIds)
    {
        return $this->model
            ->whereIn('link_labor_wo_id', $linkLaborWoIds)
            ->get();
    }
}
