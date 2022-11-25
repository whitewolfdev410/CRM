<?php

namespace App\Modules\User\Services;

use App\Core\User;
use App\Modules\EmailTemplate\Providers\AuthFieldsProvider;
use App\Modules\EmailTemplate\Providers\OrganizationFieldsProvider;
use App\Modules\EmailTemplate\Providers\UserFieldsProvider;
use App\Modules\EmailTemplate\Services\EmailTemplateService;
use App\Modules\User\Repositories\UserRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class UserService
{
    /**
     * @var Container
     */
    private $app;

    /**
     * @var EmailTemplateService
     */
    private $templateService;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * Initialize class
     *
     * @param Container            $app
     * @param UserRepository       $userRepository
     * @param EmailTemplateService $templateService
     */
    public function __construct(
        Container $app,
        UserRepository $userRepository,
        EmailTemplateService $templateService
    ) {
        $this->app = $app;
        $this->templateService = $templateService;
        $this->userRepository = $userRepository;
    }

    /**
     * Create a new user
     *
     * @param array $input
     *
     * @return array
     *
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function create(array $input)
    {
        $generatedPassword = self::generatePassword($input);

        /** @var User $user */
        /** @var User $user */
        list($user, $roles) = $result = $this->userRepository->create($input);

        if ($generatedPassword || !empty($input['send_email'])) {
            $this->sendPasswordEmail($generatedPassword, $user, $input);
        }

        if ($generatedPassword) {
            $user['generated_password'] = $generatedPassword;
        }

        return $result;
    }

    /**
     * Update a user with new data
     *
     * @param       $id
     * @param array $input
     *
     * @return array|\Illuminate\Database\Eloquent\Model
     *
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function update($id, array $input)
    {
        $generatedPassword = self::generatePassword($input);

        if (!$generatedPassword) {
            $input['expire_at'] = null;
            $input['is_password_temporary'] = false;
        }

        /** @var User $user */
        list($user, $roles) = $result = $this->userRepository->updateWithIdAndInput($id, $input);

        if ($generatedPassword || !empty($input['send_email'])) {
            $this->sendPasswordEmail($generatedPassword, $user, $input);
        }

        if ($generatedPassword) {
            $user['generated_password'] = $generatedPassword;
        }

        return $result;
    }

    /**
     * Clear user failed login attempts and lock
     *
     * @param User $user
     *
     * @return boolean
     */
    public function clearFailedLoginAttempts($user)
    {
        $this->setFailedAttempts($user, 0);
        $this->setLastFailedAttemptAt($user, null);
        $this->setLockedAt($user, null);

        if ($user) {
            $user->save();

            return true;
        }

        return false;
    }

    /**
     * Generate direct login token based on user id
     *
     * @param int $id
     *
     * @return string
     *
     * @throws ModelNotFoundException
     */
    public function generateDirectLoginById($id)
    {
        /** @var User $user */
        $user = $this->userRepository->find($id);

        if ($user) {
            $token = Str::random(32);

            $user->setDirectLoginToken($token);
            $user->save();

            return $token;
        }

        return null;
    }

    /**
     * Handle user failed login attempt
     *
     * @param User $user
     *
     * @return boolean
     */
    public function handleLoginFail($user)
    {
        $now = Carbon::now();

        $last = $this->getLastFailedAttemptAt($user);

        $this->setLastFailedAttemptAt($user, $now);

        if (!$last || $now->diffInSeconds($last) > config('auth.failedAttemptInterval', 30)
        ) {
            $attempts = 1;
        } else {
            $attempts = $this->getFailedAttempts($user) + 1;
        }

        $this->setFailedAttempts($user, $attempts);

        if ($attempts >= config('auth.maxAllowedFailedAttempts', 10)) {
            $this->setLockedAt($user, $now);
        }

        if ($user) {
            $user->save();

            return true;
        }

        return false;
    }

    /**
     * Get how much time left that the user is locked
     *
     * @param User $user
     *
     * @return int
     */
    public function getLockTimeLeft($user)
    {
        $now = Carbon::now();
        $lockTime = config('auth.lockTime', 15 * 60);

        $lockedAt = $this->getLockedAt($user);
        if ($lockedAt) {
            $lockedTime = $now->diffInSeconds($lockedAt);
            if ($lockedTime < $lockTime) {
                return $lockTime - $lockedTime;
            }
        }

        return 0;
    }

    /**
     * Get failed login attempts
     *
     * @param User $user
     *
     * @return int
     */
    public function getFailedAttempts($user)
    {
        return max((int)session('failed_attempts', 0), $user ? $user->getFailedAttempts() : 0, 0);
    }

    /**
     * Set failed login attempts
     *
     * @param User $user
     * @param int  $value
     */
    public function setFailedAttempts($user, $value)
    {
        if ($user) {
            $user->setFailedAttempts($value);
        }

        session(['failed_attempts' => $value]);
    }

    /**
     * Get last failed login attempt
     *
     * @param User $user
     *
     * @return Carbon|string
     */
    public function getLastFailedAttemptAt($user)
    {
        /** @var Carbon $sessionValue */
        $sessionValue = session('last_failed_attempt_at', null);
        if ($sessionValue) {
            $sessionValue = Carbon::parse($sessionValue);
        }

        $userValue = $user ? $user->getLastFailedAttemptAt() : null;

        if (!$sessionValue && !$userValue) {
            return null;
        }

        if ($sessionValue && $userValue) {
            return $sessionValue->gt($userValue) ? $sessionValue : $userValue;
        }

        return $sessionValue ?: $userValue;
    }

    /**
     * Set last failed login attempt
     *
     * @param User          $user
     * @param Carbon|string $value
     */
    public function setLastFailedAttemptAt($user, $value)
    {
        if ($user) {
            $user->setLastFailedAttemptAt($value);
        }

        session(['last_failed_attempt_at' => $value]);
    }

    /**
     * Get lock time
     *
     * @param User $user
     *
     * @return Carbon|string
     */
    public function getLockedAt($user)
    {
        /** @var Carbon $sessionValue */
        $sessionValue = session('locked_at', null);
        if ($sessionValue) {
            $sessionValue = Carbon::parse($sessionValue);
        }

        $userValue = $user ? $user->getLockedAt() : null;

        if (!$sessionValue && !$userValue) {
            return null;
        }

        if ($sessionValue && $userValue) {
            return $sessionValue->gt($userValue) ? $sessionValue : $userValue;
        }

        return $sessionValue ?: $userValue;
    }

    /**
     * Set lock time
     *
     * @param User          $user
     *
     * @param Carbon|string $value
     */
    public function setLockedAt($user, $value)
    {
        if ($user) {
            $user->setLockedAt($value);
        }

        session(['locked_at' => $value]);
    }

    /**
     * Generate new password
     *
     * @param array $input
     *
     * @return string
     */
    private static function generatePassword(array &$input)
    {
        if (array_key_exists('auto_generated', $input)
            && ($input['auto_generated'] == 1)
        ) {
            $input['password'] = Str::random(8);
            $input['expire_at'] = new Carbon();
            $input['expire_at']->addDays(config('auth.generated.expire', 7));
            $input['is_password_temporary'] = true;

            return $input['password'];
        }

        return null;
    }

    /**
     * Send password by email
     *
     * @param string|null $generatedPassword
     * @param User   $user
     *
     * @throws ModelNotFoundException
     */
    private function sendPasswordEmail($generatedPassword, $user, $input)
    {
        if ($generatedPassword) {
            $this->templateService->sendTemplate(
                'kb::ack_request',
                $this->templateService->mergeByTemplateId(
                    config('auth.generated.email'),
                    'en-US',
                    [
                        'auth_generated_password' => $generatedPassword,
                    ],
                    [
                        AuthFieldsProvider::NAME         => new AuthFieldsProvider($this->app, $user),
                        OrganizationFieldsProvider::NAME => new OrganizationFieldsProvider(),
                        UserFieldsProvider::RECIPIENT    => new UserFieldsProvider($user),
                    ]
                ),
                $user->getEmailForPasswordReset()
            );
        } else {
            $this->templateService->sendTemplate(
                'kb::ack_request',
                $this->templateService->mergeByTemplateId(
                    'user.account',
                    'en-US',
                    [
                        'auth_generated_password' => $input['password'],
                    ],
                    [
                        AuthFieldsProvider::NAME         => new AuthFieldsProvider($this->app, $user),
                        OrganizationFieldsProvider::NAME => new OrganizationFieldsProvider(),
                        UserFieldsProvider::RECIPIENT    => new UserFieldsProvider($user),
                    ]
                ),
                $user->getEmailForPasswordReset()
            );
        }
    }
}
