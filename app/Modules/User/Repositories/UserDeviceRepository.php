<?php

namespace App\Modules\User\Repositories;

use App\Core\AbstractRepository;
use App\Modules\MobileAuth\Models\UserDevice;
use App\Modules\User\Models\UserDeviceToken;
use Illuminate\Container\Container;

/**
 * User repository class
 */
class UserDeviceRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [];

    /**
     * Repository constructor
     *
     * @param Container  $app
     * @param UserDevice $userDevice
     */
    public function __construct(Container $app, UserDevice $userDevice)
    {
        parent::__construct($app, $userDevice);
    }

    /**
     * Get device phone number by personId
     *
     * @param $personId
     *
     * @return UserDeviceToken
     */
    public function getPhoneNumberByPersonId($personId)
    {
        $userDevice = $this->model
            ->select(['user_devices.number'])
            ->join('users', 'users.id', '=', 'user_devices.user_id')
            ->where('user_devices.active', '=', 1)
            ->where('users.person_id', $personId)
            ->orderByDesc('user_devices.updated_at')
            ->first();
        
        if ($userDevice) {
            return $userDevice->number;
        }
        
        return null;
    }
}
