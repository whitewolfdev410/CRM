<?php

namespace App\Core;

use App\Core\Oauth2\AccessToken;
use App\Core\Rbac\HasRoleTrait as HasRole;
use App\Modules\MobileAuth\Models\UserDevice;
use App\Modules\Person\Models\Person;
use BadMethodCallException;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use InvalidArgumentException;
use RuntimeException;

/**
 * @property int           company_person_id
 * @property string        direct_login_token
 * @property string        email
 * @property Carbon|string expire_at
 * @property int           failed_attempts
 * @property mixed         gui_settings
 * @property int           id
 * @property int           is_password_temporary
 * @property Carbon|string last_failed_attempt_at
 * @property Carbon|string last_login_at
 * @property string        locale
 * @property Carbon|string locked_at
 * @property string        password
 * @property Person        person
 * @property int           person_id
 */
class User extends LogModel implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword, HasRole;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'person_id',
        'email',
        'password',
        'gui_settings',
        'expire_at',
        'is_password_temporary',
        'failed_attempts',
        'last_failed_attempt_at',
        'locked_at',
    ];

    const TEST_USER_EMAIL = 'testing@friendly-solutions.com';
    const TEST_USER_DIRECT_LOGIN_TOKEN = 'CLcgDLpbA2kBw6uHYPmCNNXuKbRKnrsn';

    /**
     * User access token
     *
     * @var AccessToken
     */
    private $accessToken;
    
    public $isPortalAdmin = false;
    public $portalCustomer;

    //region relationships

    /**
     * One-to-One relation with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /**
     * One user may have assign multiple devices
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }

    //endregion

    //region accessors

    /**
     * Returns user ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns user email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Returns username
     *
     * @return string
     */
    public function getUsername()
    {
        $person = $this->person;
        if ($person) {
            return $person->getName();
        }

        return $this->email;
    }

    /**
     * Returns Twilio number value
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function getTwilioNumber()
    {
        $number = null;
        $person = $this->person;
        if ($person) {
            $twilioNumber = $person->getTwilioNumber();
            if ($twilioNumber) {
                $number = $twilioNumber->value;
            }
        }

        return $number;
    }

    /**
     * Returns ID of related person
     *
     * @return int
     */
    public function getPersonId()
    {
        return (int)$this->person_id;
    }

    /**
     * Returns related person
     *
     * @return Person
     */
    public function getPerson()
    {
        return $this->person;
    }

    /**
     * Return gui_settings data
     *
     * @return mixed
     */
    public function getGuiSettings()
    {
        return $this->gui_settings;
    }

    /**
     * Sets new password
     *
     * @param string $password
     *
     * @return self
     *
     * @throws RuntimeException
     */
    public function setPassword($password)
    {
        $this->password = Hash::make($password);

        return $this;
    }

    /**
     * Checks if given password is valid
     *
     * @param string $password
     *
     * @return bool
     *
     * @throws BadMethodCallException
     * @throws RuntimeException
     */
    public function isPasswordValid($password)
    {
        if (Hash::check($password, $this->password)) {
            return true;
        }

        $person = $this->person;
        if ($person && $person->getPassword() === md5($password)) {
            // updating user password to hash version
            $this->setPassword($password);
            $this->save();

            // clearing stored md5 password and login in person
            // $person->setLogin('');
            // $person->setPassword('');
            // $person->save();

            return true;
        }

        return false;
    }

    /**
     * Set user access token
     *
     * @param AccessToken $accessToken
     */
    public function setAccessToken(AccessToken $accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * Get user access token
     *
     * @return AccessToken|null
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Get user expiry date
     *
     * @return Carbon
     */
    public function getExpireAt()
    {
        return (!$this->expire_at || ($this->expire_at === '0000-00-00 00:00:00')) ?
            null :
            Carbon::parse($this->expire_at);
    }

    /**
     * Get whether user's password is temporary
     *
     * @return boolean|int
     */
    public function isPasswordTemporary()
    {
        return $this->is_password_temporary;
    }

    /**
     * Get user's failed login attempts
     *
     * @return int
     */
    public function getFailedAttempts()
    {
        return (int)($this->failed_attempts ?: 0);
    }

    /**
     * Set user's failed login attempts
     *
     * @param int $value
     */
    public function setFailedAttempts($value)
    {
        $this->failed_attempts = $value;
    }

    /**
     * Get last user's failed login attempt
     *
     * @return Carbon|string
     */
    public function getLastFailedAttemptAt()
    {
        return (!$this->last_failed_attempt_at || ($this->last_failed_attempt_at === '0000-00-00 00:00:00')) ?
            null :
            Carbon::parse($this->last_failed_attempt_at);
    }

    /**
     * Set last user's failed login attempt
     *
     * @param Carbon|string $value
     */
    public function setLastFailedAttemptAt($value)
    {
        $this->last_failed_attempt_at = $value;
    }

    /**
     * Get user's locale
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale ?: 'en-US';
    }

    /**
     * Get user's lock time
     *
     * @return Carbon|string
     */
    public function getLockedAt()
    {
        return (!$this->locked_at || ($this->locked_at === '0000-00-00 00:00:00')) ?
            null :
            Carbon::parse($this->locked_at);
    }

    /**
     * Set user's lock time
     *
     * @param Carbon|string $value
     */
    public function setLockedAt($value)
    {
        $this->locked_at = $value;
    }

    /**
     * Get user's last login time
     *
     * @return Carbon|string
     */
    public function getLastLoginAt()
    {
        return (!$this->last_login_at || ($this->last_login_at === '0000-00-00 00:00:00')) ?
            null :
            Carbon::parse($this->last_login_at);
    }

    /**
     * Set user's last login time
     *
     * @param Carbon|string $value
     */
    public function setLastLoginAt($value)
    {
        $this->last_login_at = $value;
    }

    /**
     * Return person id of user's company
     *
     * @return integer
     */
    public function getCompanyPersonId()
    {
        return $this->company_person_id;
    }

    /**
     * Set person id of user's company
     *
     * @param int $value
     *
     * @return $this
     */
    public function setCompanyPersonId($value)
    {
        $this->company_person_id = $value;

        return $this;
    }

    /**
     * Gets of user's direct_login_token
     *
     * @return string
     */
    public function getDirectLoginToken()
    {
        return $this->direct_login_token;
    }

    /**
     * Sets user's direct_login_token
     *
     * @param string $value
     */
    public function setDirectLoginToken($value)
    {
        $this->direct_login_token = $value;
    }

    /**
     * Accessor for getting gui_settings property - decodes it before getting
     *
     * @param string $value
     *
     * @return mixed
     */
    public function getGuiSettingsAttribute($value)
    {
        return json_decode($value, true);
    }

    /**
     * Mutator for setting gui_settings property - encodes it before saving
     *
     * @param string $value
     *
     * @return $this
     */
    public function setGuiSettingsAttribute($value)
    {
        $this->attributes['gui_settings'] = json_encode($value);

        return $this;
    }

    //endregion

    /**
     * {@inheritdoc}
     *
     * @throws BadMethodCallException
     */
    public function save(array $options = [])
    {
        $return = parent::save($options);

        // clear user auth cache
        $cacheId = 'auth_param_' . $this->getId();
        $env = \App::environment();
        Cache::tags($env . '_users')->forget($cacheId);

        return $return;
    }

    /**
     * Get user scopes
     *
     * @return array - list of scopes
     */
    public function getScopes()
    {
        $scopes = [];
        foreach ($this->roles as $role) {
            foreach ($role->perms as $permission) {
                $scopes[] = $permission->name;
            }
        }

        return $scopes;
    }
}
