<?php

namespace App\Modules\User\Services;

use App\Core\User;
use App\Modules\User\Models\UserDeviceToken;
use App\Modules\User\Repositories\UserDeviceTokenRepository;
use Illuminate\Support\Facades\Auth;

class UserDeviceTokenService
{
    /**
     * @var UserDeviceTokenRepository
     */
    private $tokenRepo;

    /**
     * Initialize class
     *
     * @param UserDeviceTokenRepository $tokenRepo
     */
    public function __construct(
        UserDeviceTokenRepository $tokenRepo
    ) {
        $this->tokenRepo = $tokenRepo;
    }

    /**
     * Add or update device_token and device_type for current OAuth token
     *
     * @param        $deviceType
     * @param        $deviceToken
     *
     * @param        $accessToken
     * @param string $deviceTokenType
     *
     * @return UserDeviceToken
     */
    public function add($deviceType, $deviceToken, $accessToken, $deviceTokenType = '')
    {
        /** @var User $user */
        $user = Auth::user();

        // get token record
        $tokenRecord =
            $this->tokenRepo->findSoftByTokenId($accessToken->getId());

        if ($tokenRecord) {
            // user device token exists, update it
            $data = $this->tokenRepo->updateWithIdAndInput(
                $tokenRecord->getId(),
                [
                    'user_id'               => $user->getId(),
                    'device_type'           => $deviceType,
                    'device_token'          => $deviceToken,
                    'device_token_type'     => $deviceTokenType,
                    'oauth_access_token_id' => $accessToken->getId(), // update also access token just in case
                ]
            );
        } else {
            // user device token does not exists, create new
            $data = $this->tokenRepo->create([
                'user_id'               => $user->getId(),
                'device_type'           => $deviceType,
                'device_token'          => $deviceToken,
                'device_token_type'     => $deviceTokenType,
                'oauth_access_token_id' => $accessToken->getId(),
            ]);
        }

        return $data;
    }
}
