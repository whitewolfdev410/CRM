<?php

namespace App\Modules\WorkOrder\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\NoPermissionException;
use App\Http\Controllers\Controller;
use App\Modules\WorkOrder\Exceptions\LpWoCannotCompleteNoCompletionCodeException;
use App\Modules\WorkOrder\Exceptions\LpWoChangeStatusInvalidStatusException;
use App\Modules\WorkOrder\Exceptions\LpWoMissingEcdException;
use App\Modules\WorkOrder\Exceptions\LpWoMissingWorkOrderException;
use App\Modules\WorkOrder\Exceptions\LpWoNotAssignedException;
use App\Modules\WorkOrder\Http\Requests\LinkPersonWoBulkCompleteRequest;
use App\Modules\WorkOrder\Http\Requests\LinkPersonWoCompleteRequest;
use App\Modules\WorkOrder\Http\Requests\LinkPersonWoConfirmRequest;
use App\Modules\WorkOrder\Http\Requests\LinkPersonWoJobDescriptionRequest;
use App\Modules\WorkOrder\Http\Requests\LinkPersonWoRequest;
use App\Modules\WorkOrder\Http\Requests\LinkPersonWoStatsResolveRequest;
use App\Modules\WorkOrder\Http\Requests\LinkPersonWoStatusChangeRequest;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\LinkPersonWoStatsRepository;
use App\Modules\WorkOrder\Services\LinkPersonWoAlertCounterService;
use App\Modules\WorkOrder\Services\LinkPersonWoCompleteService;
use App\Modules\WorkOrder\Services\LinkPersonWoConfirmService;
use App\Modules\WorkOrder\Services\LinkPersonWoJobDescriptionService;
use App\Modules\WorkOrder\Services\LinkPersonWoService;
use App\Modules\WorkOrder\Services\LinkPersonWoStatusService;
use App\Modules\WorkOrder\Services\WorkOrderMobileService;
use Exception;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Log;

/**
 * Class LinkPersonWoController
 *
 * @package App\Modules\LinkPersonWo\Http\Controllers
 */
class LinkPersonWoController extends Controller
{
    /**
     * LinkPersonWo repository
     *
     * @var LinkPersonWoRepository
     */
    private $linkPersonWoRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param LinkPersonWoRepository $linkPersonWoRepository
     */
    public function __construct(LinkPersonWoRepository $linkPersonWoRepository)
    {
        $this->middleware('auth');

        $this->linkPersonWoRepository = $linkPersonWoRepository;
    }

    /**
     * Return list of LinkPersonWo
     *
     * @param Config $config
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['link-person-wo.index']);

        $onPage = $config->get('system_settings.link_person_wo_pagination', 50);
        $list = $this->linkPersonWoRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Display the specified LinkPersonWo
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function show($id)
    {
        $this->checkPermissions(['link-person-wo.show']);

        $id = (int)$id;

        return response()->json($this->linkPersonWoRepository->show($id));
    }

    /**
     * Display Work Order which is associated with given LpWo id
     *
     * @param int                    $id
     * @param WorkOrderMobileService $service
     * @param Request                $request
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function mobileShow(
        $id,
        WorkOrderMobileService $service,
        Request $request
    ) {
        $this->checkPermissions(['link-person-wo.mobile-show']);

        $id = (int)$id;

        return response()->json($service->getMobileItem(
            $id,
            'link_person_wo',
            $request->query('user_timezone', '')
        ));
    }

    /**
     * Return module configuration for store action
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function create()
    {
        $this->checkPermissions(['link-person-wo.store']);

        $rules['fields'] = $this->linkPersonWoRepository->getRequestRules();

        return response()->json($rules);
    }

    /**
     * Store a newly created LinkPersonWo in storage.
     *
     * @param LinkPersonWoRequest $request
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function store(LinkPersonWoRequest $request)
    {
        $this->checkPermissions(['link-person-wo.store']);

        $model = $this->linkPersonWoRepository->create($request->all());

        return response()->json(['item' => $model], 201);
    }

    /**
     * Display LinkPersonWo and module configuration for update action
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function edit($id)
    {
        $this->checkPermissions(['link-person-wo.update']);

        $id = (int)$id;

        return response()->json($this->linkPersonWoRepository->show($id, true));
    }

    /**
     * Update the specified LinkPersonWo in storage.
     *
     * @param LinkPersonWoRequest $request
     * @param  int                $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function update(LinkPersonWoRequest $request, $id)
    {
        $this->checkPermissions(['link-person-wo.update']);
        $id = (int)$id;

        $record = $this->linkPersonWoRepository
            ->updateWithIdAndInput($id, $request->all());

        return response()->json(['item' => $record]);
    }

    /**
     * Remove the specified LinkPersonWo from storage.
     *
     * @param2 int $id
     * @param  LinkPersonWoService  $linkPersonWoService
     * @param $id
     *
     * @return JsonResponse
     * @throws App\Modules\WorkOrder\Exceptions\LpWoAssignedTimeSheetsException
     * @throws NoPermissionException
     */
    public function destroy(LinkPersonWoService $linkPersonWoService, $id)
    {
        $this->checkPermissions(['link-person-wo.destroy']);

        if (config('app.crm_user') === 'fs') {
            $status = $linkPersonWoService->remove($id);

            return response()->json(['status' => $status]);
        } else {
            abort(404);
            exit;
        }
    }

    /**
     * Get alerts information
     *
     * @param LinkPersonWoAlertCounterService $alertService
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function countAlerts(LinkPersonWoAlertCounterService $alertService)
    {
        $this->checkPermissions(['link-person-wo.count-alerts']);

        $data = $alertService->get();

        return response()->json(['item' => $data]);
    }

    /**
     * Confirm work order (actually link person wo)
     *
     * @param LinkPersonWoConfirmRequest $request
     * @param LinkPersonWoConfirmService $service
     * @param int                        $id
     *
     * @return JsonResponse
     *
     * @throws LpWoNotAssignedException
     * @throws NoPermissionException
     */
    public function confirmWorkOrder(
        LinkPersonWoConfirmRequest $request,
        LinkPersonWoConfirmService $service,
        $id
    ) {
        $this->checkPermissions(['link-person-wo.confirm-wo']);

        $record = $service->confirm($id, $request->input('via'));

        return response()->json(['item' => $record]);
    }

    /**
     * Complete work order (actually link person wo)
     *
     * @param LinkPersonWoCompleteRequest $request
     * @param LinkPersonWoCompleteService $service
     * @param int                         $id
     *
     * @return JsonResponse
     *
     * @throws Exception
     * @throws LpWoCannotCompleteNoCompletionCodeException
     * @throws LpWoNotAssignedException
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function completeWorkOrder(
        LinkPersonWoCompleteRequest $request,
        LinkPersonWoCompleteService $service,
        $id
    ) {
        $this->checkPermissions(['link-person-wo.complete-wo']);

        $record = $service->complete(
            $id,
            $request->input('via'),
            $request->input('completion_code'),
            $request->input('is_mobile', 0)
        );

        return response()->json(['item' => $record]);
    }

    /**
     * @param  LinkPersonWoBulkCompleteRequest  $linkPersonWoBulkCompleteRequest
     * @param  LinkPersonWoCompleteService  $linkPersonWoCompleteService
     *
     * @throws NoPermissionException
     */
    public function bulkComplete(
        LinkPersonWoBulkCompleteRequest $linkPersonWoBulkCompleteRequest,
        LinkPersonWoCompleteService $linkPersonWoCompleteService
    ) {
        $this->checkPermissions(['link-person-wo.bulk-complete-wo']);

        $result = $linkPersonWoCompleteService->bulkComplete($linkPersonWoBulkCompleteRequest);

        return response()->json($result);
    }
    
    /**
     * Change status of given link person wo
     *
     * @param LinkPersonWoStatusChangeRequest $request
     * @param LinkPersonWoStatusService       $service
     * @param int                             $id
     *
     * @return JsonResponse
     *
     * @throws LpWoChangeStatusInvalidStatusException
     * @throws NoPermissionException
     */
    public function changeStatus(
        LinkPersonWoStatusChangeRequest $request,
        LinkPersonWoStatusService $service,
        $id
    ) {
        $this->checkPermissions(['link-person-wo.status']);

        $statusLabel = $request->input('status_label');
        if ($request->input('status_id') && !$request->input('status_label')) {
            $statusLabel = strtolower(getTypeValueById($request->input('status_id')));
        }
        
        list($record, $changes) = $service->change(
            $id,
            $statusLabel,
            $request->input('additional_information'),
            $request->input('reason_type_id', null),
            $request->input('no_invoice_certify', null),
            $request->input('assign_to_person_id', null),
            $request->input('job_type', null),
            $request->input('recall_link_person_wo_id', null),
            $request->input('completion_code', null),
            $request->input('force', 0)
        );

        return response()->json(['item' => $record, 'changes' => $changes]);
    }

    /**
     * Get Link person wo job description for update job description action
     *
     * @param int                               $id
     * @param LinkPersonWoJobDescriptionService $service
     *
     * @return JsonResponse
     *
     * @throws LpWoMissingEcdException
     * @throws LpWoMissingWorkOrderException
     * @throws NoPermissionException
     */
    public function getJobDescription(
        $id,
        LinkPersonWoJobDescriptionService $service
    ) {
        $this->checkPermissions(['link-person-wo.get-job-description']);

        $data = $service->get($id);

        return response()->json($data);
    }

    /**
     * Get request rules for update job description action
     *
     * @param  LinkPersonWoJobDescriptionService  $service
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function createJobDescription(LinkPersonWoJobDescriptionService $service)
    {
        $this->checkPermissions(['link-person-wo.get-job-description']);

        $data = $service->getRequestRules();

        return response()->json($data);
    }
    
    /**
     * Save job description for link person wo
     *
     * @param LinkPersonWoJobDescriptionRequest $request
     * @param int                               $id
     * @param LinkPersonWoJobDescriptionService $service
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function saveJobDescription(
        LinkPersonWoJobDescriptionRequest $request,
        $id,
        LinkPersonWoJobDescriptionService $service
    ) {
        $this->checkPermissions(['link-person-wo.get-job-description']);

        list($item, $changes) = $service->save($id, $request->all());

        return response()->json(['item' => $item, 'changes' => $changes]);
    }

    /**
     * Get list of work orders with violations resolved
     *
     * @param LinkPersonWoStatsRepository $linkPersonWoStatsRepository
     *
     * @return array
     *
     * @throws InvalidArgumentException
     * @throws NoPermissionException
     */
    public function getResolvedWO(LinkPersonWoStatsRepository $linkPersonWoStatsRepository)
    {
        $this->checkPermissions(['workorder.unresolved-index']);

        $data = $linkPersonWoStatsRepository->getResolvedWO();

        return response()->json($data);
    }

    /**
     * Get list of work orders with violations
     *
     * @param LinkPersonWoStatsRepository $linkPersonWoStatsRepository
     *
     * @return array
     *
     * @throws InvalidArgumentException
     * @throws NoPermissionException
     */
    public function getUnresolvedWO(LinkPersonWoStatsRepository $linkPersonWoStatsRepository)
    {
        $this->checkPermissions(['workorder.unresolved-index']);

        $data = $linkPersonWoStatsRepository->getUnresolvedWO();

        return response()->json($data);
    }

    /**
     * Resolve
     *
     * @param LinkPersonWoStatsResolveRequest $request
     * @param LinkPersonWoStatsRepository     $linkPersonWoStatsRepository
     * @param                                 $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function resolveWorkOrder(
        LinkPersonWoStatsResolveRequest $request,
        LinkPersonWoStatsRepository $linkPersonWoStatsRepository,
        $id
    ) {
        $this->checkPermissions(['workorder.unresolved-update']);

        $resolutionType = $request->get('resolution_type_id');
        $resolutionMemo = $request->get('resolution_memo');
        try {
            $linkPersonWoStatsRepository->resolve((int)$id, $resolutionType, $resolutionMemo);
        } catch (\Exception $e) { // I don't remember what exception it is specifically
            Log::error('Cannot update Exception', ['exception' => $e]);
            return response()->json([
                'success' => false,
            ]);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    public function changeOrder($id, $direction)
    {
        $response = $this->linkPersonWoRepository->changePriority($direction, $id);

        return response()->json([
            'success' => $response
        ]);
    }

    public function getTechGrid(Request $request)
    {
        $response = $this->linkPersonWoRepository->techGrid($request->all());

        return response()->json(["data" => $response]);
    }
}
