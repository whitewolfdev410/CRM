<?php

namespace App\Exceptions;

use App\Core\Exceptions\ApiException;
use App\Core\Exceptions\DatabaseException;
use App\Core\Exceptions\GeneralException;
use App\Core\Exceptions\HttpNotFoundException;
use App\Core\Exceptions\ObjectNotFoundException;
use App\Core\Exceptions\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

// use Illuminate\Validation\ValidationException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        ValidationException::class,
        ApiException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $e
     *
     * @return void
     */

    public function report(Throwable $e)
    {
        if ($e instanceof ValidationException) {
            return;
        }

        if ($e instanceof ApiException) {
            $e->log();

            return;
        }

        if ($e instanceof ModelNotFoundException) {
            $exp = app(ObjectNotFoundException::class);
            $exp->setData(['exception' => (string)$e]);
            $exp->log();

            return;
        }

        if ($e instanceof NotFoundHttpException ||
            $e instanceof MethodNotAllowedHttpException
        ) {
            $exp = app(HttpNotFoundException::class);
            $exp->setData(['exception' => (string)$e]);
            $exp->log();

            return;
        }

        if ($e instanceof \PDOException) {
            $exp = app(DatabaseException::class);
            $exp->setData(['exception' => (string)$e]);
            $exp->log();

            return;
        }

        $exp = app(GeneralException::class);
        $exp->setData(['exception' => (string)$e]);
        $exp->log();
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Throwable  $e
     *
     * @return \Illuminate\Http\Response
     * @throws Throwable
     */

    public function render($request, Throwable $e)
    {
        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return $this->unauthenticated($request, $e);
        }

        if ($e instanceof ValidationException) {
            return $this->sendJsonResponse($e);
        }

        // handle API exception
        if ($e instanceof ApiException) {
            return $this->sendJsonResponse($e);
        }

        // map Laravel exceptions into custom exceptions

        if ($e instanceof ModelNotFoundException) {
            $exp = app(ObjectNotFoundException::class);
            $exp->setData(['exception' => (string)$e]);

            return $this->sendJsonResponse($exp);
        }

        if ($e instanceof NotFoundHttpException ||
            $e instanceof MethodNotAllowedHttpException
        ) {
            $exp = app(HttpNotFoundException::class);

            return $this->sendJsonResponse($exp);
        }

        if ($e instanceof \PDOException) {
            $exp = app(DatabaseException::class);

            return $this->sendJsonResponse($exp);
        }

        // when debug set to true, display other errors in default Laravel format
        if (config('app.debug', false)) {
            return parent::render($request, $e);
        }

        // if debug set to false display general description
        $exp = app(GeneralException::class);

        return $this->sendJsonResponse($exp);
    }

    /**
     * Create json response with cors and exception data
     *
     * @param  \Throwable  $exp
     *
     * @return \Illuminate\Http\Response
     */
    public function sendJsonResponse(Throwable $exp)
    {
        $response = response()
            ->json(
                $exp->getResponseData(),
                $exp->getStatusCode()
            );

        if(empty($_SERVER['HTTP_X_CORS'])) {
            $response = $response
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, User-Info')
                ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        }
        
        return $response;
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $e
     *
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $e)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated2.'], 401);
        } else {
            return redirect()->guest('login');
        }
    }
}
