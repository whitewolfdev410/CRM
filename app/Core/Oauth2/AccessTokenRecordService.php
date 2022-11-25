<?php

namespace App\Core\Oauth2;

use App\Modules\User\Repositories\UserDeviceTokenRepository;
use Illuminate\Contracts\Container\Container;

class AccessTokenRecordService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var UserDeviceTokenRepository
     */
    protected $tokenRepo;

    /**
     * Initialize class
     *
     * @param Container $app
     * @param UserDeviceTokenRepository $tokenRepo
     */
    public function __construct(
        Container $app,
        UserDeviceTokenRepository $tokenRepo
    ) {
        $this->app = $app;
        $this->tokenRepo = $tokenRepo;
    }

    /**
     * Actions that should be launched after deleting access token
     *
     * @param AccessToken $accessToken
     */
    public function deleted(AccessToken $accessToken)
    {
        $this->tokenRepo->deleteByAccessTokenId($accessToken->getId());
    }
}
