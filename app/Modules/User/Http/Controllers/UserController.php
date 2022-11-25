<?php

namespace App\Modules\User\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\NoPermissionException;
use App\Http\Controllers\Controller;
use App\Modules\Mainmenu\Repositories\MainmenuRoleRepository;
use App\Modules\User\Http\Requests\UserStoreRequest;
use App\Modules\User\Http\Requests\UserUpdateRequest;
use App\Modules\User\Repositories\UserRepository;
use App\Modules\User\Services\UserService;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class UserController
 *
 * @package App\Modules\User\Http\Controllers
 */
class UserController extends Controller
{
    /**
     * User repository
     *
     * @var UserRepository
     */
    private $userRepository;
    /**
     * User service
     *
     * @var UserService
     */
    private $userService;

    /**
     * Set repository and apply auth filter
     *
     * @param UserRepository $userRepository
     * @param UserService    $userService
     */
    public function __construct(
        UserRepository $userRepository,
        UserService $userService
    ) {
        $this->middleware('auth');
        $this->userRepository = $userRepository;
        $this->userService = $userService;
    }

    /**
     * Return list of User
     *
     * @param Config $config
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['user.index']);
        $onPage = $config->get('system_settings.user_pagination');
        $list = $this->userRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Display the specified User
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     * @throws ModelNotFoundException
     */
    public function show($id)
    {
        $this->checkPermissions(['user.show']);
        $id = (int)$id;

        return response()->json($this->userRepository->show($id));
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
        $this->checkPermissions(['user.store']);
        $rules['fields'] = $this->userRepository->getConfig('create');

        return response()->json($rules);
    }

    /**
     * Store a newly created User in storage.
     *
     * @param UserStoreRequest $request
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function store(UserStoreRequest $request)
    {
        $this->checkPermissions(['user.store']);
        list($model, $roles) = $this->userService->create($request->all());

        $data['item'] = $model;
        $data['roles'] = $roles;

        return response()->json($data, 201);
    }

    /**
     * Display User and module configuration for update action
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     * @throws ModelNotFoundException
     */
    public function edit($id)
    {
        $this->checkPermissions(['user.update']);
        $id = (int)$id;

        return response()->json($this->userRepository->show($id, true));
    }

    /**
     * Update the specified User in storage.
     *
     * @param UserUpdateRequest $request
     * @param  int              $id
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     * @throws ModelNotFoundException
     *
     */
    public function update(UserUpdateRequest $request, $id)
    {
        $this->checkPermissions(['user.update']);
        $id = (int)$id;

        list($model, $roles) = $this->userService->update($id, $request->all());

        $data['item'] = $model;
        $data['roles'] = $roles;

        $mmRepo = app()->make(MainMenuRoleRepository::class);
        $mmRepo->clearCache();
        
        return response()->json($data);
    }

    /**
     * Remove the specified User from storage.
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws HttpException
     * @throws NoPermissionException
     */
    public function destroy($id)
    {
        $this->checkPermissions(['user.destroy']);
        abort(404);
        exit;

        /* $id = (int) $id;
        $this->userRepository->destroy($id); */
    }

    /**
     * Generate direct login token for the User.
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     * @throws ModelNotFoundException
     */
    public function generateDirectLogin($id)
    {
        $this->checkPermissions([
            'user.show',
            'user.update',
        ]);

        $token = $this->userService->generateDirectLoginById($id);

        return response()->json([
            'token' => $token,
        ]);
    }
}
