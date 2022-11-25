<?php

namespace App\Modules\User\Http\Controllers;

use App\Core\Exceptions\NoPermissionException;
use App\Core\Oauth2\AccessToken;
use App\Http\Controllers\Controller;
use App\Modules\MobileAuth\Models\UserDevice;
use App\Modules\MobileAuth\Repositories\UserDeviceRepository;
use App\Modules\User\Http\Requests\UserDeviceStoreRequest;
use App\Modules\User\Http\Requests\UserDeviceUpdateRequest;
use App\Modules\User\Services\UserDeviceHistoryService;
use Exception;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Class UserDeviceController
 *
 * @package App\Modules\User\Http\Controllers
 */
class UserDeviceController extends Controller
{
    /**
     * User repository
     *
     * @var UserDeviceRepository
     */
    private $deviceRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param UserDeviceRepository $deviceRepository
     */
    public function __construct(UserDeviceRepository $deviceRepository)
    {
        $this->middleware('auth');
        $this->deviceRepository = $deviceRepository;
    }

    /**
     * Return list of User devices
     *
     * @param Config $config
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['user-device.index']);

        $onPage = $config->get('system_settings.user_devices_pagination');
        $list = $this->deviceRepository->paginate($onPage);

        return response()->json($list);
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
        $this->checkPermissions(['user-device.store']);

        $rules['fields'] = $this->deviceRepository->getConfig('create');

        return response()->json($rules);
    }


    /**
     * Store a newly created User device in storage.
     *
     * @param UserDeviceStoreRequest $request
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function store(UserDeviceStoreRequest $request)
    {
        $this->checkPermissions(['user-device.store']);
        $model = $this->deviceRepository->create($request->all());

        return response()->json(['item' => $model], 201);
    }

    /**
     * Display User device and module configuration for update action
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
        $this->checkPermissions(['user-device.update']);
        $id = (int)$id;

        return response()->json($this->deviceRepository->show($id, true));
    }

    /**
     * Update the specified User device in storage.
     *
     * @param UserDeviceUpdateRequest $request
     * @param int                     $id
     * @param AccessToken             $accessToken
     *
     * @return JsonResponse
     *
     * @throws Exception
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function update(
        UserDeviceUpdateRequest $request,
        $id,
        AccessToken $accessToken
    ) {
        $this->checkPermissions(['user-device.update']);
        $id = (int)$id;

        /** @var UserDevice $device */
        $device = $this->deviceRepository->updateWithIdAndInput($id, $request->all());

        /* in case device set to inactive, access token for this device should
           be removed to not allow to access API by this device any more */

        if ($request->input('active') == 0) {
            $accessToken->deleteByDeviceNumber(
                $device->getNumber(),
                $device->getUserId(),
                $device->getDeviceImei()
            );
        }

        return response()->json(['item' => $device]);
    }

    /**
     * Remove the specified Device from storage.
     *
     * @param int         $id
     * @param AccessToken $accessToken
     *
     * @throws Exception
     * @throws mixed
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function destroy(
        $id,
        AccessToken $accessToken
    ) {
        $this->checkPermissions(['user-device.destroy']);

        $id = (int)$id;

        DB::transaction(function () use (
            $id,
            $accessToken
        ) {
            /** @var UserDevice $device */
            $device = $this->deviceRepository->find($id);

            $accessToken->deleteByDeviceNumber(
                $device->getNumber(),
                $device->getUserId(),
                $device->getDeviceImei()
            );

            $this->deviceRepository->destroy($id);
        });
    }

    /**
     * Return history of Asset
     *
     * @param Config                   $config
     * @param UserDeviceHistoryService $service
     *
     * @return JsonResponse
     *
     * @throws InvalidArgumentException
     * @throws NoPermissionException
     */
    public function getHistory(
        Config $config,
        UserDeviceHistoryService $service
    ) {
        $this->checkPermissions(['user-device.index']);

        $onPage = $config
            ->get(
                'system_settings.user_device_history_pagination',
                2000
            );

        $list = $service->getAll($onPage);

        return response()->json($list);
    }
}
