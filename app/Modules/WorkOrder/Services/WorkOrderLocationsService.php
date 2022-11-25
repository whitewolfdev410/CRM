<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Trans;
use App\Modules\File\Services\FileService;
use App\Modules\Region\Repositories\RegionRepository;
use App\Modules\TimeSheet\Repositories\TimeSheetRepository;
use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;

class WorkOrderLocationsService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var LinkPersonWoRepository
     */
    private $linkPersonWoRepository;
    
    /**
     * @var TimeSheetRepository
     */
    private $tsRepo;

    /**
     * @var WorkOrderRepository
     */
    private $workOrderRepository;

    /**
     * Initialize class
     *
     * @param Container              $app
     * @param LinkPersonWoRepository $linkPersonWoRepository
     * @param TimeSheetRepository    $tsRepo
     * @param WorkOrderRepository    $workOrderRepository
     */
    public function __construct(
        Container $app,
        LinkPersonWoRepository $linkPersonWoRepository,
        TimeSheetRepository $tsRepo,
        WorkOrderRepository $workOrderRepository
    ) {
        $this->app = $app;
        $this->linkPersonWoRepository = $linkPersonWoRepository;
        $this->tsRepo = $tsRepo;
        $this->workOrderRepository = $workOrderRepository;
    }

    /**
     * Get work order locations paginated list
     *
     * @param int $workOrderId
     * @param int $onPage
     *
     * @return LengthAwarePaginator
     */
    public function get($workOrderId, $onPage)
    {
        return $this->tsRepo->getLocations($workOrderId, $onPage);
    }

    /**
     * Get link person wo for given work order and photos
     *
     * @param int $workOrderId
     * @param int $onPage
     * @param int $width
     * @param int $height
     * @param int $forceSize
     * @param int $withDimensions
     *
     * @return LengthAwarePaginator
     */
    public function getPhotos(
        $workOrderId,
        $onPage,
        $width,
        $height,
        $forceSize,
        $withDimensions = 0
    ) {
        /** @var LinkPersonWoRepository $lpWoRepo */
        $lpWoRepo = $this->app->make(LinkPersonWoRepository::class);

        /** @var Trans $trans */
        $trans = $this->app->make(Trans::class);

        /** @var FileService $fileService */
        $fileService = $this->app->make(FileService::class);

        /** @var LengthAwarePaginator $lpWos */
        $lpWos = $lpWoRepo->getForWo($workOrderId, true, true, $onPage);

        foreach ($lpWos->items() as $lpWo) {
            $lpWo->kind = ($lpWo->person_kind == 'person')
                ? $trans->get('person_kind.technician')
                : $trans->get('person_kind.vendor');

            $lpWo->images = $fileService->getPhotos(
                'link_person_wo',
                $lpWo->getId(),
                null,
                $width,
                $height,
                $forceSize,
                $withDimensions
            );
        }

        return $lpWos;
    }

    /**
     * Get data for locations vendors by workOrderId
     *
     * @param $id
     *
     * @return array
     */
    public function getLocationsVendors($id)
    {
        $locations = [
            'city_open_workorders' => [],
            'last_vendors'         => [],
            'open_workorders'      => []
        ];

        /** @var WorkOrder $workOrder */
        $workOrder = $this->workOrderRepository->find($id);

        // get opened work orders in the same location
        $openedWorkOrders = $this->workOrderRepository->getOpenedInLocation(
            $workOrder->getShopAddressId(),
            $workOrder->getId()
        );

        $locations['open_workorders'] = array_values($openedWorkOrders);

        $lastLocationVendors = $this->linkPersonWoRepository->getLastLocationVendors(
            $workOrder->getShopAddressId(),
            $workOrder->getId(),
            10
        );

        $locations['last_vendors'] = $lastLocationVendors;

        // get opened work orders in the same city
        $openedCityWorkOrders = $this->workOrderRepository->getOpenedInCity(
            $workOrder->getShopAddressId(),
            $workOrder->getId()
        );

        $locations['city_open_workorders'] = array_values($openedCityWorkOrders);
        
        return $locations;
    }

    /**
     * Get regions list
     *
     * @return array
     */
    public function getRegions()
    {
        $regionRepository = $this->app->make(RegionRepository::class);
        $regions = [];
        
        $data = $regionRepository->getList();
        natcasesort($data);
        foreach ($data as $id => $name) {
            $regions[] = [
                'label' => trim($name),
                'value' => $id
            ];
        }
        
        return $regions;
    }

    /**
     * Get trades list
     *
     * @return array
     */
    public function getTrades()
    {
        $typeRepository = $this->app->make(TypeRepository::class);
        $trades = [];

        $data = $typeRepository->getList('company_trade');
        natcasesort($data);
        foreach ($data as $id => $name) {
            $trades[] = [
                'label' => trim($name),
                'value' => $id
            ];
        }

        return $trades;
    }
}
