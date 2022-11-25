<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\WorkOrder\Models\LinkLaborFileRequired;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * LinkLaborFileRequiredRepository class
 */
class LinkLaborFileRequiredRepository extends AbstractRepository
{
    /**
     * Repository constructor
     *
     * @param Container             $app
     * @param LinkLaborFileRequired $linkLaborFileRequired
     */
    public function __construct(Container $app, LinkLaborFileRequired $linkLaborFileRequired)
    {
        parent::__construct($app, $linkLaborFileRequired);
    }

    /**
     * @param int $customerSettingsId
     * @param     $inventory_id
     *
     * @return mixed
     */
    public function getLaborLinkFileByCustomerSettingsIdAndInventoryId(int $customerSettingsId, $inventory_id)
    {
        return $this->model
            ->where('customer_settings_id', $customerSettingsId)
            ->where('inventory_id', $inventory_id)
            ->first();
    }

    /**
     * @param int   $customerSettingsId
     * @param array $linkLaborFileRequiredIds
     *
     * @return mixed
     */
    public function removeNotExistingFiles(int $customerSettingsId, array $linkLaborFileRequiredIds)
    {
        return $this->model
            ->where('customer_settings_id', $customerSettingsId)
            ->whereNotIn('link_labor_file_required_id', $linkLaborFileRequiredIds)
            ->delete();
    }

    /**
     * @param $customerSettingsId
     *
     * @return mixed
     */
    public function getLaborLinkFilesByCustomerSettingsId($customerSettingsId)
    {
        return $this->model
        ->where('customer_settings_id', '=', $customerSettingsId)
        ->get();
    }

    /**
     * @param $companyPersonId
     *
     * @return mixed
     */
    public function getLaborLinkFilesByCompanyPersonId($companyPersonId)
    {
        return $this->model
            ->join('customer_settings', function ($join) use ($companyPersonId) {
                $join
                    ->on('settings.customer_settings_id', '=', 'link_labor_file_required.customer_settings_id')
                    ->on('settings.company_person_id', '=', DB::raw($companyPersonId));
            })
            ->get();
    }

    public function getInventoryIdsByWorkOrderIds($workOrderIds)
    {
        $workOrderRepository = app(WorkOrderRepository::class);

        $groupedByCustomerSettings = [];
        $groupedByWorkOrder = [];

        $mappingWorkOrderIdsByCustomerSettingsIds = $workOrderRepository->getCustomerSettingsIdsByWorkOrderIds($workOrderIds);
        if ($mappingWorkOrderIdsByCustomerSettingsIds) {
            $inventory = $this->model
                ->select([
                    'inventory_id',
                    'customer_settings_id'
                ])
                ->whereIn('customer_settings_id', array_values($mappingWorkOrderIdsByCustomerSettingsIds))
                ->get();

            foreach ($inventory as $item) {
                if (!isset($groupedByCustomerSettings[$item->customer_settings_id])) {
                    $groupedByCustomerSettings[$item->customer_settings_id] = [];
                }

                $groupedByCustomerSettings[$item->customer_settings_id][] = $item->inventory_id;
            }
        }

        foreach ($mappingWorkOrderIdsByCustomerSettingsIds as $workOrderId => $customerSettingsId) {
            $groupedByWorkOrder[$workOrderId] = isset($groupedByCustomerSettings[$customerSettingsId])
                ? $groupedByCustomerSettings[$customerSettingsId]
                : [];
        }

        return $groupedByWorkOrder;
    }
}
