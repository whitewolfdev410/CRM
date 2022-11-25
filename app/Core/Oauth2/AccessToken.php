<?php

namespace App\Core\Oauth2;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\AccessTokenCannotGenerateException;
use App\Core\Exceptions\AccessTokenExpiredException;
use App\Core\Exceptions\AccessTokenInvalidFormatException;
use App\Core\Exceptions\AccessTokenMissingException;
use App\Core\Exceptions\AccessTokenAuthorizationException;
use App\Core\User;
use BadMethodCallException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use RuntimeException;

/**
 * Access token class
 *
 * @property string      device_id
 * @property AccessToken device_token
 * @property string      device_type
 * @property int         expire_time
 * @property int         id
 * @property string      ip_address
 * @property User        user
 * @property int         user_id
 */
class AccessToken extends Model
{
    // Device types
    const DEVICE_TYPE_WEB = 'web';
    const DEVICE_TYPE_IOS = 'ios';
    const DEVICE_TYPE_ANDROID = 'android';
    const DEVICE_TYPE_TEST = 'test';
    const DEVICE_TYPE_MOBILE = 'mobile';

    // Token type
    const TOKEN_TYPE = 'Bearer';

    // Token expiration time
    const EXPIRES_IN_MONTH = 2592000; // 60sec * 60min * 24h * 30days

    // Token mobile expiration time
    const EXPIRES_MOBILE = 316224000; // 60sec * 60 min * 24h * 366 days * 10 years

    /**
     * Related table
     *
     * @var string
     */
    protected $table = 'oauth_access_tokens';

    /**
     * Disable incrementing due to string key
     *
     * @var bool
     */
    public $incrementing = false;

    // Relationships

    /**
     * Many-to-one relation with Country
     *
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // getters / setters

    /**
     * Returns id aka Access token hash
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets ID or generates new one if nothing passed
     *
     * @param int $id
     *
     * @return AccessToken
     *
     * @throws Exception
     */
    public function setId($id = null)
    {
        $this->id = ($id !== null) ? $id : $this->generateId();

        return $this;
    }

    /**
     * Sets user
     *
     * @param User $user
     *
     * @return self
     */
    public function setUser(User $user)
    {
        $this->user_id = $user->getId();

        return $this;
    }

    /**
     * Return related user
     *
     * @return User
     *
     * @throws BadMethodCallException
     */
    public function getUser()
    {
        $cacheId = 'auth_param_' . $this->getUserId();
        $env = \App::environment();
        $cachingEnabled = $this->isUserCachingEnabled();

        // get from cache if caching enabled
        if ($cachingEnabled && Cache::tags($env . '_users')->has($cacheId)) {
            return Cache::tags($env . '_users')->get($cacheId);
        }

        $user = $this->user;
        $user->load('roles.perms');
        $data = $user;

        // add to cache if caching enabled
        if ($cachingEnabled) {
            Cache::tags($env . '_users')
                ->put($cacheId, $data, $this->getUserCacheExpireTime());
        }

        return $data;
    }

    /**
     * Return related user ID
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * Returns expire time
     *
     * @return int
     */
    public function getExpireTime()
    {
        return $this->expire_time;
    }

    /**
     * Returns cache expire time (in seconds)
     *
     * @return int
     */
    private function getAccessTokenCacheExpireTime()
    {
        // token expiration time in minutes
        $expireTime = $this->getExpireTime() - time();

        // maximum expiration time from config
        $maxExpireTime = $this->getAccessTokenConfigExpirationTime();

        return ($expireTime > $maxExpireTime) ? $maxExpireTime : $expireTime;
    }

    /**
     * Returns user cache expire time (in seconds)
     *
     * @return int
     */
    private function getUserCacheExpireTime()
    {
        // token expiration time in seconds
        $expireTime = $this->getExpireTime() - time();

        // maximum expiration time from config
        $maxExpireTime = $this->getUserConfigExpirationTime();

        return ($expireTime > $maxExpireTime) ? $maxExpireTime : $expireTime;
    }

    /**
     * Sets token expiration time
     *
     * @param int $time = null unix timestamp
     *
     * @return self
     */
    public function setExpireTime($time = null)
    {
        $this->expire_time = ($time !== null) ? $time
            : (time() + self::EXPIRES_IN_MONTH);

        return $this;
    }

    /**
     * Checks if current access token is expired
     *
     * @return bool
     */
    public function isExpired()
    {
        return ((time() - $this->expire_time) > 0);
    }

    /**
     * Checks if current access token is valid
     *
     * @return bool
     */
    public function isValid()
    {
        return !$this->isExpired();
    }

    /**
     * Returns device type
     *
     * @return string
     */
    public function getDeviceType()
    {
        return $this->device_type;
    }

    /**
     * Sets device type
     * check AccessToken::DEVICE_TYPE_* consts for options
     *
     * @param string $deviceType
     *
     * @return self
     */
    public function setDeviceType($deviceType)
    {
        $this->device_type = $deviceType;

        return $this;
    }

    /**
     * Returns device ID
     *
     * @return string
     */
    public function getDeviceId()
    {
        return $this->device_id;
    }

    /**
     * Sets device ID, e.g. serial number, imei
     *
     * @param string $deviceId
     *
     * @return self
     */
    public function setDeviceId($deviceId)
    {
        $this->device_id = $deviceId;

        return $this;
    }

    /**
     * Return device token
     *
     * @return string
     */
    public function getDeviceToken()
    {
        return $this->device_token;
    }

    /**
     * Sets device token, e.g. Android GCM token, APPLE push notification token
     *
     * @param  string $deviceToken
     *
     * @return self
     */
    public function setDeviceToken($deviceToken)
    {
        $this->device_token = $deviceToken;

        return $this;
    }

    /**
     * Returns IP address
     *
     * @return string
     */
    public function getIpAddress()
    {
        return $this->ip_address;
    }

    /**
     * Sets IP address
     *
     * @param string $ipAddress
     *
     * @return self
     */
    public function setIpAddress($ipAddress)
    {
        $this->ip_address = $ipAddress;

        return $this;
    }

    // others

    /**
     * Sets test id
     *
     * @return self
     *
     * @throws Exception
     */
    public function setTestId()
    {
        $this->id
            =
            'TEST_' . $this->generateId(35); // default hash length is 40 chars

        return $this;
    }

    /**
     * Generates new access token
     *
     * @param int $length = 40
     *
     * @return string
     * @throws Exception on error
     */
    private function generateId($length = 40)
    {
        $stripped = '';

        do {
            $bytes = openssl_random_pseudo_bytes($length, $strong);
            // We want to stop execution if the key fails because, well, that is bad.
            if ($bytes === false || $strong === false) {
                throw App::make(AccessTokenCannotGenerateException::class);
            }
            $stripped .= str_replace(
                ['/', '+', '='],
                '',
                base64_encode($bytes)
            );
        } while (strlen($stripped) < $length);

        return substr($stripped, 0, $length);
    }

    /**
     * Creates (and saves) new access token for given user
     *
     * @param \App\Core\User $user
     * @param string         $deviceType
     * @param string         $deviceId
     * @param string         $ipAddress
     *
     * @return AccessToken
     *
     * @throws Exception
     */
    public static function createForUser(
        User $user,
        $deviceType,
        $deviceId,
        $ipAddress
    ) {
        // delete old tokens due to possibility of having multiple for one device, especially for web clients
        self::where('expire_time', '<', time())
            ->delete();

        $expireTime = null; // default will be used nor non-mobile devices

        /* for mobile devices we allow only one token so we need to remove any
           existing mobile tokens for this user that are mobile type
        */
        if ($deviceType === self::DEVICE_TYPE_MOBILE) {
            $oldTokens = self::where('user_id', $user->getId())
                ->where('device_type', $deviceType)->get();
            // delete in loop to remove cache and launch deleted event
            foreach ($oldTokens as $oldToken) {
                $oldToken->delete();
                Cache::forget(self::getTokenCacheId($oldToken->getId()));
            }

            $expireTime = time() + self::EXPIRES_MOBILE;
        }

        $token = new self();
        $token
            ->setId()
            ->setUser($user)
            ->setExpireTime($expireTime)
            ->setDeviceType($deviceType)
            ->setDeviceId($deviceId)
            ->setIpAddress($ipAddress)
            ->save();

        return $token;
    }

    /**
     * Creates (or returns) access token for testing
     *
     * @return AccessToken
     *
     * @throws Exception
     * @throws RuntimeException
     */
    public static function createForTesting()
    {
        $user = User::where('email', User::TEST_USER_EMAIL)
            ->first();

        if (!$user) {
            throw new RuntimeException('Missing test user! Please create it by running `php artisan module:seed Users` !');
        }

        $validTimestamp = time() + self::EXPIRES_IN_MONTH;

        $token = self::where('user_id', $user->getId())
            ->where('id', 'LIKE', 'TEST_%')
            ->where('expire_time', '<', $validTimestamp)
            ->first();

        if (!$token) {
            $token = new self();
            $token
                ->setTestId()
                ->setUser($user)
                ->setExpireTime()
                ->setDeviceType(self::DEVICE_TYPE_TEST)
                ->setDeviceId('')
                ->setIpAddress('127.0.0.1')
                ->save();
        }

        return $token;
    }

    /**
     * Returns access token based on given authorization header or throws
     * validation errors
     *
     * @param string $authorizationHeader e.g. Bearer
     *                                    wi6GMbmW4NgcFFqUk28qnwZScK4aKAHx2eMjnI54
     *
     * @return AccessToken
     *
     * @throws Exception
     */
    public static function validateByAuthorizationHeader($authorizationHeader)
    {
        if (!$authorizationHeader) {
            throw App::make(AccessTokenMissingException::class);
        }

        $header = explode(' ', $authorizationHeader);
        if (count($header) !== 2 || $header[0] !== self::TOKEN_TYPE
            || !$header[1]
        ) {
            throw App::make(AccessTokenInvalidFormatException::class);
        }

        $token = self::getTokenData($header[1]);

        if (!$token) {
            throw App::make(AccessTokenAuthorizationException::class);
        }

        if ($token->isExpired()) {
            throw App::make(AccessTokenExpiredException::class);
        }

        return $token;
    }

    /**
     * Get access token based on $token
     *
     * @param string $token
     *
     * @return AccessToken
     */
    protected static function getTokenData($token)
    {
        $cacheId = self::getTokenCacheId($token);
        $cachingEnabled = self::isAccessTokenCachingEnabled();

        // get from cache if caching enabled
        if ($cachingEnabled && Cache::has($cacheId)) {
            return Cache::get($cacheId);
        }

        /** @var AccessToken $data */
        $data = self::find($token);

        // add to cache if caching enabled
        if ($data && $cachingEnabled) {
            Cache::put($cacheId, $data, $data->getAccessTokenCacheExpireTime());
        }

        return $data;
    }

    /**
     * Get token cache id
     *
     * @param $token
     *
     * @return string
     */
    protected static function getTokenCacheId($token)
    {
        return 'auth_' . $token;
    }

    /**
     * Removes access token based on given authorization header
     *
     * @param string $authorizationHeader e.g. Bearer
     *                                    wi6GMbmW4NgcFFqUk28qnwZScK4aKAHx2eMjnI54
     *
     * @return bool true when token was removed, false if not found
     */
    public static function deleteByAuthorizationHeader($authorizationHeader)
    {
        $accessTokenHash = trim(str_replace(
            self::TOKEN_TYPE,
            '',
            $authorizationHeader
        ));
        if (!$accessTokenHash) {
            return false;
        }

        $token = self::find($accessTokenHash);
        if (!$token) {
            return false;
        }

        $token->delete();
        Cache::forget(self::getTokenCacheId($accessTokenHash));

        return true;
    }

    /**
     * Removes access token based on given device number/imei and user id
     *
     * @param string $deviceNumber Mobile device number
     * @param int    $userId       User id
     * @param string $deviceImei   Device imei if any
     *
     * @return bool true when token was removed, false if not found
     *
     * @throws Exception
     */
    public static function deleteByDeviceNumber($deviceNumber, $userId, $deviceImei = null)
    {
        /** @var AccessToken $token */
        $token = self
            ::where('device_type', self::DEVICE_TYPE_MOBILE)
            ->where(function ($query) use ($deviceNumber, $deviceImei) {
                /** @var Builder|AccessToken $query */
                $query = $query
                    ->where('device_id', $deviceNumber);

                if ($deviceImei) {
                    $query = $query->orWhere('device_id', "imei:$deviceImei");
                }

                return $query;
            })
            ->where('user_id', $userId);

        $token = $token->first();

        if (!$token) {
            return false;
        }
        $accessTokenHash = $token->getId();

        $token->delete();
        Cache::forget(self::getTokenCacheId($accessTokenHash));

        return true;
    }

    /**
     * Checks whether current device_type is web
     *
     * @return bool
     */
    public function isForWeb()
    {
        return $this->device_type === self::DEVICE_TYPE_WEB;
    }

    /**
     * Checks whether current device_type is mobile
     *
     * @return bool
     */
    public function isMobile()
    {
        return $this->device_type === self::DEVICE_TYPE_MOBILE;
    }

    /**
     * Verify if access token caching is enabled
     *
     * @return bool
     */
    protected static function isAccessTokenCachingEnabled()
    {
        return (config('cache.expiration_time.access_token') !== false);
    }

    /**
     * Verify if user caching is enabled
     *
     * @return bool
     */
    protected function isUserCachingEnabled()
    {
        return (config('cache.expiration_time.user') !== false);
    }

    /**
     * Get access token expiration time from config
     *
     * @return int|false
     */
    protected function getAccessTokenConfigExpirationTime()
    {
        return config('cache.expiration_time.access_token');
    }

    /**
     * Get user expiration time from config
     *
     * @return int|false
     */
    protected function getUserConfigExpirationTime()
    {
        return config('cache.expiration_time.user');
    }
}
