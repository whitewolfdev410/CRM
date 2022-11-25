<?php

namespace App\Modules\CustomerSettings\Services;

use App\Modules\Asset\Models\AssetLinkFileRequired;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsItemsRepository;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsOptionsRepository;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsRepository;
use App\Modules\File\Models\LinkFileRequired;
use App\Modules\History\Repositories\HistoryRepository;
use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\WorkOrder\Models\LinkLaborFileRequired;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerSettingsHistoryService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var CustomerSettingsItemsRepository
     */
    protected $customerSettingsItemsRepository;

    /**
     * @var CustomerSettingsOptionsRepository
     */
    protected $customerSettingsOptionsRepository;

    /**
     * @var CustomerSettingsRepository
     */
    protected $customerSettingsRepository;

    /**
     * @var CustomerSettingsService
     */
    protected $customerSettingsService;

    /**
     * @var HistoryRepository
     */
    protected $historyRepository;

    /**
     * @var TypeRepository
     */
    protected $typeRepository;
    
    /**
     * CustomerSettingsService constructor.
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;

        $this->customerSettingsItemsRepository = $this->app[CustomerSettingsItemsRepository::class];
        $this->customerSettingsOptionsRepository = $this->app[CustomerSettingsOptionsRepository::class];
        $this->customerSettingsRepository = $this->app[CustomerSettingsRepository::class];
        $this->customerSettingsService = $this->app[CustomerSettingsService::class];
        $this->historyRepository = $this->app[HistoryRepository::class];
        $this->typeRepository = $this->app[TypeRepository::class];
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function getHistory(Request $request)
    {
        $tableName = $request->get('table_name');
        $personId = $request->get('person_id');

        if ($tableName && $personId) {
            $customerSettingsIds = $this->customerSettingsRepository->getIds($personId);

            switch ($tableName) {
                case 'asset_link_file_required':
                    $paginate = $this->getHistoryForAssetLinkFileRequired($request, $customerSettingsIds);

                    break;
                case 'customer_settings':
                    $paginate = $this->getHistoryForCustomerSettings($request, $customerSettingsIds);

                    break;
                case 'customer_settings_items':
                    $paginate = $this->getHistoryForCustomerSettingsItems($request, $customerSettingsIds);

                    break;
                case 'link_file_required':
                    $paginate = $this->getHistoryForLinkFileRequired($request, $customerSettingsIds);

                    break;
                case 'link_labor_file_required':
                    $paginate = $this->getHistoryForLinkLaborFileRequired($request, $customerSettingsIds);

                    break;
            }

            if (isset($paginate)) {
                return $paginate;
            }
        }

        return [];
    }

    private function getHistoryForAssetLinkFileRequired(Request $request, array $customerSettingsIds)
    {
        $linkFileRequired = AssetLinkFileRequired::whereIn('customer_settings_id', $customerSettingsIds)
            ->get();

        $itemIds = [];
        foreach ($linkFileRequired as $item) {
            $itemIds[$item->asset_link_file_required_id] = [
                'file_type_id' => $item->file_type_id,
                'wo_type_id' => $item->wo_type_id,
                'asset_system_type_id' => $item->asset_system_type_id
            ];
        }
        
        $fileTypes = $this->typeRepository->getList('asset_pictures');
        $woTypes = $this->typeRepository->getList('wo_type');
        $assetSystemTypes = $this->typeRepository->getList('asset_system_types');
        
        $paginate = $this->mergeRequestAndPaginate($request, 'asset_link_file_required', array_keys($itemIds));
        foreach ($paginate['data'] as $key => $values) {
            $fileTypeId = $itemIds[$values->record_id]['file_type_id'];
            $woTypeId = $itemIds[$values->record_id]['wo_type_id'];
            $assetSystemTypeId = $itemIds[$values->record_id]['asset_system_type_id'];

            $label = '';
            
            if ($woTypeId && isset($woTypes[$woTypeId])) {
                $label .= $woTypes[$woTypeId] . ' -> ';
            }

            if ($fileTypeId && isset($fileTypes[$fileTypeId])) {
                $label .= $fileTypes[$fileTypeId] . ' -> ';
            }

            if ($assetSystemTypeId && isset($assetSystemTypes[$assetSystemTypeId])) {
                $label .= $assetSystemTypes[$assetSystemTypeId] . ' -> ';
            }
            
            $label .= $values->columnname;
            
            $paginate['data'][$key]->label = $label;
        }

        return $paginate;
    }

    private function getHistoryForCustomerSettings(Request $request, array $customerSettingsIds)
    {
        $labels = array_map(function ($item) {
            return $item['label'];
        }, $this->customerSettingsService->getBasicSettingsFields());

        $paginate = $this->mergeRequestAndPaginate($request, 'customer_settings', $customerSettingsIds);
        foreach ($paginate['data'] as $key => $values) {
            if ($values->columnname === 'meta_data') {
                unset($paginate['data'][$key]);
                
                continue;
            }

            if (isset($labels[$values->columnname])) {
                $paginate['data'][$key]->label = $labels[$values->columnname];
            } else {
                $paginate['data'][$key]->label = $values->columnname;
            }
        }

        $paginate['data'] = array_values($paginate['data']);
        
        return $paginate;
    }

    private function getHistoryForCustomerSettingsItems(Request $request, array $customerSettingsIds)
    {
        $itemIds = $this->customerSettingsItemsRepository->getKeyWithIdsByCustomerSettingIds($customerSettingsIds);
        $labels = $this->customerSettingsOptionsRepository->getLabels();

        $paginate = $this->mergeRequestAndPaginate($request, 'customer_settings_items', array_keys($itemIds));
        foreach ($paginate['data'] as $key => $values) {
            $labelKey = $itemIds[$values->record_id];

            if (isset($labels[$labelKey])) {
                $paginate['data'][$key]->label = $labels[$labelKey];
            } else {
                $paginate['data'][$key]->label = $values->columnname;
            }
        }

        return $paginate;
    }

    private function getHistoryForLinkFileRequired(Request $request, array $customerSettingsIds)
    {
        $itemIds = LinkFileRequired::whereIn('customer_settings_id', $customerSettingsIds)
            ->where('type', DB::raw("'work_order'"))
            ->limit(1000)
            ->pluck('file_type_id', 'link_file_required_id')
            ->all();

        $types = $this->typeRepository->getList('wo_pictures');
            
        $paginate = $this->mergeRequestAndPaginate($request, 'link_file_required', array_keys($itemIds));
        foreach ($paginate['data'] as $key => $values) {
            $typeId = $itemIds[$values->record_id];

            if (isset($types[$typeId])) {
                $paginate['data'][$key]->label = $types[$typeId] . ' -> ' . $values->columnname;
            } else {
                $paginate['data'][$key]->label = $values->columnname;
            }
        }

        return $paginate;
    }

    private function getHistoryForLinkLaborFileRequired(Request $request, array $customerSettingsIds)
    {
        $itemIds = LinkLaborFileRequired::whereIn('customer_settings_id', $customerSettingsIds)
            ->limit(1000)
            ->pluck('link_labor_file_required_id')
            ->all();

        $paginate = $this->mergeRequestAndPaginate($request, 'link_labor_file_required', $itemIds);
        foreach ($paginate['data'] as $key => $values) {
            $paginate['data'][$key]->label = $values->columnname;
        }

        return $paginate;
    }
    
    private function mergeRequestAndPaginate(Request $request, string $tableName, array $ids)
    {
        $request->merge([
            'tablename' => $tableName,
            'record_id' => implode(',', $ids),
            'multiple'  => true
        ]);

        $paginate = $this->historyRepository->paginate();
        $paginate = $paginate->toArray();

        return $paginate;
    }
}
