<?php

namespace App\Modules\WorkOrder\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\NoPermissionException;
use App\Core\Exceptions\ObjectNotFoundException;
use App\Http\Controllers\Controller;
use App\Modules\WorkOrder\Http\Requests\WorkOrderExtensionRequest;
use App\Modules\WorkOrder\Repositories\WorkOrderExtensionRepository;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * Class WorkOrderExtensionController
 *
 * @package App\Modules\WorkOrderExtension\Http\Controllers
 */
class WorkOrderExtensionController extends Controller
{
    protected $app;
    /**
     * WorkOrderExtension repository
     *
     * @var WorkOrderExtensionRepository
     */
    private $workOrderExtRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param WorkOrderExtensionRepository $workOrderExtRepository
     */
    public function __construct(
        WorkOrderExtensionRepository $workOrderExtRepository,
        Container $app
    ) {
        $this->middleware('auth');
        $this->workOrderExtRepository = $workOrderExtRepository;
        $this->app = $app;
    }

    /**
     * Return list of WorkOrderExtension
     *
     * @param Config $config
     *
     * @return JSONResponse
     *
     * @throws NoPermissionException
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['workorder-extension.index']);
        /*        $onPage
                    = $config->get('system_settings.workorder_extension_pagination');
                $list = $this->workOrderExtRepository->paginate($onPage);

                return response()->json($list);*/
    }
    /**
     * Return list of Extensions for WO
    */
    public function showWOExtensions($id, Request $request)
    {
        $this->checkPermissions(['workorder-extension.show']);
        
        
        $limit = $request->input('limit', 50);
        
        $list = DB::table('work_order_extension')
            ->leftJoin('person', 'work_order_extension.person_id', '=', 'person.person_id')
            ->where('work_order_id', $id)
            ->selectRaw('work_order_extension.*, concat(person.custom_1, " ", person.custom_3) as person_name')
            ->get();
        return response()->json([
            'fields' => $this->workOrderExtRepository->getRequestRules(),
            'items' => $list
        ]);
    }

    /**
     * Display the specified WorkOrderExtension
     *
     * @param  int $id
     *
     * @return JSONResponse
     *
     * @throws NoPermissionException
     */
    public function show($id)
    {
        $this->checkPermissions(['workorder-extension.show']);
        /*        $id = (int)$id;

                return response()->json($this->workOrderExtRepository->show($id));*/
    }

    /**
     * Return module configuration for store action
     *
     * @return JSONResponse
     *
     * @throws NoPermissionException
     */
    public function create()
    {
        $this->checkPermissions(['workorder-extension.store']);
        /*        $rules['fields'] = $this->workOrderExtRepository->getRequestRules();

                return response()->json($rules);*/
    }

    /**
     * Store a newly created WorkOrderExtension in storage.
     *
     * @param WorkOrderExtensionRequest $request
     *
     * @return JSONResponse
     *
     * @throws NoPermissionException
     */
    public function store(WorkOrderExtensionRequest $request)
    {
        $this->checkPermissions(['workorder-extension.store']);
        list($model, $changed)
            = $this->workOrderExtRepository->create($request->all());

        return response()->json(['item' => $model, 'changed' => $changed], 201);
    }

    /**
     * Display WorkOrderExtension and module configuration for update action
     *
     * @param  int $id
     *
     * @return JSONResponse
     *
     * @throws NoPermissionException
     */
    public function edit($id)
    {
        $this->checkPermissions(['workorder-extension.update']);
        /*   $id = (int)$id;

           return response()->json($this->workOrderExtRepository->show($id, true));*/
    }

    /**
     * Update the specified WorkOrderExtension in storage.
     *
     * @param WorkOrderExtensionRequest $request
     * @param  int                      $id
     *
     * @return JSONResponse
     *
     * @throws NoPermissionException
     */
    public function update(WorkOrderExtensionRequest $request, $id)
    {
        $this->checkPermissions(['workorder-extension.update']);
        /*$id = (int)$id;

        $record = $this->workOrderExtRepository->updateWithIdAndInput($id,
            $request->all());

        return response()->json(['item' => $record]);*/
    }

    /**
     * Remove the specified WorkOrderExtension from storage.
     *
     * @param  int $id
     *
     * @return JSONResponse
     *
     * @throws NoPermissionException
     */
    public function destroy($id)
    {
        $this->checkPermissions(['workorder-extension.destroy']);

        $id = (int) $id;

        if (!$this->workOrderExtRepository->destroy($id)) {
            throw with($this->app->make(ObjectNotFoundException::class));
        }

        return response()->json('Extension has been deleted.');
    }
}
