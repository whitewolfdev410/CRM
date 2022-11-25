<?php

namespace App\Modules\User\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use App\Modules\User\Http\Requests\UserDeviceTokenStoreRequest;
use App\Modules\User\Repositories\UserDeviceTokenRepository;
use App\Modules\User\Services\UserDeviceTokenService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;

/**
 * Class UserDeviceTokenController
 *
 * @package App\Modules\User\Http\Controllers
 */
class UserDeviceTokenController extends Controller
{
    /**
     * User repository
     *
     * @var UserDeviceTokenRepository
     */
    private $tokenRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param UserDeviceTokenRepository $tokenRepository
     */
    public function __construct(UserDeviceTokenRepository $tokenRepository)
    {
        $this->middleware('auth');
        $this->tokenRepository = $tokenRepository;
    }

    /**
     * Store (or update) new user device token
     *
     * @param  UserDeviceTokenStoreRequest $request
     * @param  UserDeviceTokenService      $service
     *
     * @return Response
     * @throws App\Core\Exceptions\NoPermissionException
     */
    public function store(
        UserDeviceTokenStoreRequest $request,
        UserDeviceTokenService $service
    ) {
        $this->checkPermissions(['user-device-token.store']);
        $model = $service->add(
            $request->input('device_type', ''),
            $request->input('device_token', ''),
            Auth::user()->getAccessToken(),
            $request->input('device_token_type', '')
        );

        return response()->json(['item' => $model], 201);
    }
}
