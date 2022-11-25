<?php

namespace App\Modules\User\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Core\User;

/**
 * @property string device_token
 * @property string device_type
 * @property int    oauth_access_token_id
 * @property int    user_id
 */
class UserDeviceToken extends LogModel
{
    use TableFixTrait;

    protected $fillable = [
        'user_id',
        'device_type',
        'device_token',
        'device_token_type',
        'oauth_access_token_id',
    ];

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
    }

    //region Accessors

    /**
     * Get related person_id or user_id for logging in history
     *
     * @return array
     */
    public function getHistoryRelatedRecord()
    {
        if ($this->user_id) {
            /** @var User $user */
            $user = User::find($this->user_id);

            if ($user && $user->getPersonId()) {
                return ['person', $user->getPersonId()];
            }

            return ['users', $this->user_id];
        }

        return null;
    }

    /**
     * Get user_id data
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * Get device_type data
     *
     * @return string
     */
    public function getDeviceType()
    {
        return $this->device_type;
    }

    /**
     * Get device_token data
     *
     * @return string
     */
    public function getDeviceToken()
    {
        return $this->device_token;
    }

    /**
     * Get device_token data
     *
     * @return string
     */
    public function getDeviceTokenType()
    {
        return $this->device_token_type;
    }
    
    /**
     * Get oauth_access_token_id data
     *
     * @return int
     */
    public function getOauthAccessTokenId()
    {
        return $this->oauth_access_token_id;
    }

    //endregion
}
