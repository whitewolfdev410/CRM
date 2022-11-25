<?php

namespace App\Modules\WorkOrder\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\NoPermissionException;
use App\Http\Controllers\Controller;
use App\Modules\WorkOrder\Http\Requests\WorkOrderTemplateRequest;
use App\Modules\WorkOrder\Repositories\WorkOrderTemplateRepository;
use App\Modules\WorkOrder\Services\WorkOrderTemplateService;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

/**
 * Class WorkOrderExtensionController
 *
 * @package App\Modules\WorkOrderExtension\Http\Controllers
 */
class WorkOrderTemplateController extends Controller
{
    protected $app;
    /**
     * WorkOrderTemplateRepository repository
     *
     * @var WorkOrderTemplateRepository
     */
    private $workOrderTemplateRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param  Container  $app
     * @param  WorkOrderTemplateRepository  $workOrderTemplateRepository
     */
    public function __construct(Container $app, WorkOrderTemplateRepository $workOrderTemplateRepository)
    {
        $this->middleware('auth');

        $this->app = $app;
        $this->workOrderTemplateRepository = $workOrderTemplateRepository;
    }

    /**
     * Return list of WorkOrderTemplate
     *
     * @param  Config  $config
     *
     * @return JSONResponse
     *
     * @throws NoPermissionException
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['workorder-template.index']);

        $onPage = $config->get('system_settings.workorder_template_pagination', 50);

        $list = $this->workOrderTemplateRepository->paginate($onPage);

        return response()->json($list);
    }
    
    /**
     * Return module configuration for store action
     *
     * @param  WorkOrderTemplateService  $workOrderTemplateService
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function create(WorkOrderTemplateService $workOrderTemplateService)
    {
        $this->checkPermissions(['workorder-template.store']);

        $rules = $workOrderTemplateService->getRequestRules('create');

        return response()->json($rules);
    }

    /**
     * Store a newly created WorkOrderTemplate in storage.
     *
     * @param  WorkOrderTemplateRequest  $workOrderTemplateRequest
     * @param  WorkOrderTemplateService  $workOrderTemplateService
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function store(
        WorkOrderTemplateRequest $workOrderTemplateRequest,
        WorkOrderTemplateService $workOrderTemplateService
    ) {
        $this->checkPermissions(['workorder-template.store']);

        $model = $workOrderTemplateService->create($workOrderTemplateRequest->all());

        $result = $workOrderTemplateService->show($model->getId());
        
        return response()->json($result, 201);
    }

    /**
     * Display WorkOrderTemplate and module configuration for update action
     *
     * @param  int  $id
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     * @throws ModelNotFoundException
     */
    public function edit($id)
    {
        $this->checkPermissions(['workorder-template.update']);

        return response()->json($this->workOrderTemplateRepository->show($id, true));
    }

    /**
     * Update WorkOrderTemplate
     *
     * @param  int  $id
     * @param  WorkOrderTemplateRequest  $workOrderTemplateRequest
     * @param  WorkOrderTemplateService  $workOrderTemplateService
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function update(
        WorkOrderTemplateRequest $workOrderTemplateRequest,
        WorkOrderTemplateService $workOrderTemplateService,
        $id
    ) {
        $this->checkPermissions(['workorder-template.update']);

        $workOrderTemplateService->update($id, $workOrderTemplateRequest->all());

        return response()->json([
            'message' => 'Work Order temlpate has been updated',
        ]);
    }

    /**
     * Display the specified WorkOrderTemplate
     *
     * @param  WorkOrderTemplateService  $workOrderTemplateService
     * @param  int  $id
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function show(WorkOrderTemplateService $workOrderTemplateService, $id)
    {
        $this->checkPermissions(['workorder-template.show']);

        return response()->json($workOrderTemplateService->show((int)$id));
    }
    
    public function destroy(WorkOrderTemplateService $workOrderTemplateService, $id)
    {
        $this->checkPermissions(['workorder-template.destroy']);

        $workOrderTemplateService->destroy($id);

        return response()->json([
            'message' => 'Work Order temlpate has been deleted',
        ]);
    }
}
