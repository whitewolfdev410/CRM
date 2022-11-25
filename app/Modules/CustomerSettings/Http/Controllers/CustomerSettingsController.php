<?php

namespace App\Modules\CustomerSettings\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use App\Modules\CustomerSettings\Http\Requests\AssetRequiredRequest;
use App\Modules\CustomerSettings\Http\Requests\CustomerSettingsRequest;
use App\Modules\CustomerSettings\Http\Requests\UpdateCustomerSettingsRequest;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsRepository;
use App\Modules\CustomerSettings\Services\CustomerSettingsAssetRequiredService;
use App\Modules\CustomerSettings\Services\CustomerSettingsHistoryService;
use App\Modules\CustomerSettings\Services\CustomerSettingsService;
use App\Modules\MsDynamics\Services\MsDynamicsService;
use App\Modules\WorkOrder\Services\LinkLaborFileRequiredService;
use Exception;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\Request;

/**
 * Class CustomerSettingsController
 *
 * @package App\Modules\CustomerSettings\Http\Controllers
 */
class CustomerSettingsController extends Controller
{
    /**
     * CustomerSettings repository
     *
     * @var CustomerSettingsRepository
     */
    private $customersettingsRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param CustomerSettingsRepository $customersettingsRepository
     */
    public function __construct(
        CustomerSettingsRepository $customersettingsRepository
    ) {
        $this->middleware('auth');
        $this->customersettingsRepository = $customersettingsRepository;
    }

    /**
     * Return list of CustomerSettings
     *
     * @param Config $config
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['customersettings.index']);
        $onPage = $config->get('system_settings.customersettings_pagination');
        $list = $this->customersettingsRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Display the specified CustomerSettings
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $this->checkPermissions(['customersettings.show']);
        $id = (int)$id;

        return response()->json($this->customersettingsRepository->show($id));
    }

    /**
     * Return module configuration for store action
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        $this->checkPermissions(['customersettings.store']);
        $rules['fields'] = $this->customersettingsRepository->getRequestRules();

        return response()->json($rules);
    }


    /**
     * Store a newly created CustomerSettings in storage.
     *
     * @param CustomerSettingsRequest $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CustomerSettingsRequest $request)
    {
        $this->checkPermissions(['customersettings.store']);
        $model = $this->customersettingsRepository->create($request->all());

        return response()->json(['item' => $model], 201);
    }

    /**
     * Display CustomerSettings and module configuration for update action
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        $this->checkPermissions(['customersettings.update']);
        $id = (int)$id;

        return response()->json($this->customersettingsRepository->show(
            $id,
            true
        ));
    }

    /**
     * Update the specified CustomerSettings in storage.
     *
     * @param CustomerSettingsRequest $request
     * @param int                     $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CustomerSettingsRequest $request, $id)
    {
        $this->checkPermissions(['customersettings.update']);
        $id = (int)$id;

        $record = $this->customersettingsRepository->updateWithIdAndInput(
            $id,
            $request->all()
        );

        return response()->json(['item' => $record]);
    }

    /**
     * Display the specified CustomerSettings
     *
     * @param CustomerSettingsService $service
     * @param                         $customerSettingsId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function showSettings(CustomerSettingsService $service, $customerSettingsId)
    {
        $this->checkPermissions(['customersettings.show']);

        return response()->json($service->show($customerSettingsId));
    }

    /**
     * Update the specified CustomerSettings in storage.
     *
     * @param UpdateCustomerSettingsRequest $request
     * @param CustomerSettingsService       $service
     * @param                               $customerSettingsId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSettings(
        UpdateCustomerSettingsRequest $request,
        CustomerSettingsService $service,
        $customerSettingsId
    ) {
        $this->checkPermissions(['customersettings.update']);
        
        try {
            $status = $service->updateSettings($customerSettingsId, $request->get('settings', []));
            return response()->json(['status' => $status]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove the specified CustomerSettings from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $this->checkPermissions(['customersettings.destroy']);
        abort(404);
        exit;

        /* $id = (int) $id;
        $this->customersettingsRepository->destroy($id); */
    }

    /**
     * @param Request                 $request
     * @param CustomerSettingsService $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAssetRequiredTypes(
        Request $request,
        CustomerSettingsService $service
    ) {
        $this->checkPermissions(['type.index']);
        
        $types = $service->getAssetTypes();

        return response()->json(['data' => $types]);
    }

    /**
     * @param Request                 $request
     * @param CustomerSettingsService $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPhotoRequiredTypes(
        Request $request,
        CustomerSettingsService $service
    ) {
        $this->checkPermissions(['type.index']);
        
        $types = $service->getPhotoTypes();

        return response()->json(['data' => $types]);
    }

    /**
     * @param Request                 $request
     * @param CustomerSettingsService $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWorkOrderPhotoRequiredTypes(
        Request $request,
        CustomerSettingsService $service
    ) {
        $this->checkPermissions(['type.index']);
        
        $types = $service->getWorkOrderPhotoTypes();

        return response()->json(['data' => $types]);
    }


    /**
     * @param Request                 $request
     * @param CustomerSettingsService $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWorkOrderRequiredTypes(
        Request $request,
        CustomerSettingsService $service
    ) {
        $this->checkPermissions(['type.index']);
        
        $types = $service->getWorkOrderTypes();

        return response()->json(['data' => $types]);
    }


    /**
     * @param Request                 $request
     * @param CustomerSettingsService $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAssetSystemRequiredTypes(
        Request $request,
        CustomerSettingsService $service
    ) {
        $this->checkPermissions(['type.index']);
        
        $types = $service->getAssetSystemTypes();

        return response()->json(['data' => $types]);
    }


    /**
     * @param Request                 $request
     * @param CustomerSettingsService $service
     * @param int                     $customerSettingsId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveLinkFileRequired(
        Request $request,
        CustomerSettingsService $service,
        $customerSettingsId
    ) {
        $this->checkPermissions(['customersettings.update']);
        
        try {
            $service->saveLinkFileRequired($customerSettingsId);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param Request                 $request
     * @param CustomerSettingsService $service
     * @param int                     $customerSettingsId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLinkFileRequired(
        Request $request,
        CustomerSettingsService $service,
        $customerSettingsId
    ) {
        $this->checkPermissions(['customersettings.index']);
        
        try {
            $data = $service->getLinkFileRequired($customerSettingsId);
            return response()->json(['data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param Request                 $request
     * @param CustomerSettingsService $service
     * @param int                     $customerSettingsId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteLinkFileRequired(
        Request $request,
        CustomerSettingsService $service,
        $customerSettingsId
    ) {
        try {
            $service->deleteLinkFileRequired($customerSettingsId);
            return response()->json(['status' => true]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param AssetRequiredRequest                 $request
     * @param CustomerSettingsAssetRequiredService $service
     * @param int                                  $customerSettingsId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveAssetRequired(
        AssetRequiredRequest $request,
        CustomerSettingsAssetRequiredService $service,
        $customerSettingsId
    ) {
        try {
            $status = $service->saveAssetRequired($customerSettingsId);
            return response()->json(['status' => $status]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param AssetRequiredRequest                 $request
     * @param CustomerSettingsAssetRequiredService $service
     * @param int                                  $customerSettingsId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAssetRequired(
        AssetRequiredRequest $request,
        CustomerSettingsAssetRequiredService $service,
        $customerSettingsId
    ) {
        try {
            $status = $service->deleteAssetRequired($customerSettingsId);
            return response()->json(['status' => $status]);
        } catch (Exception $e) {
            return response()->json(['status' => false], 422);
        }
    }

    /**
     * @param Request $request
     * @param string  $customerId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomerId(Request $request, $customerId)
    {
        $this->checkPermissions(['customersettings.show']);
        
        try {
            $customerSettings = $this->customersettingsRepository->getPersonForCustomerId($customerId);
            return response()->json([
                'customer_settings_id' => $customerSettings->customer_settings_id,
                'customer_name'        => $customerSettings->person_name,
                'person_id'            => $customerSettings->person_id ?? null
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param Request $request
     * @param int     $personId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomerIdByPersonId(Request $request, $personId)
    {
        $this->checkPermissions(['customersettings.show']);
        
        try {
            $customerSettings = $this->customersettingsRepository->getPersonForPersonId($personId);
            return response()->json([
                'customer_settings_id'      => $customerSettings->customer_settings_id,
                'customer_name'             => $customerSettings->person_name,
                'customer_id'               => $customerSettings->sl_record_id ?? null,
                'required_work_order_files' => config('mobile.settings.app.required_work_order_files')
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param Request                 $request
     * @param CustomerSettingsService $service
     * @param int                     $customerSettingsId
     *
     * @return mixed
     */
    public function saveWorkOrderRequiredFiles(
        Request $request,
        CustomerSettingsService $service,
        $customerSettingsId
    ) {
        $this->checkPermissions(['customersettings.update']);
        
        try {
            $service->saveWorkOrderFileLinkRequired($customerSettingsId);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param Request                 $request
     * @param CustomerSettingsService $service
     * @param int                     $customerSettingsId
     *
     * @return mixed
     */
    public function getWorkOrderRequiredFiles(
        Request $request,
        CustomerSettingsService $service,
        $customerSettingsId
    ) {
        $this->checkPermissions(['customersettings.index']);
        
        try {
            $data = $service->getWorkOrderFileLinkRequired($customerSettingsId);

            return response()->json(['data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param CustomerSettingsService $service
     * @param int                     $customerSettingsId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteWorkOrderRequiredFiles(
        Request $request,
        CustomerSettingsService $service,
        $customerSettingsId
    ) {
        try {
            $service->deleteWorkOrderFileLinkRequired($customerSettingsId);
            return response()->json(['status' => true]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param MsDynamicsService $msDynamicsService
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLaborTypes(MsDynamicsService $msDynamicsService)
    {
        $this->checkPermissions(['type.index']);
        
        try {
            $data = $msDynamicsService->getLaborTypes();
            $data = array_map(function ($invtId, $desc) {
                return [
                    'value' => trim($invtId),
                    'label' => trim($desc)
                ];
            }, array_keys($data), $data);

            return response()->json(['status' => true, 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param Request                      $request
     * @param LinkLaborFileRequiredService $service
     * @param int                          $customerSettingsId
     *
     * @return mixed
     */
    public function saveLaborRequiredFiles(Request $request, LinkLaborFileRequiredService $service, $customerSettingsId)
    {
        $this->checkPermissions(['customersettings.update']);
        
        try {
            $service->saveLaborLinkFiles($customerSettingsId, $request);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param LinkLaborFileRequiredService $service
     * @param int                          $customerSettingsId
     *
     * @return mixed
     */
    public function getLaborRequiredFiles(LinkLaborFileRequiredService $service, $customerSettingsId)
    {
        $this->checkPermissions(['customersettings.index']);
        
        try {
            $data = $service->getLaborLinkFiles($customerSettingsId);
            return response()->json(['data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param Request                        $request
     * @param CustomerSettingsHistoryService $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request, CustomerSettingsHistoryService $service)
    {
        $history = $service->getHistory($request);

        return response()->json($history);
    }
}
