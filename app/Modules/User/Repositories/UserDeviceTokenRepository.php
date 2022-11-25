<?php

namespace App\Modules\User\Repositories;

use App\Core\AbstractRepository;
use App\Modules\User\Models\UserDeviceToken;
use Illuminate\Container\Container;

/**
 * User repository class
 */
class UserDeviceTokenRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param UserDeviceToken $token
     */
    public function __construct(Container $app, UserDeviceToken $token)
    {
        parent::__construct($app, $token);
    }

    /**
     * Find UserDeviceToken record by oauth token id
     *
     * @param $tokenId
     * @return UserDeviceToken
     */
    public function findSoftByTokenId($tokenId)
    {
        return $this->model->where('oauth_access_token_id', $tokenId)->first();
    }

    /**
     * Find first user device token by user id
     *
     * @param int $userId
     * @return UserDeviceToken
     */
    public function findSoftByUserId($userId)
    {
        return $this->model->where('user_id', $userId)->orderByDesc('updated_at')->first();
    }

    /**
     * Delete user device tokens for given Oauth Token id
     *
     * @param string $accessTokenId
     */
    public function deleteByAccessTokenId($accessTokenId)
    {
        $devTokens = $this->model->where(
            'oauth_access_token_id',
            $accessTokenId
        )->get();

        foreach ($devTokens as $devToken) {
            $devToken->delete();
        }
    }
}
