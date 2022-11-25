<?php

namespace App\Modules\UserSettings\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Modules\UserSettings\Repositories\UserSettingsRepository;
use Illuminate\Config\Repository as Config;
use App\Modules\UserSettings\Http\Requests\UserSettingsRequest;
use Illuminate\Support\Facades\App;

/**
 * Class UserSettingsController
 *
 * @package App\Modules\UserSettings\Http\Controllers
 */
class UserSettingsController extends Controller
{
    /**
     * UserSettings repository
     *
     * @var UserSettingsRepository
     */
    private $userSettingsRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param UserSettingsRepository $userSettingsRepository
     */
    public function __construct(UserSettingsRepository $userSettingsRepository)
    {
        $this->middleware('auth');
        $this->userSettingsRepository = $userSettingsRepository;
    }

    /**
     * Return list of UserSettings
     *
     * @param Config $config
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['user-settings.index']);
        
        $onPage = $config->get('system_settings.user_settings_pagination', 50);
        
        $list = $this->userSettingsRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Get by type
     *
     * @param $type
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws App\Core\Exceptions\NoPermissionException
     */
    public function getByType($type)
    {
        $this->checkPermissions(['user-settings.show']);

        return response()->json($this->userSettingsRepository->getByType($type));
    }
    
    /**
     * Display the specified UserSettings
     *
     * @param  int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $this->checkPermissions(['user-settings.show']);

        return response()->json($this->userSettingsRepository->show($id));
    }

    /**
     * Return module configuration for store action
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        $this->checkPermissions(['user-settings.store']);
        
        $rules['fields'] = $this->userSettingsRepository->getRequestRules();

        return response()->json($rules);
    }


    /**
     * Store a newly created UserSettings in storage.
     *
     * @param UserSettingsRequest $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(UserSettingsRequest $request)
    {
        $this->checkPermissions(['user-settings.store']);
        
        $model = $this->userSettingsRepository->create($request->all());

        return response()->json(['item' => $model], 201);
    }

    /**
     * Display UserSettings and module configuration for update action
     *
     * @param  int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        $this->checkPermissions(['user-settings.update']);
        
        return response()->json($this->userSettingsRepository->show($id, true));
    }

    /**
     * Update the specified UserSettings in storage.
     *
     * @param UserSettingsRequest $request
     * @param  int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UserSettingsRequest $request, $id)
    {
        $this->checkPermissions(['user-settings.update']);

        $record = $this->userSettingsRepository->updateWithIdAndInput($id, $request->all());

        return response()->json(['item' => $record]);
    }

    /**
     * Remove the specified UserSettings from storage.
     *
     * @param  int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $this->checkPermissions(['user-settings.destroy']);
        abort(404);
        exit;

        /* $id = (int) $id;
        $this->userSettingsRepository->destroy($id); */
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function types()
    {
        $types = $this->userSettingsRepository->getTypes();
        
        return response()->json(['data' => $types]);
    }
}
