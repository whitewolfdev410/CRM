<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Trans;
use App\Modules\File\Services\FileService;
use App\Modules\TimeSheet\Repositories\TimeSheetRepository;
use App\Modules\WorkOrder\Repositories\LinkAssetWoRepository;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;

class WorkOrderAssetsService
{
    /**
     * @var Container
     */
    protected $app;
    /**
     * @var LinkAssetWoRepository
     */
    private $linkAssetWoRepository;

    /**
     * Initialize class
     *
     * @param Container $app
     * @param LinkAssetWoRepository $linkAssetWoRepository
     */
    public function __construct(
        Container $app,
        LinkAssetWoRepository $linkAssetWoRepository
    ) {
        $this->app = $app;
        $this->linkAssetWoRepository = $linkAssetWoRepository;
    }

    /**
     * Get work order locations paginated list
     *
     * @param int $onPage
     * @return LengthAwarePaginator
     */
    public function get($onPage)
    {
        return $this->linkAssetWoRepository->paginate($onPage);
    }
}
