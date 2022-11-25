<?php

namespace App\Modules\User\Providers;

use App\Core\Trans;
use App\Modules\MobileAuth\Repositories\UserDeviceRepository;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class UserDeviceServiceProvider extends ServiceProvider
{
    /** @var App\Modules\MobileAuth\Models\UserDevice */
    private $takenDevice;

    public function boot()
    {
        Validator::extend('unique_device_number', function ($attribute, $value, $parameters, $validator) {
            $deviceId = 0;

            if (count($parameters) > 0) {
                $deviceId = $parameters[0];
            }

            $repository = App::make(UserDeviceRepository::class);

            $this->takenDevice = $repository->findByNumberSoft($value)->first();

            return !($this->takenDevice && ($this->takenDevice->getId() != $deviceId));
        });

        Validator::replacer('unique_device_number', function ($message, $attribute, $rule, $parameters) {
            $trans = App::make(Trans::class);

            $user = $this->takenDevice->getUser();

            $person = $user ? $user->getPerson() : null;

            $name = $person ? $person->getName() : $user->getUsername();

            $url = $person
                ? ('/#/person/' . $person->getId() . '/view')
                : ('/#/user/' . $user->getId() . '/view');

            return $trans->get($message, [
                'user' => "<a href='$url' target='_blank'>$name<a/>",
            ]);
        });
    }

    /**
     * Register the User Device module service provider.
     *
     * @return void
     */
    public function register()
    {
    }
}
