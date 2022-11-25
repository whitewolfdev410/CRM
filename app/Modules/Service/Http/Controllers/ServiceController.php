<?php

namespace App\Modules\Service\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Modules\Service\Repositories\ServiceRepository;
use App\Modules\Service\Services\IndexServiceService;
use Illuminate\Config\Repository as Config;
use App\Modules\Service\Http\Requests\ServiceRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class ServiceController
 *
 * @package App\Modules\Service\Http\Controllers
 */
class ServiceController extends Controller
{
    /**
     * Service repository
     *
     * @var ServiceRepository
     */
    private $serviceRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param ServiceRepository $serviceRepository
     */
    public function __construct(ServiceRepository $serviceRepository)
    {
        $this->middleware('auth');
        $this->serviceRepository = $serviceRepository;
    }

    /**
     * Return list of Service
     *
     * @param Config $config
     * @param IndexServiceService $service
     * @param Request $request
     *
     * @return Response
     */
    public function index(
        Config $config,
        IndexServiceService $service,
        Request $request
    ) {
        $this->checkPermissions(['service.index']);
        $onPage = $config->get('system_settings.service_pagination');
        $list = $service->get($onPage, $request);
        
        //$this->serviceRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Display the specified Service
     *
     * @param  int $id
     *
     * @return Response
     */
    public function show($id)
    {
        $this->checkPermissions(['service.show']);
        $id = (int)$id;

        return response()->json($this->serviceRepository->show($id));
    }

    /**
     * Return module configuration for store action
     *
     * @return Response
     */
    public function create()
    {
        $this->checkPermissions(['service.store']);
        $data['fields'] = $this->serviceRepository->getConfig();
        $data['pricing_structure']
            = $this->serviceRepository->getPricingStructureWithMatrix();

        return response()->json($data);
    }


    /**
     * Store a newly created Service in storage.
     *
     * @param ServiceRequest $request
     *
     * @return Response
     */
    public function store(ServiceRequest $request)
    {
        $this->checkPermissions(['service.store']);
        list($model, ) = $this->serviceRepository->create($request->all());
        $data['item'] = $model;
        $data['pricing_structure']
            =
            $this->serviceRepository->getPricingStructureWithMatrix($model->id);

        return response()->json($data, 201);
    }

    /**
     * Display Service and module configuration for update action
     *
     * @param  int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        $this->checkPermissions(['service.update']);
        $id = (int)$id;

        return response()->json($this->serviceRepository->show($id, true));
    }

    /**
     * Update the specified Service in storage.
     *
     * @param ServiceRequest $request
     * @param  int $id
     *
     * @return Response
     */
    public function update(ServiceRequest $request, $id)
    {
        $this->checkPermissions(['service.update']);
        $id = (int)$id;

        list($model, )
            = $this->serviceRepository->updateWithIdAndInput(
                $id,
                $request->all()
            );

        $data['item'] = $model;
        $data['pricing_structure']
            =
            $this->serviceRepository->getPricingStructureWithMatrix($model->id);

        return response()->json($data);
    }

    /**
     * Remove the specified Service from storage.
     *
     * @param  int $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        $this->checkPermissions(['service.destroy']);
        abort(404);
        exit;

        /* $id = (int) $id;
        $this->serviceRepository->destroy($id); */
    }

    /**
     * @param ServiceRepository $serviceRepository
     * @param                   $state
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCities(ServiceRepository $serviceRepository, $state)
    {
        $cities = $this->serviceRepository->getCities($state);

        return response()->json($cities);
    }
}
