<?php

namespace App\Http\Controllers;

use App\Core\LoggedUser;
use App\Core\Oauth2\AccessToken;
use App\Core\User;
use App\Http\Requests\DirectLoginRequest;
use App\Http\Requests\FcmTokenRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\AutoLoginRequest;
use App\Http\Requests\LogoutRequest;
use App\Modules\Mainmenu\Repositories\MainmenuRoleRepository;
use App\Modules\User\Services\UserDeviceTokenService;
use App\Modules\User\Services\UserService;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use App\Modules\WorkOrder\Services\WorkOrderService;
use App\Services\AuthService;
use Carbon\Carbon;
use Exception;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * The Guard implementation.
     *
     * @var Guard
     */
    protected $auth;

    /**
     * The Container implementation.
     *
     * @var Container
     */
    protected $app;

    /**
     * The UserService implementation.
     *
     * @var UserService
     */
    protected $userService;

    /**
     * Create a new authentication controller instance.
     *
     * @param Container   $app
     * @param Guard       $auth
     * @param UserService $userService
     */
    public function __construct(
        Container $app,
        Guard $auth,
        UserService $userService
    ) {
        $this->app = $app;
        $this->auth = $auth;
        $this->userService = $userService;
        $this->middleware('auth', ['except' => ['postDirectLogin', 'postToken', 'postAutoLogin']]);
    }

    /**
     * Handle a direct login request to the application.
     *
     * @param AuthService        $authService
     * @param DirectLoginRequest $request
     *
     * @return JsonResponse
     */
    public function postDirectLogin(
        AuthService $authService,
        DirectLoginRequest $request
    ) {
        /** @var User $user */
        $user = User::with('person')
            ->where('direct_login_token', $request->input('token'))
            ->first();

        $response = $authService->logIn($user);

        switch ($response['code']) {
            case AuthService::INVALID_USER:
                return $this->invalidUserResponse($user);
                break;
            case AuthService::LOCKED:
                return $this->userLockedResponse($response['data']);
                break;
            case AuthService::PASSWORD_TEMPORARY:
                return $this->passwordTemporaryResponse($user);
                break;
            case AuthService::PASSWORD_EXPIRED:
                return $this->userPasswordExpiredResponse();
                break;
            default:
        }

        $accessToken = $authService->getAccessToken(
            $user,
            $request->input('device_type', AccessToken::DEVICE_TYPE_WEB),
            $request->input('device_id', ''),
            $request->getClientIp()
        );

        return $this->validUserResponse($user, $accessToken, $request->get('is_new_crm', false));
    }

    /**
     * @TODO: Move logic to AuthService
     *
     * Handle a login request to the application.
     *
     * @param  LoginRequest $request
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function postToken(LoginRequest $request)
    {
        /** @var User $user */
        $user = User::with('person')
            ->where('email', $request->input('email'))
            ->first();

        $lockTimeLeft = $this->userService->getLockTimeLeft($user);
        if ($lockTimeLeft > 0) {
            return $this->userLockedResponse($lockTimeLeft);
        }

        if (!$user) {
            return $this->invalidUserResponse($user);
        }

        if (!$user->isPasswordValid($request->input('password'))) {
            return $this->invalidUserResponse($user);
        }

        if ($user->isPasswordTemporary()) {
            return $this->passwordTemporaryResponse($user);
        }

        $expireAt = $user->getExpireAt();
        if ($expireAt && $expireAt->lte(Carbon::now())) {
            return $this->userPasswordExpiredResponse();
        }

        $this->auth->loginUsingId($user->getId());
        

        $deviceType = $request->input('is_mobile', false)
            ? AccessToken::DEVICE_TYPE_MOBILE
            : AccessToken::DEVICE_TYPE_WEB;
        
        // @TODO device_id is required when device_type != AccessToken::DEVICE_TYPE_WEB
        $accessToken = AccessToken::createForUser(
            $user,
            $request->input('device_type', $deviceType),
            $request->input('device_id', ''),
            $request->getClientIp()
        );

        $deviceToken = $request->input('device_token', null);
        if ($deviceType === AccessToken::DEVICE_TYPE_MOBILE && $deviceToken) {
            $deviceTokenService = app(UserDeviceTokenService::class);

            $deviceTokenService->add(
                $request->input('device_system', 'android'),
                $deviceToken,
                $accessToken,
                $request->input('device_token_type', 'fcm')
            );
        }
        
        return $this->validUserResponse($user, $accessToken, $request->get('is_new_crm', false));
    }

    /**
     * Save fcm token
     *
     * @param  FcmTokenRequest  $request
     *
     * @return JsonResponse
     *
     */
    public function postFcmToken(FcmTokenRequest $request)
    {
        $accessToken = AccessToken::find($request->bearerToken());
        
        /** @var UserDeviceTokenService $deviceTokenService */
        $deviceTokenService = app(UserDeviceTokenService::class);

        $deviceToken = $deviceTokenService->add(
            $request->input('device_type', 'browser'),
            $request->input('fcm_token'),
            $accessToken,
            'fcm'
        );

        return response()->json(['status' => (bool)$deviceToken]);
    }
    
    /**
     * @param AutoLoginRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function postAutoLogin(AutoLoginRequest $request)
    {
        $user = User::with('person')
            ->where('direct_login_token', $request->input('auto_login_hash'))
            ->first();
        
        if (!$user) {
            return $this->invalidUserResponse($user);
        }
        
        $this->auth->loginUsingId($user->getId());

        // @TODO device_id is required when device_type != AccessToken::DEVICE_TYPE_WEB
        $accessToken = AccessToken::createForUser(
            $user,
            $request->input('device_type', AccessToken::DEVICE_TYPE_WEB),
            $request->input('device_id', ''),
            $request->getClientIp()
        );

        return $this->validUserResponse($user, $accessToken, true);
    }


    /**
     * Log the user out of the application.
     *
     * @param  LogoutRequest  $request
     *
     * @param  WorkOrderRepository  $workOrderRepository
     *
     * @return JsonResponse
     */
    public function getLogout(LogoutRequest $request, WorkOrderRepository $workOrderRepository)
    {
        try {
            $personId = $this->auth->user()->getPersonId();
            $workOrderRepository->unlockAllByPersonId($personId);
        } catch (Exception $e) {
        }
        
        $this->auth->logout();
        AccessToken::deleteByAuthorizationHeader($request->header('Authorization'));

        return response()->json(
            [
                'message' => 'Ok.',
            ]
        );
    }

    /**
     * Creates response with user data
     *
     * @param User        $user
     * @param AccessToken $accessToken
     * @param bool        $isNewCrm whether get old or new crm menu
     *
     * @return JsonResponse
     */
    private function validUserResponse(User $user, AccessToken $accessToken, $isNewCrm = false)
    {
        $user->setLastLoginAt(date('Y-m-d H:i:s'));
        $this->userService->clearFailedLoginAttempts($user);

        $expireAt = $user->getExpireAt();

        $data = [
            'access_token' => $accessToken->getId(),
            'customer' => $this->app->config->get('app.crm_user'),
            'google_api_key' => $this->app->config->get('google.api_key'),
            'expire_time'  => $accessToken->getExpireTime(),
            'user'         => [
                'email'         => $user->getEmail(),
                'username'      => $user->getUsername(),
                'company_person_id' => $user->getCompanyPersonId(),
                'person_id'     => $user->getPersonId(),
                'twilio_number' => $user->getTwilioNumber(),
                'roles'         => $user->roles->pluck('name', 'id')->all(),
                'scope'         => $user->getScopes(),
                'gui_settings'  => $user->getGuiSettings(),
                'expire_in'     => $expireAt ? Carbon::now()->diffInDays($expireAt) : -1,
            ],
            'legacy_domain' => config('app.legacy_url')
        ];

        if ($accessToken->isForWeb()) {
            $mainmenu = [];

            try {
                $mainmenu = $this->getMenuTree($user->roles->pluck('id')->all(), $isNewCrm);
            } catch (\Exception $e) {
//                Log::error('Error while fetching user menu: ' . $e->getMessage());
            }

            $data['mainmenu'] = $mainmenu;
        }

        return response()->json($data);
    }

    /**
     * Creates invalid response with error message and 401 http status
     *
     * @param User $user
     *
     * @return JsonResponse
     */
    private function invalidUserResponse($user)
    {
        $this->userService->handleLoginFail($user);

        return response()->json(
            [
                'message' => trans('passwords.invalid'),
                'error'   => true,
            ],
            401
        );
    }

    /**
     * Creates password temporary response with error message and 401 http status
     *
     * @param User $user
     *
     * @return JsonResponse
     */
    private function passwordTemporaryResponse($user)
    {
        $tokens = $this->app->make(PasswordBroker::class);

        return response()->json(
            [
                'message' => trans('passwords.temporary'),
                'token'   => $tokens->createToken($user),
                'error'   => false,
                'is_temp_password' => true,
            ],
            401
        );
    }

    /**
     * Creates user locked response with error message and 401 http status
     *
     * @param int $lockTimeLeft
     *
     * @return JsonResponse
     */
    private function userLockedResponse($lockTimeLeft)
    {
        return response()->json(
            [
                'message' => trans('passwords.locked', [
                    'lockTimeLeft' => $lockTimeLeft,
                ]),
                'error'   => true,
            ],
            401
        );
    }

    /**
     * Creates user password expired response with error message and 401 http status
     *
     * @return JsonResponse
     */
    private function userPasswordExpiredResponse()
    {
        return response()->json(
            [
                'message' => trans('passwords.expired'),
                'error'   => true,
            ],
            401
        );
    }

    /**
     * Get user menu tree for user roles
     *
     * @param array $roles
     * @param bool  $isNewCrm whether get old or new crm menu
     *
     * @return mixed
     */
    private function getMenuTree(array $roles, $isNewCrm = false)
    {
        /** @var MainmenuRoleRepository $menuRoleRepository */
        $menuRoleRepository = \App::make(MainmenuRoleRepository::class);

        $role = implode(',', $roles);
        if ($isNewCrm) {
            // Get new crm menu
            $id = $menuRoleRepository->findNewMenuIdForRoles($roles);
        } else {
            // Get old crm menu
            $id = $menuRoleRepository->findIdMenuForRoles($roles);
        }

        $loggedUser = \App::make(LoggedUser::class);

        return $menuRoleRepository->findForRole($id, $role, $loggedUser);
    }
}
