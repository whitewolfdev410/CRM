<?php

namespace App\Modules\WorkOrder\Services;

use Illuminate\Support\Facades\App;
use App\Modules\Bill\Repositories\BillRejectionRepository;
use App\Modules\Bill\Repositories\BillRepository;
use App\Modules\File\Repositories\FileRepository;
use App\Modules\File\Services\FileService;
use App\Modules\Kb\Repositories\CertificateRepository;
use App\Modules\PurchaseOrder\Repositories\PurchaseOrderRepository;
use App\Modules\TimeSheet\Repositories\TimeSheetRepository;
use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use Carbon\Carbon;
use Illuminate\Container\Container;

class WorkOrderVendorsService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var BillRejectionRepository
     */
    protected $billRejectionRepository;

    /**
     * @var BillRepository
     */
    protected $billRepository;

    /**
     * @var CertificateRepository
     */
    protected $certificateRepository;

    /**
     * @var FileRepository
     */
    protected $fileRepository;

    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var LinkPersonWoRepository
     */
    protected $linkPersonWoRepository;

    /**
     * @var PurchaseOrderRepository
     */
    protected $purchaseOrderRepository;

    /**
     * @var TimeSheetRepository
     */
    protected $timeSheetRepository;

    /**
     * @var TypeRepository
     */
    protected $typeRepository;

    /**
     * Initialize class
     *
     * @param Container               $app
     * @param BillRejectionRepository $billRejectionRepository
     * @param BillRepository          $billRepository
     * @param CertificateRepository   $certificateRepository
     * @param FileRepository          $fileRepository
     * @param FileService             $fileService
     * @param LinkPersonWoRepository  $linkPersonWoRepository
     * @param PurchaseOrderRepository $purchaseOrderRepository
     * @param TimeSheetRepository     $timeSheetRepository
     * @param TypeRepository          $typeRepository
     */
    public function __construct(
        Container $app,
        BillRejectionRepository $billRejectionRepository,
        BillRepository $billRepository,
        CertificateRepository $certificateRepository,
        FileRepository $fileRepository,
        FileService $fileService,
        LinkPersonWoRepository $linkPersonWoRepository,
        PurchaseOrderRepository $purchaseOrderRepository,
        TimeSheetRepository $timeSheetRepository,
        TypeRepository $typeRepository
    ) {
        $this->app = $app;
        $this->billRejectionRepository = $billRejectionRepository;
        $this->billRepository = $billRepository;
        $this->certificateRepository = $certificateRepository;
        $this->fileRepository = $fileRepository;
        $this->fileService = $fileService;
        $this->linkPersonWoRepository = $linkPersonWoRepository;
        $this->purchaseOrderRepository = $purchaseOrderRepository;
        $this->timeSheetRepository = $timeSheetRepository;
        $this->typeRepository = $typeRepository;
    }

    /**
     * Get vendor details
     *
     * @param $workOrderId
     *
     * @return mixed
     */
    public function getVendorDetails($workOrderId)
    {
        $vendorsSummary = $this->timeSheetRepository->getVendorSummary($workOrderId, true, true);

        // get assigned vendors/techs
        list($vendorsTechs, $vendorsTechsIds, $readyToInvoice, $notCompletedVendors) = $this->getVendorsTechs(
            $workOrderId,
            $vendorsSummary
        );

        // get bills
        list($vendorsTechs, $billingStatuses) = $this->getVendorsBills($vendorsTechs, $vendorsTechsIds);

        // get vendor bills rejections
        $vendorsTechs = $this->getVendorBillsRejections($vendorsTechs, $vendorsTechsIds);

        //get bills summary
        list($vendorsTechs) = $this->getBillsSummary($vendorsTechs, $billingStatuses);

        // get purchase order
        list($vendorsTechs) = $this->getVendorsPurchaseOrders($vendorsTechs, $vendorsTechsIds);

        // get files
        list($vendorsTechs) = $this->getFiles($workOrderId, $vendorsTechs);

        return [
            'items'                 => $this->removeArrayIndexes($vendorsTechs),
            'certificates'          => $this->getCertsInfoForVendors($vendorsTechsIds),
            'not_completed_vendors' => $notCompletedVendors,
            'ready_to_invoice'      => $readyToInvoice
        ];
    }

    /**
     * @param $vendorsTechsIds
     *
     * @return array
     */
    public function getCertsInfoForVendors($vendorsTechsIds)
    {
        $types = $this->typeRepository->getListByKeys([
            'certificate.general_liability',
            'certificate.workmans_comp',
            'certificate.automobile_liability'
        ], 'type_value', 'type_id');

        $certs = [];
        foreach ($vendorsTechsIds as $vendorTechsId) {
            foreach ($types as $typeId => $typeValue) {
                $certs[$vendorTechsId][$typeId] = [
                    'name'            => $typeValue,
                    'expiration_date' => null,
                    'expired'         => null
                ];
            }
        }

        $certsInfo = $this->certificateRepository->getCertsInfoForVendors($vendorsTechsIds)->get();
        foreach ($certsInfo as $info) {
            if (isset($certs[$info->person_id][$info->type_id])) {
                $expired = strtotime($info->expiration_date) <= strtotime(date('Y-m-d'));
                
                $certs[$info->person_id][$info->type_id]['expired'] = $expired;
                $certs[$info->person_id][$info->type_id]['expiration_date'] = Carbon::parse($info->expiration_date)
                    ->format('d/m/Y H:i');
            }
        }

        foreach ($certs as $personId => $info) {
            $certs[$personId] = array_values($info);
        }

        return $certs;
    }

    /**
     * Get vendor summary
     *
     * @param $workOrderId
     *
     * @return mixed
     */
    public function getVendorSummary($workOrderId)
    {
        $items = [];
        $summary = [
            'travel_time'     => null,
            'work_time'       => null,
            'total_time'      => null,
            'estimated_cost'  => 0,
            'purchase_orders' => 0,
            'bills'           => 0,
            'total_cost'      => 0,
        ];

        $vendorsSummary = $this->timeSheetRepository->getVendorSummary($workOrderId, true, true);

        $data = $this->linkPersonWoRepository->getVendorsTechs($workOrderId);
        foreach ($data as $item) {
            $travelTime = isset($vendorsSummary['travel'][$item->link_person_wo_id]->duration)
                ? substr($vendorsSummary['travel'][$item->link_person_wo_id]->duration, 0, 8)
                : null;

            $workTime = isset($vendorsSummary['work'][$item->link_person_wo_id]->duration)
                ? substr($vendorsSummary['work'][$item->link_person_wo_id]->duration, 0, 8)
                : null;

            $totalTime = isset($vendorsSummary['total'][$item->link_person_wo_id]->duration)
                ? substr($vendorsSummary['total'][$item->link_person_wo_id]->duration, 0, 8)
                : null;

            $itemData = [
                'id'                   => $item->link_person_wo_id,
                'person_id'            => $item->person_id,
                'person_name'          => $item->person_name,
                'status_type_id'       => $item->status_type_id,
                'vendor_status'        => $item->vendor_status,
                'vendor_status_key'    => $item->vendor_status_key,
                'vendor_person_status' => $item->vendor_person_status,
                'confirmed_date'       => $item->confirmed_date,
                'is_disabled'          => $item->is_disabled,
                'disabled_date'        => $item->disabled_date,
                'full_created_date'    => $item->full_created_date,
                'completion_date'      => $item->completion_date,
                'travel_time'          => $travelTime,
                'work_time'            => $workTime,
                'total_time'           => $totalTime,
                'estimated_cost'       => round(
                    $this->calculateVendorTotalTimeCost($item->getId(), $vendorsSummary),
                    2
                ),
                'purchase_orders'      => round($item->po_total_amount ?? 0, 2),
                'bills'                => round($item->bill_total_amount ?? 0, 2),
            ];

            $items[] = $itemData;

            if ($travelTime) {
                $summary['travel_time'] = $this->sumTime($summary['travel_time'], $travelTime);
            }

            if ($workTime) {
                $summary['work_time'] = $this->sumTime($summary['work_time'], $workTime);
            }

            if ($totalTime) {
                $summary['total_time'] = $this->sumTime($summary['total_time'], $totalTime);
            }

            $summary['estimated_cost'] += $itemData['estimated_cost'];
            $summary['purchase_orders'] += $itemData['purchase_orders'];
            $summary['bills'] += $itemData['bills'];
            $summary['total_cost'] += $itemData['estimated_cost'] + $itemData['purchase_orders'] + $itemData['bills'];
        }

        $summary['estimated_cost'] = round($summary['estimated_cost'], 2);
        $summary['purchase_orders'] = round($summary['purchase_orders'], 2);
        $summary['bills'] = round($summary['bills'], 2);
        $summary['total_cost'] = round($summary['total_cost'], 2);

        return [
            'items'   => $items,
            'summary' => $summary,
        ];
    }

    /**
     * Get basic list of vendors
     *
     * @param int   $workOrderId
     * @param array $vendorSummary
     *
     * @return array
     */
    protected function getVendorsTechs($workOrderId, array $vendorSummary)
    {
        $data = $this->linkPersonWoRepository->getVendorsTechs($workOrderId);

        $vendorCompleted = $this->typeRepository->getIdByKey('wo_vendor_status.completed');
        $vendorCanceled = $this->typeRepository->getIdByKey('wo_vendor_status.canceled');
        $personTechnician = $this->typeRepository->getIdByKey('person.technician');
        $companySupplier = $this->typeRepository->getIdByKey('company.supplier');
        $companyStatusDisabled = $this->typeRepository->getIdByKey('company_status.disabled');

        $vendors = [];
        $ids = [];

        $readyToInvoice = false;
        $notCompletedVendors = 0;
        if (count($data)) {
            $readyToInvoice = true;
        }
        foreach ($data as $item) {
            $item->type = ucfirst($item->getType());

            if (!$item->getIsDisabled()
                && ($item->getType() == 'Work' || $item->getType() == 'Recall')
                && ($item->getStatusTypeId() != $vendorCompleted && $item->getStatusTypeId() != $vendorCanceled)
            ) {
                ++$notCompletedVendors;
            }
            $item->is_tech = $item->vendor_type == $personTechnician;
            $item->is_supplier = $item->vendor_type == $companySupplier;

            $numbers = $item->getId() * 37;
            $temp = (string)$numbers;
            $num = 0;
            for ($i = 0; $i < strlen($numbers); $i++) {
                $num += $temp[$i];
            }

            $item->pin = $numbers . ($num % 10);
            $item->total_time_cost = $this->calculateVendorTotalTimeCost($item->getId(), $vendorSummary);

            $item->bill_final_checked = 0;
            if ($item->bill_final != 2) {
                if ($item->bill_final == 1) {
                    $item->bill_final_checked = 1;
                } else {
                    $readyToInvoice = false;
                }
            }

            $item->mark_as_disabled = $item->vendor_person_status == $companyStatusDisabled;

            $ids[] = $item->getId();
            $vendors[$item->getId()] = $item;
        }

        return [$vendors, $ids, $readyToInvoice, $notCompletedVendors];
    }

    /**
     * Get vendors bills
     *
     * @param array $vendorTechs
     * @param array $vendorTechsIds
     *
     * @return array
     */
    protected function getVendorsBills(array $vendorTechs, array $vendorTechsIds)
    {
        $billIds = [];
        $fileIds = [];
        $bookingStatuses = [];

        $receipts = $this->typeRepository->getList('bill_entry');

        $bills = $this->billRepository->getForLinkedPersonWo($vendorTechsIds);

        foreach ($bills as $bill) {
            $fileIds[] = $bill->file_id;
        }

        $files = $this->fileRepository->getFileLinksByFileIds($fileIds);
        /** @var \App\Modules\Bill\Models\Bill $bill */
        foreach ($bills as $bill) {
            $id = $bill->getLinkPersonWoId();
            $billId = $bill->getId();
            if (!isset($vendorTechs[$id]->bills)) {
                $vendorTechs[$id]->bills = [];
            }
            $curBills = $vendorTechs[$id]->bills;

            if (!isset($curBills[$billId])) {
                $supplierName = null;
                if (!empty($bill->getCompanyPersonId())) {
                    $supplierName = $bill->person_name;
                }
                $bill->supplier_name = $supplierName;

                $status = null;
                if (!empty($bill->getStatusTypeId())) {
                    $status = $bill->status()->first()->getTypeValue();
                }

                $curBills = array_replace(
                    $curBills,
                    [
                        $billId => [
                            'bill_id'              => $billId,
                            'file'                 => isset($files['links'][$bill->file_id])
                                ? array_merge(['id' => $bill->file_id], $files['links'][$bill->file_id])
                                : null,
                            'final'                => $bill->final,
                            'number'               => $bill->getNumber(),
                            'amount'               => $bill->getAmount(),
                            'payment_terms_name'   => $bill->payment_terms_name,
                            'bill_date'            => $bill->getBillDate(),
                            'bill_status_id'       => $bill->getStatusTypeId(),
                            'bill_status_id_value' => $status,
                            'created_date'         => $bill->getCreatedAt(),
                            'supplier_name'        => $supplierName,
                            'invoice_number'       => $bill->invoice_number
                        ],
                    ]
                );

                if (trim($billId) != '') {
                    $billIds[] = trim($billId);
                }
            }

            if ($bill->bill_entry_id) {
                $curBills[$billId]['entries'][] = [
                    'qty'                => $bill->qty,
                    'total'              => $bill->total,
                    'service1'           => $bill->service1,
                    'service2'           => $bill->service2,
                    'item'               => $bill->item,
                    'type_name'          => $bill->type_name,
                    'item_code'          => $bill->item_code,
                    'description'        => $bill->description,
                    'non_used_qty'       => $bill->unused_qty,
                    'number_of_men'      => $bill->men_count,
                    'tax'                => $bill->tax_amount ?? 0,
                    'total_with_tax'     => ($bill->tax_amount ?? 0) + $bill->total,
                    'approval_person_id' => $bill->approval_person_id,
                    'invoice_entry_id'   => $bill->invoice_entry_id,
                    'receipt'            => isset($bill->receipt) && isset($receipts[$bill->receipt])
                        ? $receipts[$bill->receipt]
                        : null,
                    'price'              => $bill->price
                ];
            }

            $vendorTechs[$id]->bills = $curBills;
        }

        if ($billIds) {
            $bookingConfig = $this->app->config->get('services.booking');

            if ($bookingConfig['enabled'] === true) {
                $bookingService = $this->app->make($bookingConfig['class']);
                $bookingService->setConnection($bookingConfig['connection']);
                $bookingStatuses = $bookingService->getBillsSyncStatus($billIds);
            }
        }

        return [$vendorTechs, $bookingStatuses];
    }


    /**
     * Get vendors bill rejections
     *
     * @param array $vendorTechs
     * @param array $vendorTechsIds
     *
     * @return mixed
     */
    protected function getVendorBillsRejections(array $vendorTechs, array $vendorTechsIds)
    {
        $rejections = $this->billRejectionRepository->getForLinkedPersonWo($vendorTechsIds);

        /** @var \App\Modules\Bill\Models\BillRejection $rejection */
        foreach ($rejections as $rejection) {
            $vendorId = $rejection['link_person_wo_id'];
            $curBills = $vendorTechs[$vendorId]->bills;
            $curBills[$rejection->getBillId()]['rejections'][] = $rejection;
            $vendorTechs[$vendorId]->bills = $curBills;
        }

        return $vendorTechs;
    }

    /**
     * Get Bills summary
     *
     * @param array $vendorsTechs
     * @param array $billingStatuses
     *
     * @return array
     */
    protected function getBillsSummary(array $vendorsTechs, array $billingStatuses)
    {
        $allBillsTotal = 0;

        if ($vendorsTechs) {
            foreach ($vendorsTechs as $id => $vendor) {
                $btotal = 0.0;

                if (isset($vendor->bills)) {
                    foreach ($vendor->bills as $billId => $bill) {
                        if (!isset($bill['rejections']) || empty($bill['rejections'])) {
                            $btotal += floatval($bill['amount']);
                        }

                        $curBills = $vendorsTechs[$id]->bills;
                        $curBills[$billId]['qb_sync_status'] = isset($billingStatuses[$billId]['sync_status'])
                            ? $billingStatuses[$billId]['sync_status']
                            : null;

                        $vendorsTechs[$id]->bills = $curBills;
                    }
                }
                $vendorsTechs[$id]->bills_total = $btotal;
                $allBillsTotal += $btotal;
            }
        }

        return [$vendorsTechs, $allBillsTotal];
    }


    /**
     * Get vendors purchase orders
     *
     * @param array $vendorsTechs
     * @param array $vendorsTechsIds
     *
     * @return array
     */
    protected function getVendorsPurchaseOrders(array $vendorsTechs, array $vendorsTechsIds)
    {
        $orderIds = [];
        $allOrdersTotal = 0;

        $orders = $this->purchaseOrderRepository->getForLinkedPersonWo($vendorsTechsIds);

        /** @var \App\Modules\PurchaseOrder\Models\PurchaseOrder $order */
        foreach ($orders as $order) {
            $vendorId = $order->getLinkPersonWoId();
            $purchaseId = $order->getId();

            $po = isset($vendorsTechs[$vendorId]->purchase_orders) ? $vendorsTechs[$vendorId]->purchase_orders : null;
            if ($po === null || !isset($po[$purchaseId])) {
                $po[$purchaseId] = (object)[
                    'purchase_order_id'   => $purchaseId,
                    'number'              => $order->getPurchaseOrderNumber(),
                    'purchase_order_date' => $order->purchase_order_date,
                ];
                $orderIds[] = $purchaseId;
            }

            if ($order->purchase_order_entry_id) {
                $po[$purchaseId]->entries[] = (object)[
                    'qty'                     => $order->quantity,
                    'total'                   => $order->total,
                    'item'                    => $order->item,
                    'purchase_order_entry_id' => $order->purchase_order_entry_id,
                ];
            }

            $vendorsTechs[$vendorId]->purchase_orders = $po;
        }

        if ($orderIds) {
            $totals = $this->purchaseOrderRepository->getTotals($orderIds);

            /** @var \App\Modules\PurchaseOrder\Models\PurchaseOrder $total */
            foreach ($totals as $total) {
                $id = $total->getId();
                $vendorId = $total->lpwo_id;

                $po = isset($vendorsTechs[$vendorId]->purchase_orders) ? $vendorsTechs[$vendorId]->purchase_orders : null;
                $po[$id]->total = $total->total;
                $vendorsTechs[$vendorId]->purchase_orders = $po;

                if (isset($vendorsTechs[$vendorId]->purchase_orders_total)) {
                    $vendorsTechs[$vendorId]->purchase_orders_total += $total->total;
                } else {
                    $vendorsTechs[$vendorId]->purchase_orders_total = $total->total;
                }

                $allOrdersTotal += $total->total;
            }
        }

        return [$vendorsTechs, $allOrdersTotal];
    }


    /**
     * Get files work Work order with given id and for its vendors
     *
     * @param       $workOrderId
     * @param array $vendorsTechs
     *
     * @return array
     */
    protected function getFiles($workOrderId, array $vendorsTechs)
    {
        $preFiles = $this->fileRepository->getForWo($workOrderId);

        $workOrderFilesCount = 0;
        $files = [];
        $filesData = [];

        foreach ($preFiles as $f) {
            $filesData[$f->file_id] = $f->filename;
        }

        // get links for files
        $links = $this->fileService->getFileLinks(array_keys($filesData), $filesData);

        foreach ($preFiles as $f) {
            $f->list_only = (strpos($f->filename, '_signature_') !== false) ? 1 : 0;
            $f->type = substr($f->filename, -3);

            if (isset($f->type) && (in_array(mb_strtolower($f->type), ['jpg', 'gif', 'png']))) {
                $f->is_image = true;
            } else {
                $f->is_image = false;
            }

            if (isset($links['links'][$f->file_id])) {
                $f->link = $links['links'][$f->file_id]['link'];
                $f->thumbnail = $links['links'][$f->file_id]['thumbnail'];
            } else {
                $f->link = null;
                $f->thumbnail = null;
            }

            if ($f->table_name == 'link_person_wo') {
                $data = [];

                if (isset($vendorsTechs[$f->table_id]['files'])) {
                    $data = $vendorsTechs[$f->table_id]['files'];
                }

                $data[$f->file_id] = $f;
                $vendorsTechs[$f->table_id]['files'] = $data;
            // @todo - separate queries - one for vendors one for workorder
                // other work order files
            } else {
                $files[$f->file_id] = $f;
                ++$workOrderFilesCount;
            }
        }

        return [$vendorsTechs, $files, $workOrderFilesCount];
    }

    /**
     * Calculate time cost for vendor with given id
     *
     * @param int   $id
     * @param array $vendorSummary
     *
     * @return float
     */
    protected function calculateVendorTotalTimeCost($id, array $vendorSummary)
    {
        $workSec = isset($vendorSummary['work'][$id]['duration_sec']) ?
            $vendorSummary['work'][$id]['duration_sec'] : 0;

        $travelSec = isset($vendorSummary['travel'][$id]['duration_sec']) ?
            $vendorSummary['travel'][$id]['duration_sec'] : 0;

        return $this->calculateRealCost($workSec, $travelSec);
    }

    /**
     * Calculate cost for work and travel
     *
     * @param $work
     * @param $travel
     *
     * @return float
     */
    protected function calculateRealCost($work, $travel)
    {
        $hourCost = $this->getHourCost();

        return $hourCost * ((intval($work) + intval($travel)) / 3600);
    }

    /**
     * Get hour cost
     *
     * @return float
     */
    protected function getHourCost()
    {
        return floatval($this->app->config->get('system_settings.hour_cost'));
    }

    /**
     * Remove all indexes from array fix for json object
     *
     * @param $vendorsTechs
     *
     * @return array
     */
    private function removeArrayIndexes($vendorsTechs)
    {
        foreach ($vendorsTechs as $index => $vendor) {
            if (isset($vendorsTechs[$index]->bills) && is_array($vendorsTechs[$index]->bills)) {
                $vendorsTechs[$index]->bills = array_values($vendorsTechs[$index]->bills);
            }

            if (isset($vendorsTechs[$index]->files) && is_array($vendorsTechs[$index]->files)) {
                $vendorsTechs[$index]->files = array_values($vendorsTechs[$index]->files);
            }

            if (isset($vendorsTechs[$index]->purchase_orders) && is_array($vendorsTechs[$index]->purchase_orders)) {
                $vendorsTechs[$index]->purchase_orders = array_values($vendorsTechs[$index]->purchase_orders);
            }

            if (!isset($vendorsTechs[$index]->files)) {
                $vendorsTechs[$index]->files = [];
            }
        }

        return array_values($vendorsTechs);
    }

    /**
     * The sum of two times
     *
     * @param $time1
     * @param $time2
     *
     * @return false|string
     */
    private function sumTime($time1, $time2)
    {
        if (is_null($time1) && is_null($time2)) {
            return null;
        } else {
            if (is_null($time1)) {
                return $time2;
            } else {
                if (is_null($time2)) {
                    return $time1;
                }
            }
        }

        return gmdate('h:i:s', strtotime('1900-01-01 ' . $time1) + strtotime('1900-01-01 ' . $time2));
    }
}
