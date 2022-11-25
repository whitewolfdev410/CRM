<?php

namespace App\Modules\WorkOrder\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\NoPermissionException;
use App\Http\Controllers\Controller;
use App\Modules\WorkOrder\Http\Requests\AcceptLaborsRequest;
use App\Modules\WorkOrder\Http\Requests\LaborPricingRequest;
use App\Modules\WorkOrder\Repositories\LinkLaborWoRepository;
use App\Modules\WorkOrder\Services\LinkLaborWoService;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Class LinkPersonWoController
 *
 * @package App\Modules\LinkPersonWo\Http\Controllers
 */
class LinkLaborWoController extends Controller
{
    /**
     * LinkLaborWoRepository repository
     *
     * @var LinkLaborWoRepository
     */
    private $linkLaborWoRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param LinkLaborWoRepository $linkLaborWoRepository
     */
    public function __construct(LinkLaborWoRepository $linkLaborWoRepository)
    {
        $this->middleware('auth');

        $this->linkLaborWoRepository = $linkLaborWoRepository;
    }

    /**
     * Return list of LinkPersonWo
     *
     * @param LinkLaborWoRepository $linkLaborWoRepository
     * @param Config                $config
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function getLaborsToAccept(LinkLaborWoRepository $linkLaborWoRepository, Config $config)
    {
        $this->checkPermissions(['workorder.labors-to-accept']);

        $onPage = $config->get('system_settings.workorder_labors_pagination', 50);
        $list = $linkLaborWoRepository->toAcceptPaginate($onPage);

        return response()->json($list);
    }

    /**
     * Get labors
     *
     * @param LinkLaborWoRepository $linkLaborWoRepository
     * @param Config                $config
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getLabors(LinkLaborWoRepository $linkLaborWoRepository, Config $config)
    {
        $this->checkPermissions(['workorder.labors']);

        $onPage = $config->get('system_settings.workorder_labors_pagination', 50);
        $list = $linkLaborWoRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Accept labors
     *
     * @param AcceptLaborsRequest   $acceptLaborsRequest
     * @param LinkLaborWoRepository $linkLaborWoRepository
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function accept(AcceptLaborsRequest $acceptLaborsRequest, LinkLaborWoRepository $linkLaborWoRepository)
    {
        $this->checkPermissions(['workorder.labors-accept']);

        $result = $linkLaborWoRepository->acceptLabors($acceptLaborsRequest);

        return response()->json($result);
    }

    /**
     * Get pricing by work order id
     *
     * @param LaborPricingRequest $laborPricingRequest
     * @param LinkLaborWoService  $linkLaborWoService
     *
     * @return JsonResponse
     */
    public function getPricing(LaborPricingRequest $laborPricingRequest, LinkLaborWoService $linkLaborWoService)
    {
        $result = $linkLaborWoService->getPricing($laborPricingRequest);

        return response()->json($result);
    }
}
