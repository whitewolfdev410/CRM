<?php

namespace App\Http\Middleware;

use App\Core\Oauth2\AccessToken;
use App\Core\User;
use Closure;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class Authenticate
{
    /**
     * The authentication factory instance.
     *
     * @var Auth
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  Auth  $auth
     *
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     *
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $cookies = $request->cookies->all();

        try {
            if (Arr::has($cookies, 'user_session')) {
                $token = AccessToken::validateByAuthorizationHeader(
                    "Bearer ".json_decode($cookies['user_session'])->accessToken
                );
            } else {
                $token = AccessToken::validateByAuthorizationHeader(
                    $request->header('Authorization')
                );
            }
        } catch (Exception $e) {
            $apiToken = $request->get('api_token');
            if ($apiToken) {
                $token = AccessToken::validateByAuthorizationHeader("Bearer ".$apiToken);
            } else {
                throw $e;
            }
        }

        /** @var User $user */
        $user = $token->getUser();

        $user->setAccessToken($token);
        Auth::login($user);

        // Handle client portal user
        $user->isPortalAdmin = $user->hasPermissions(['client_portal.admin']) ? 1 : 0;
        if ($user->isPortalAdmin) { // If it's client portal admin, try to retrieve the selected customer
            $customer = (int)$request->header('Customer', 0);
            if ($customer) {
                $user->setCompanyPersonId($customer);
            }
        }

        return $next($request);
    }
}
