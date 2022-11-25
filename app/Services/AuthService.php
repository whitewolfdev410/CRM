<?php

namespace App\Services;

use App\Core\Oauth2\AccessToken;
use App\Core\User;
use App\Modules\User\Services\UserService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Guard;

class AuthService
{
    const LOGIN_OK = 0;
    const INVALID_USER = 1;
    const LOCKED = 2;
    const PASSWORD_TEMPORARY = 3;
    const PASSWORD_EXPIRED = 4;

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
     * Create a new authentication service instance.
     *
     * @param Container   $app
     * @param Guard       $auth
     * @param UserService $userService
     */
    public function __construct(Container $app, Guard $auth, UserService $userService)
    {
        $this->app = $app;
        $this->auth = $auth;
        $this->userService = $userService;
    }

    /**
     * @param User $user
     *
     * @return array
     */
    public function logIn(
        $user
    ) {
        $lockTimeLeft = $this->userService->getLockTimeLeft($user);
        if ($lockTimeLeft > 0) {
            return $this->response(self::LOCKED, $lockTimeLeft);
        }

        if (!$user) {
            return $this->response(self::INVALID_USER);
        }

        if ($user->isPasswordTemporary()) {
            return $this->response(self::PASSWORD_TEMPORARY);
        }

        $expireAt = $user->getExpireAt();
        if ($expireAt && $expireAt->lte(Carbon::now())) {
            return $this->response(self::PASSWORD_EXPIRED);
        }

        $this->auth->loginUsingId($user->getId());

        return $this->response(self::LOGIN_OK);
    }

    /**
     * @param User   $user
     * @param string $password
     *
     * @return array
     */
    public function logInWithPassword(
        $user,
        $password
    ) {
        if (!$user->isPasswordValid($password)) {
            return $this->response(self::INVALID_USER);
        }

        return $this->logIn($user);
    }

    public function getAccessToken($user, $deviceType, $deviceId, $clientIP)
    {
        // @TODO device_id is required when device_type != AccessToken::DEVICE_TYPE_WEB
        return AccessToken::createForUser(
            $user,
            $deviceType,
            $deviceId,
            $clientIP
        );
    }

    /**
     * @param int   $code
     * @param mixed $data
     *
     * @return array
     */
    private function response($code, $data = null)
    {
        return [
            'code' => $code,
            'data' => $data,
        ];
    }
}
