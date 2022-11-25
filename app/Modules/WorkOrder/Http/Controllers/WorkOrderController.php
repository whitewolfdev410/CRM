<?php

namespace App\Modules\WorkOrder\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\NoPermissionException;
use App\Core\Exceptions\NoSomePermissionException;
use App\Core\InputFormatter;
use App\Http\Controllers\Controller;
use App\Modules\Address\Repositories\StateRepository;
use App\Modules\ExternalServices\Exceptions\NotImplementedException;
use App\Modules\ExternalServices\Exceptions\ServiceNotSupportedException;
use App\Modules\Invoice\Repositories\InvoiceRepository;
use App\Modules\Monitor\Repositories\LinkContactMonitorRepository;
use App\Modules\Person\Repositories\PersonNoteRepository;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\WorkOrder\Http\Requests\TechnicianSummaryRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderAssignVendorRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderAssignVendorsRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderBasicUpdateRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderCancelRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderMobileStoreRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderNoteUpdateRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderStoreRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderUpdateRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderVendorsToAssignRequest;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use App\Modules\WorkOrder\Services\WorkOrderAddVendorsService;
use App\Modules\WorkOrder\Services\WorkOrderAssetsService;
use App\Modules\WorkOrder\Services\WorkOrderCancelService;
use App\Modules\WorkOrder\Services\WorkOrderClientService;
use App\Modules\WorkOrder\Services\WorkOrderDataService;
use App\Modules\WorkOrder\Services\WorkOrderLocationsService;
use App\Modules\WorkOrder\Services\WorkOrderMobileService;
use App\Modules\WorkOrder\Services\WorkOrderSearchVendorsService;
use App\Modules\WorkOrder\Services\WorkOrderService;
use App\Modules\WorkOrder\Services\WorkOrderVendorsAssignValidatorService;
use App\Modules\WorkOrder\Services\WorkOrderVendorsService;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Class WorkOrderController
 *
 * @package App\Modules\WorkOrder\Http\Controllers
 */
class WorkOrderController extends Controller
{
    /**
     * WorkOrder repository
     *
     * @var WorkOrderRepository
     */
    private $workOrderRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param WorkOrderRepository $workOrderRepository
     */
    public function __construct(WorkOrderRepository $workOrderRepository)
    {
        $this->middleware('auth');
        $this->workOrderRepository = $workOrderRepository;
    }

    /**
     * Return list of WorkOrder
     *
     * @param Config $config
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['workorder.index']);

        $onPage = $config->get('system_settings.workorder_pagination');
        $list = $this->workOrderRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Display the specified WorkOrder
     *
     * @param  WorkOrderService  $workOrderService
     * @param  int  $id
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function show(WorkOrderService $workOrderService, $id)
    {
        $this->checkPermissions(['workorder.show']);

        $item = $this->workOrderRepository->show($id);
        $item['allow_fields'] = $workOrderService->getAvailableFieldsForWorkOrder($id);
        
        return response()->json($item);
    }

    /**
     * Display the specified WorkOrder for mobile devices
     *
     * @param int                    $id
     * @param WorkOrderMobileService $service
     * @param Request                $request
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function mobileShow(
        $id,
        WorkOrderMobileService $service,
        Request $request
    ) {
        $this->checkPermissions(['workorder.mobile-show']);

        $id = (int)$id;

        return response()->json($service->getMobileItem(
            $id,
            'work_order',
            $request->query('user_timezone', '')
        ));
    }

    /**
     * Return module configuration for store action
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function create()
    {
        $this->checkPermissions(['workorder.store']);

        $rules = $this->workOrderRepository->getConfig('create');

        return response()->json($rules);
    }

    /**
     * Store a newly created WorkOrder in storage.
     *
     * @param WorkOrderStoreRequest $request
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function store(WorkOrderStoreRequest $request, PersonNoteRepository $personNoteRepository)
    {
        $this->checkPermissions(['workorder.store']);

        $input = $request->all();
        
        if (isCrmUser('fs')) {
            $input['alert_notes'] = $personNoteRepository->isNoteAlert($input['company_person_id']);
        }
        
        list($model, $pickedUp, $vendors) = $this->workOrderRepository->create($input);

        $data['item'] = $model;
        $data['picked_up'] = $pickedUp;
        $data['vendors'] = $vendors;

        return response()->json($data, 201);
    }

    /**
     * Create work order by mobile app
     * @param  WorkOrderMobileStoreRequest  $workOrderMobileStoreRequest
     * @param  WorkOrderService  $workOrderService
     *
     * @return JsonResponse
     */
    public function mobileStore(
        WorkOrderMobileStoreRequest $workOrderMobileStoreRequest,
        WorkOrderService $workOrderService
    ) {
        $model = $workOrderService->createWorkOrderByMobile($workOrderMobileStoreRequest);

        return response()->json(['item' => $model], 201);
    }
    
    /**
     * Display WorkOrder and module configuration for update action
     *
     * @param int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function edit($id)
    {
        $this->checkPermissions(['workorder.update']);

        $id = (int)$id;
        if (config('app.crm_user') == 'bfc') {
            return response()->json($this->workOrderRepository->showBFC($id, true));
        } else {
            return response()->json($this->workOrderRepository->show($id, true));
        }
    }

    /**
     * Update BFC work order
     * @param  int  $id
     * @param  Request $request
     * @return JsonResponse
     */
    public function updateBfc($id, Request $request)
    {
        $this->checkPermissions(['workorder.update']);

        $id = (int)$id;

        $this->workOrderRepository->updateWithIdAndInputBFC($id, $request->all());

        return response()->json([
            'message' => 'Work Order has been updated',
        ]);
    }

    /**
     * Send note to external customer's system
     * @param  int  $id
     * @param  Request $request
     * @return JsonResponse
     */
    public function sendExternalNoteBfc($id, Request $request)
    {
        $notes = $this->workOrderRepository->sendExternalNoteBfc($id, $request['note']);

        return response()->json([
            'message' => 'Note has been sent to the customer',
            'external_notes' => $notes,
        ]);
    }

    /**
     * Send file to external customer's system
     * @param  int  $id
     * @param  Request $request
     * @return JsonResponse
     */
    public function sendExternalFileBfc($id, Request $request)
    {
        $this->workOrderRepository->sendExternalFileBfc($id, $request['file_id']);

        return response()->json([
            'message' => 'File has been sent to the customer',
        ]);
    }

    /**
     * Display WorkOrder and module configuration for basic update action
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function basicEdit($id)
    {
        $this->checkPermissions(['workorder.basic-update']);

        $id = (int)$id;

        return response()->json($this->workOrderRepository->basicEdit($id));
    }

    /**
     * Update the specified WorkOrder in storage.
     *
     * @param WorkOrderUpdateRequest $request
     * @param  int                   $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\LockedMismatchException
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update(WorkOrderUpdateRequest $request, $id)
    {
        $this->checkPermissions(['workorder.update']);

        $id = (int)$id;
        if (config('app.crm_user') == 'bfc') {
            list($record, $messages) = $this->workOrderRepository
                ->updateWithIdAndInputBFC($id, $request->all());
        } else {
            list($record, $messages) = $this->workOrderRepository
                ->updateWithIdAndInput($id, $request->all());
        }

        if(isCrmUser('fs')) {
            /** @var InvoiceRepository $invoiceRepository */
            $invoiceRepository = app(InvoiceRepository::class);
            $invoiceRepository->updateDescriptionByWorkOrderId($id, $record->description);
        }
        
        return response()->json([
            'item'     => $record,
            'messages' => $messages,
        ]);
    }

    /**
     * Basic Update the specified WorkOrder in storage.
     *
     * @param WorkOrderBasicUpdateRequest $request
     * @param int                         $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\LockedMismatchException
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function basicUpdate(WorkOrderBasicUpdateRequest $request, $id)
    {
        $this->checkPermissions(['workorder.basic-update']);
        $id = (int)$id;

        $record = $this->workOrderRepository
            ->basicUpdateWithIdAndInput($id, $request->all());

        return response()->json(['item' => $record]);
    }

    /**
     * Fields Update the specified WorkOrder in storage.
     *
     * @param Request $request
     * @param int     $id
     *
     * @return JsonResponse
     *
     * @throws App\Core\Exceptions\LockedMismatchException
     * @throws NoPermissionException
     */
    public function fieldsUpdate(Request $request, $id)
    {
        $this->checkPermissions(['workorder.update']);

        $record = $this->workOrderRepository->updateWithIdAndInput(
            (int)$id,
            $request->all(),
            'edit',
            true
        );

        return response()->json(['item' => $record]);
    }

    /**
     * Remove the specified WorkOrder from storage.
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function destroy($id)
    {
        $this->checkPermissions(['workorder.destroy']);

        abort(404);
        exit;

        /* $id = (int) $id;
        $this->workOrderRepository->destroy($id); */
    }

    /**
     * Show person data (addresses, billing company, project manager)
     *
     * @param int $personId
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function showPersonForWo($personId)
    {
        $this->checkPermissions(['workorder.personforwo']);

        $data = $this->workOrderRepository->getPersonData($personId);

        return response()->json(['details' => $data]);
    }

    /**
     * Cancel work order
     *
     * @param WorkOrderCancelRequest $request
     * @param WorkOrderCancelService $service
     * @param int                    $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function cancel(
        WorkOrderCancelRequest $request,
        WorkOrderCancelService $service,
        $id
    ) {
        $this->checkPermissions(['workorder.cancel']);

        $data = $service->cancel(
            $id,
            $request->input('invoice_status_type_id'),
            $request->input('bill_status_type_id'),
            $request->input('cancel_reason_type_id'),
            $request->input('additional_information')
        );

        return response()->json(['item' => $data]);
    }

    /**
     * Unlock work order
     *
     * @param int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function unlock($id)
    {
        $this->checkPermissions(['workorder.unlock']);

        $force = Auth::user()->hasPermissions(['workorder.unlock_force']);
        
        if ($this->workOrderRepository->unlock($id, $force)) {
            return response()->json(['status' => true], 200);
        } else {
            return response()->json(['status' => 403, 'error' => [
                'code' => 9,
                'message' => 'You do not have permission to unlock this work order',
                'devMessage' => 'You do not have permission to unlock this work order',
                'fields' => [],
                'data' => []
            ]], 403);
        }
    }

    /**
     * Pick up work order
     *
     * @param int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function pickup($id)
    {
        $this->checkPermissions(['workorder.pickup']);

        $data = $this->workOrderRepository->pickup($id);

        return response()->json(['status' => $data]);
    }

    /**
     * Get vendors to assign list
     *
     * @param WorkOrderVendorsToAssignRequest $request
     * @param WorkOrderSearchVendorsService   $service
     * @param Config                          $config
     * @param int                             $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \App\Modules\WorkOrder\Exceptions\WoInvoicedCannotAddVendorsException
     */
    public function vendorsToAssign(
        WorkOrderVendorsToAssignRequest $request,
        WorkOrderSearchVendorsService $service,
        Config $config,
        $id
    ) {
        $this->checkPermissions(['workorder.vendors-to-assign']);

        $onPage = $config->get('system_settings.quote_pagination');

        $data = $service->search(
            $id,
            $onPage,
            $request->input('type', null),
            $request->input('name', ''),
            (int)$request->input('region_id', 0),
            (int)$request->input('trade_id', 0),
            $request->input('job_type', '')
        );

        return response()->json($data);
    }

    /**
     * Assign multiple vendors to work order
     *
     * @param WorkOrderAssignVendorsRequest $request
     * @param WorkOrderAddVendorsService    $service
     * @param int                           $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \App\Modules\WorkOrder\Exceptions\TooManyVendorsItemsException
     * @throws \App\Modules\WorkOrder\Exceptions\WoInvoicedCannotAddVendorsException
     */
    public function assignVendors(WorkOrderAssignVendorsRequest $request, WorkOrderAddVendorsService $service, $id)
    {
        $this->checkPermissions(['workorder.assign-vendors']);

        $validator = new WorkOrderVendorsAssignValidatorService(
            $request,
            new WorkOrderAssignVendorRequest(),
            new InputFormatter(),
            'vendors'
        );

        $validator->validate();

        list($models, $changed) =
            $service->run(
                $id,
                $request->input('job_type', 'work'),
                $validator->getFormattedData()['vendors'],
                $request->input('recall_link_person_wo_id', null)
            );

        return response()->json(['items' => $models, 'changed' => $changed], 201);
    }

    /**
     * Get activities for work order
     *
     * @param int     $id
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function activities($id, Request $request)
    {
        $permissions =
            ['workorder.activities', 'workorder.activities-self-only'];
        $this->checkPermissions($permissions);

        $data = $this->workOrderRepository->getActivities(
            $id,
            false,
            $request->all()
        );

        return response()->json($data);
    }

    /**
     * Basic Update the specified WorkOrder in storage.
     *
     * @param WorkOrderNoteUpdateRequest $request
     * @param int                        $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\LockedMismatchException
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function noteUpdate(WorkOrderNoteUpdateRequest $request, $id)
    {
        $this->checkPermissions(['workorder.note-update']);

        $id = (int)$id;

        $record = $this->workOrderRepository
            ->updateNote($id, $request->all());

        return response()->json(['item' => $record]);
    }

    /**
     * Get Work Order locations
     *
     * @param WorkOrderLocationsService $service
     * @param Config                    $config
     * @param int                       $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function locations(
        WorkOrderLocationsService $service,
        Config $config,
        $id
    ) {
        $this->checkPermissions(['workorder.locations']);

        $id = (int)$id;

        $onPage = $config->get('system_settings.workorder_locations_pagination');

        $list = $service->get($id, $onPage);

        return response()->json($list);
    }

    /**
     * Get Work Order locations photos
     *
     * @param Request                   $request
     * @param WorkOrderLocationsService $service
     * @param Config                    $config
     * @param int                       $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function locationsPhotos(
        Request $request,
        WorkOrderLocationsService $service,
        Config $config,
        $id
    ) {
        $this->checkPermissions(['workorder.locations-photos']);

        $id = (int)$id;

        ini_set('gd.jpeg_ignore_warning', 1);
        ini_set('max_execution_time', 0);
        error_reporting(E_ALL & ~E_NOTICE);

        $onPage =
            $config->get('system_settings.workorder_locations_photos_pagination');

        $limit = (int)$request->input('limit', $onPage);
        if ($limit > 0 && $limit < $config->get('database.max_records')) {
            $onPage = $limit;
        }

        list($width, $height, $forceSize) =
            $this->getPhotoParams($request, $config);

        $list = $service->getPhotos(
            $id,
            $onPage,
            $width,
            $height,
            $forceSize,
            $request->input('with_dimensions', 0)
        );

        return response()->json($list);
    }

    /**
     * Get data for locations vendors  by workOrderId
     *
     * @param WorkOrderLocationsService $workOrderLocationsService
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function locationsVendors($id, WorkOrderLocationsService $workOrderLocationsService)
    {
        $this->checkPermissions(['workorder.locations-vendors']);

        $data = $workOrderLocationsService->getLocationsVendors($id);

        return response()->json($data);
    }

    /**
     * Get routes list
     *
     * @param WorkOrderLocationsService $workOrderLocationsService
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getRegions(WorkOrderLocationsService $workOrderLocationsService)
    {
        $this->checkPermissions(['workorder.regions']);

        $data = $workOrderLocationsService->getRegions();

        return response()->json(['data' => $data]);
    }

    /**
     * Get trades list
     *
     * @param WorkOrderLocationsService $workOrderLocationsService
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getTrades(WorkOrderLocationsService $workOrderLocationsService)
    {
        $this->checkPermissions(['workorder.trades']);

        $data = $workOrderLocationsService->getTrades();

        return response()->json(['data' => $data]);
    }

    /**
     * Return data for Release Calls view
     *
     * @param PersonRepository $personRepository
     * @param StateRepository  $stateRepository
     *
     * @return JsonResponse
     */
    public function getReleaseCallsData(
        PersonRepository $personRepository,
        StateRepository $stateRepository
    ) {
        $data = [
            'owners' => $personRepository->getOwners(),
            'states' => $stateRepository->getList(['US']),
        ];

        return response()->json($data);
    }

    /**
     * Get photo parameters from input or default from config if none provided
     *
     * @param Request $request
     * @param Config  $config
     *
     * @return array
     */
    protected function getPhotoParams(Request $request, Config $config)
    {
        return [
            (int)$request->input(
                'width',
                $config->get('system_settings.workorder_locations_photos_thumb_width')
            ),
            (int)$request->input(
                'height',
                $config->get('system_settings.workorder_locations_photos_thumb_height')
            ),
            (int)$request->input(
                'force_size',
                $config->get('system_settings.workorder_locations_photos_thumb_force_size')
            ),
        ];
    }

    /**
     * Return completion grid of WorkOrder
     *
     * @param Config $config
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function completionGrid(Config $config)
    {
        $this->checkPermissions(['workorder.completion-grid']);

        $onPage
            =
            $config->get('system_settings.workorder_completion_grid_pagination');
        $list = $this->workOrderRepository->paginateCompletionGrid($onPage);

        return response()->json($list);
    }

    /**
     * Get Work Order assets
     *
     * @param WorkOrderAssetsService $service
     * @param Config                 $config
     *
     * @return JsonResponse
     */
    public function assets(
        WorkOrderAssetsService $service,
        Config $config
    ) {
        ///$this->checkPermissions(['workorder.assets']);

        $onPage = $config->get('system_settings.workorder_assets_pagination');

        $list = $service->get($onPage);

        return response()->json($list);
    }

    /**
     * Get profitability for work order
     *
     * @param int     $id
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     * @throws NoSomePermissionException
     */
    public function profitability($id, Request $request)
    {
        $permissions = ['workorder.profitability'];

        // verify if user has permission to any of above permissions
        $statuses = $this->getPermissionsStatus($permissions);

        if (!in_array(true, $statuses)) {
            // user has no permission
            $exp = App::make(NoSomePermissionException::class);
            $exp->setData(['permissions' => $permissions]);
            throw $exp;
        }

        $data = $this->workOrderRepository->getProfitability(
            $id,
            false,
            $request->all()
        );

        return response()->json($data);
    }


    /**
     * Return list of non closed WorkOrders
     *
     * @param Config $config
     *
     * @return mixed
     *
     * @throws NoPermissionException
     */
    public function getNonClosedList(Config $config)
    {
        $this->checkPermissions(['workorder.non_closed_list']);

        $onPage = (int)$config->get('system_settings.workorder_pagination');
        $list = $this->workOrderRepository->getWorkOrdersNonCompleted($onPage);

        return response()->json($list);
    }

    /**
     * Get vendor details by work order id
     *
     * @param                         $id
     * @param WorkOrderVendorsService $workOrderVendorsService
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getVendorDetails($id, WorkOrderVendorsService $workOrderVendorsService)
    {
        $this->checkPermissions(['workorder.vendor_details']);

        $result = $workOrderVendorsService->getVendorDetails($id);

        return response()->json($result);
    }

    /**
     * Get vendor summary by work order id
     *
     * @param                         $id
     * @param WorkOrderVendorsService $workOrderVendorsService
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getVendorSummary($id, WorkOrderVendorsService $workOrderVendorsService)
    {
        $this->checkPermissions(['workorder.vendor_summary']);

        $result = $workOrderVendorsService->getVendorSummary($id);

        return response()->json($result);
    }

    /**
     * Get work order client ivr status
     *
     * @param                           $id
     * @param WorkOrderClientService    $WorkOrderClientService
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getClientIvr($id, WorkOrderClientService $WorkOrderClientService)
    {
        $this->checkPermissions(['workorder.client_ivr']);

        try {
            $result = $WorkOrderClientService->getIvr($id);
        } catch (ServiceNotSupportedException | NotImplementedException $e) {
            $result = ['status' => 'Sync not programmed'];
        } catch (ModelNotFoundException $e) {
            return response()->json([], 404);
        }

        return response()->json($result);
    }

    /**
     * Get work order client note
     *
     * @param                           $id
     * @param WorkOrderClientService    $WorkOrderClientService
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getClientNote($id, WorkOrderClientService $WorkOrderClientService)
    {
        $this->checkPermissions(['workorder.client_note']);

        try {
            $result = $WorkOrderClientService->getNote($id);
        } catch (ServiceNotSupportedException | NotImplementedException $e) {
            $result = ['status' => 'Sync not programmed'];
        } catch (ModelNotFoundException $e) {
            return response()->json([], 404);
        }

        return response()->json($result);
    }

    /**
     *
     * @param $id
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getProblemDetails($id)
    {
        $this->checkPermissions(['workorder.problem_details']);

        $id = (int)$id;
        $details = $this->workOrderRepository->getProblemDetails($id);

        return response()->json($details);
    }

    /**
     * @param         $id
     * @param Request $request
     *
     * @return mixed
     * @throws NoPermissionException
     */
    public function storeProblemNote($id, Request $request)
    {
        $this->checkPermissions(['workorder.problem_note']);

        $id = (int)$id;
        $this->workOrderRepository->storeProblemNote($id, $request->all());

        return response()->json([
            'message' => 'Problem note has been updated',
        ]);
    }

    /**
     * @param         $id
     * @param Request $request
     *
     * @return mixed
     * @throws NoPermissionException
     */
    public function storeCustomerNote($id, Request $request)
    {
        $this->checkPermissions(['workorder.customer_details']);

        $id = (int)$id;
        $this->workOrderRepository->storeCustomerNote($id, $request->all());

        return response()->json([
            'message' => 'Customer note has been updated',
        ]);
    }

    /**
     * @param         $id
     * @param Request $request
     *
     * @return mixed
     * @throws NoPermissionException
     */
    public function storeSiteNote($id, Request $request)
    {
        $this->checkPermissions(['workorder.site-details']);

        $id = (int)$id;
        $this->workOrderRepository->storeSiteNote($id, $request->all());

        return response()->json([
            'message' => 'Site note has been updated',
        ]);
    }

    /**
     * Get customer details by work order ID
     *
     * @param $id
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getCustomerDetails($id)
    {
        $this->checkPermissions(['workorder.customer_details']);
        $id = (int)$id;
        $customerDetails = $this->workOrderRepository->getCustomerDetails($id);

        return response()->json($customerDetails);
    }

    /**
     * Storing Customer Details
     *
     * @param         $id
     * @param Request $request
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function storeCustomerDetails($id, Request $request)
    {
        if (config('app.crm_user') == 'bfc') {
            return $this->storeCustomerNote($id, $request);
        }

        $this->checkPermissions(['workorder.customer_details']);
        $id = (int)$id;
        $customerDetails = $this->workOrderRepository->storeCustomerDetails($id, $request->all());

        return response()->json($customerDetails);
    }

    /**
     * get Site Details from address assigned to Work Order
     *
     * @param $id
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getSiteDetails($id)
    {
        $this->checkPermissions(['workorder.site-details']);
        $id = (int)$id;
        $siteDetails = $this->workOrderRepository->getSiteDetails($id);

        return response()->json($siteDetails);
    }

    /**
     * Storing Site Details
     *
     * @param         $id
     * @param Request $request
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function storeSiteDetails($id, Request $request)
    {
        if (config('app.crm_user') == 'bfc') {
            return $this->storeSiteNote($id, $request);
        }

        $this->checkPermissions(['workorder.site-details']);
        $id = (int)$id;
        $siteDetails = $this->workOrderRepository->storeSiteDetails($id, $request->all());

        return response()->json($siteDetails);
    }

    /**
     * @return JsonResponse
     */
    public function getPriority()
    {
        $woStatus = $this->workOrderRepository->getPriority();

        return response()->json($woStatus);
    }

    /**
     * @return JsonResponse
     */
    public function getWoStatus()
    {
        $woStatus = $this->workOrderRepository->getWoStatus();

        return response()->json($woStatus);
    }

    /**
     * @return JsonResponse
     */
    public function getInvoiceStatus()
    {
        $invoiceStatus = $this->workOrderRepository->getInvoiceStatus();

        return response()->json($invoiceStatus);
    }

    /**
     * @return JsonResponse
     */
    public function getCallTypes()
    {
        $callStatuses = $this->workOrderRepository->getSlCallTypes();

        return response()->json($callStatuses);
    }

    /**
     * @return JsonResponse
     */
    public function getSlWoStatuses()
    {
        $SLWoStatus = $this->workOrderRepository->getSlWoStatuses();

        return response()->json($SLWoStatus);
    }

    /**
     * @return JsonResponse
     */
    public function getSlTechStatuses()
    {
        $SLTechStatus = $this->workOrderRepository->getSlTechStatuses();

        return response()->json($SLTechStatus);
    }

    /**
     * @return JsonResponse
     */
    public function getSlTechnicians()
    {
        $techs = $this->workOrderRepository->getSlTechnicians();

        return response()->json($techs);
    }

    /**
     * @return JsonResponse
     */
    public function getLocations(Request $request)
    {
        $data = [];
        $data = $this->workOrderRepository->getLocations($request->all());

        return response()->json($data);
    }

    public function getWOReassign($id)
    {
        $this->checkPermissions(['workorder.wo_reassign']);
        $id = (int)$id;
        $woReassign = $this->workOrderRepository->getWOReassign($id);

        return response()->json($woReassign);
    }

    public function storeWOReassign($id, Request $request)
    {
        $this->checkPermissions(['workorder.wo_reassign']);
        $id = (int)$id;
        $woReassign = $this->workOrderRepository->storeWOReassign($id, $request->all());

        return response()->json($woReassign);
    }

    /**
     * Get work order history for BFC
     *
     * @param int $id
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getHistoryBfc($id)
    {
        $this->checkPermissions(['workorder.show']);
        $id = (int)$id;

        $history = $this->workOrderRepository->getHistoryBfc($id);

        return response()->json($history);
    }

    /**
     * Get Open Work Order List
     *
     * @param Request $request
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getOpenWorkOrders(Request $request)
    {
        $this->checkPermissions(['workorder.open_work_orders_list']);

        $list = $this->workOrderRepository->getOpenWorkOrders($request->all());

        return response()->json($list);
    }

    public function updateComment($id, Request $request)
    {
        $id = (int)$id;
        $woReassign = $this->workOrderRepository->updateComment($id, $request->all());

        return response()->json($woReassign);
    }

    public function getLinkedArticles($id, Request $request)
    {
        return response()->json(['data' => $this->workOrderRepository->getLinkedArticles($id)]);
    }

    public function linkArticle($id, $articleId, Request $request)
    {
        $status = $this->workOrderRepository->linkArticle($id, $articleId);
        return response()->json(['data' => ['success' => $status]], $status ? 200 : 500);
    }

    public function merge($fromWoId, $toWoId)
    {
        $fromWo = WorkOrder::findOrFail($fromWoId);
        $toWo = WorkOrder::findOrFail($toWoId);
        
        $status = $this->workOrderRepository->merge($fromWo, $toWo);

        return response()->json(['data' => ['success' => $status]], $status ? 200 : 500);
    }

    public function userActivities($id, Request $request)
    {
        $workOrder = WorkOrder::find($id);
        
        $activities = $this->workOrderRepository->getUserActivityData($workOrder->company_person_id);

        return response()->json($activities);
    }

    public function filters()
    {
        return response()->json(['filters' => $this->workOrderRepository->getFilters()]);
    }

    public function columns()
    {
        return response()->json(['columns' => $this->workOrderRepository->getColumns()]);
    }
    
    public function techStatusHistory(WorkOrderDataService $workOrderDataService, $id)
    {
        $statuses = $workOrderDataService->techStatusHistory($id);
        
        return response()->json(['data' => $statuses]);
    }
    
    public function getNotInvoiced(WorkOrderRepository $workOrderRepository)
    {
        $workOrders = $workOrderRepository->getNotInvoiced();

        return response()->json($workOrders);
    }

    /**
     * Return list of LinkPersonWo
     *
     * @param  LinkContactMonitorRepository  $linkContactMonitorRepository
     *
     * @param $id
     *
     * @return JsonResponse
     */
    public function getRelatedWith(LinkContactMonitorRepository $linkContactMonitorRepository, $id)
    {
        $contacts = $linkContactMonitorRepository->getRelatedWith('work_order.work_order_id', $id);

        return response()->json($contacts);
    }

    
    public function availableTabs(WorkOrderService $workOrderService)
    {
        $availableTabs = $workOrderService->availableTabs();

        return response()->json($availableTabs);
    }

    /**
     * @param  WorkOrderService  $workOrderService
     *
     * @return JsonResponse
     */
    public function dashboard(WorkOrderService $workOrderService)
    {
        $stats = $workOrderService->getDashboardStats();

        return response()->json(['data' => $stats]);
    }


    public function assignedMeetings(WorkOrderService $workOrderService)
    {
        $result = $workOrderService->getAssignedMeetings();

        return response()->json(['data' => $result]);
    }
    
    public function assignedTasks(WorkOrderService $workOrderService)
    {
        $result = $workOrderService->getAssignedTasks();

        return response()->json(['data' => $result]);
    }
    
    public function technicianSummary(TechnicianSummaryRequest $technicianSummaryRequest, WorkOrderService $workOrderService)
    {
        $result = $workOrderService->getTechnicianSummary($technicianSummaryRequest);

        return response()->json($result);
    }
    
    public function statusHistory(WorkOrderService $workOrderService, $workOrderId)
    {
        $result = $workOrderService->statusHistory($workOrderId);

        return response()->json(['data' => $result]);
    }
}
