<?php

namespace App\Modules\CustomerSettings\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use App\Modules\CustomerSettings\Http\Requests\UpdateCustomerSettingsItemsRequest;
use App\Modules\CustomerSettings\Services\CustomerSettingsItemsService;
use Exception;

/**
 * Class CustomerSettingsItemsController
 *
 * @package App\Modules\CustomerSettings\Http\Controllers
 */
class CustomerSettingsItemsController extends Controller
{
    /**
     * Set repository and apply auth filter
     *
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the specified CustomerSettings
     *
     * @param CustomerSettingsItemsService $service
     * @param                              $customerSettingsId
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws App\Core\Exceptions\NoPermissionException
     */
    public function show(CustomerSettingsItemsService $service, $customerSettingsId)
    {
        $this->checkPermissions(['customersettings.show']);

        return response()->json($service->show($customerSettingsId));
    }

    /**
     * Update the specified CustomerSettings in storage.
     *
     * @param UpdateCustomerSettingsItemsRequest $request
     * @param CustomerSettingsItemsService       $service
     * @param                                    $customerSettingsId
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws App\Core\Exceptions\NoPermissionException
     */
    public function update(
        UpdateCustomerSettingsItemsRequest $request,
        CustomerSettingsItemsService $service,
        $customerSettingsId
    ) {
        $this->checkPermissions(['customersettings.update']);
        
        try {
            $status = $service->update($customerSettingsId, $request);
            return response()->json(['status' => $status]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
