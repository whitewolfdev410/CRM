<?php

namespace App\Http\Controllers;

use App\Core\User;
use App\Http\Requests\PasswordResetRequest;
use App\Modules\EmailTemplate\Providers\AuthFieldsProvider;
use App\Modules\EmailTemplate\Providers\AuthFieldsProviderClientPortal;
use App\Modules\EmailTemplate\Providers\OrganizationFieldsProvider;
use App\Modules\EmailTemplate\Providers\UserFieldsProvider;
use App\Modules\EmailTemplate\Services\EmailTemplateService;
use App\Modules\User\Http\Requests\UserUpdateRequest;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

class PasswordController extends Controller
{
    use ResetsPasswords;

    /**
     * @var Container
     */
    protected $app;

    /**
     * Constructor
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Return the password reset fields.
     *
     * @param string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReset($token = null)
    {
        $isTokenValid = is_null($token);

        $request = new UserUpdateRequest();

        return response()->json([
            'fields' => $isTokenValid ? $request->getRules() : null,
        ]);
    }

    /**
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws HttpResponseException
     */
    public function postEmail(Request $request)
    {
        $this->validate($request, ['email' => 'required|email']);

        $credentials = $request->all('email');

        /** @var User $user */
        $user = Password::getUser($credentials);
        //$user = $this->users->retrieveByCredentials($credentials);

        if (is_null($user)) {
            return response()->json([
                'result'  => 'failure',
                'message' => trans(Password::INVALID_USER),
            ]);
        }

        $templateService = $this->app->make(EmailTemplateService::class);

        $clientPortal = $request->all('client_portal');

        // reset password link for client portal
        if ($clientPortal['client_portal'] == true) {
            $templateService->sendTemplate(
                'kb::ack_request',
                $templateService->mergeByTemplateId(
                    'password.reset_client_portal',
                    'en-US',
                    [
                        //'ppms_acknowledge_link' => url("/#/kb/accept/{$ack->getRequestToken()}"),
                    ],
                    [
                        AuthFieldsProvider::NAME => new AuthFieldsProviderClientPortal($this->app, $user),
                        OrganizationFieldsProvider::NAME => new OrganizationFieldsProvider(),
                        UserFieldsProvider::NAME => new UserFieldsProvider($user),
                    ]
                ),
                $user->getEmailForPasswordReset()
            );
        } else {
            $templateService->sendTemplate(
                'kb::ack_request',
                $templateService->mergeByTemplateId(
                    config('auth.password.email'),
                    'en-US',
                    [
                        //'ppms_acknowledge_link' => url("/#/kb/accept/{$ack->getRequestToken()}"),
                    ],
                    [
                        AuthFieldsProvider::NAME => new AuthFieldsProvider($this->app, $user),
                        OrganizationFieldsProvider::NAME => new OrganizationFieldsProvider(),
                        UserFieldsProvider::NAME => new UserFieldsProvider($user),
                    ]
                ),
                $user->getEmailForPasswordReset()
            );
        }

        return response()->json(['result' => 'success']);
    }

    /**
     * Reset the given user's password.
     *
     * @param  \App\Http\Requests\PasswordResetRequest $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function postReset(PasswordResetRequest $request)
    {
        $credentials = $request->all(
            'email',
            'password',
            're_password',
            'token'
        );

        $credentials['password_confirmation'] = $credentials['re_password'];

        $response = Password::reset($credentials, function ($user, $password) {
            /** @var User $user */
            $user->expire_at = null;
            $user->is_password_temporary = false;

            $this->resetPassword($user, $password);
        });

        if ($response === Password::PASSWORD_RESET) {
            //
            Auth::logout();

            return response()->json(['result' => 'success']);
        }

        return response()->json(
            [
            'result'  => 'failure',
            'message' => trans($response),
            ],
            422
        );
    }
}
