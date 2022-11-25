<?php

namespace App\Core\Extended;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\JsonEncodingException;
use App\Core\User;
use App\Modules\User\Repositories\UserDeviceTokenRepository;
use Illuminate\Support\Facades\Auth;

class JsonResponse extends \Illuminate\Http\JsonResponse
{
    /**
     * {@inheritdoc}
     */
    public function __construct(
        $data = null,
        $status = 200,
        $headers = [],
        $options = 0
    ) {
        // wrapping everything in response
        $output['response'] = $data;

        /* Setting execution time when LARAVEL_START constant is defined. In
           some cases it may be not defined (for instance: Codeception tests)
        */
        if (defined('LARAVEL_START')) {
            $output['exec_time'] = microtime(true) - LARAVEL_START;
        } else {
            $output['exec_time'] = 0;
        }

        /**
         * Return true if APP (and DB) timezone is UTC, false otherwise.
         * Frontend will handle in different ways UTC dates and in other way
         * non-UTC dates
         */
        $crm = App::make('crm');
        $output['is_utc'] = $crm->isUtc();


        /**
         * In case user is logged, has access token, this access token is mobile
         * and user has no device token, we want to inform mobile device that
         * they need to send device token
         */
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            if ($user) {
                $accessToken = $user->getAccessToken();
                if ($accessToken && $accessToken->isMobile()) {
                    /** @var UserDeviceTokenRepository $tokenRepo */
                    $tokenRepo = App::make(UserDeviceTokenRepository::class);
                    $deviceToken =
                        $tokenRepo->findSoftByTokenId($accessToken->getId());
                    if (!$deviceToken) {
                        $output['device_token_required'] = true;
                    }
                }
            }
        }

        /**
         * If data cannot be encoded throw exception to log this and show
         * appropriate dev message
         */
        $data = json_encode($output, $this->encodingOptions);
        if ($data === false) {
            throw App::make(JsonEncodingException::class);
        }

        parent::__construct($output, $status, $headers, $options);
    }
}
