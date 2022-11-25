<?php

namespace App\Helpers\GuzzleHttp;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleResponseMiddleware
{
    private $nextHandler;

    /**
     * Constructor
     * @param callable $nextHandler
     */
    public function __construct(callable $nextHandler)
    {
        $this->nextHandler = $nextHandler;
    }

    /**
     * Map response
     * @param RequestInterface $request
     * @param array            $options
     * @return RequestInterface
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $fn = $this->nextHandler;

        return $fn($request, $options)->then(function (ResponseInterface $response) use ($request) {
            return $this->makeResponse($request, $response);
        });
    }

    /**
     * Make extended response instance
     * @param  RequestInterface  $request
     * @param  ResponseInterface $response
     * @return GuzzleResponse
     */
    private function makeResponse(RequestInterface $request, ResponseInterface $response)
    {
        $resp = GuzzleResponse::fromPsrResponse($response);

        // set effective URI
        $effectiveUri = (string) $request->getUri();
        $resp->setEffectiveUri($effectiveUri);

        return $resp;
    }

    /**
     * Prepare a middleware closure to be used with HandlerStack
     * @return \Closure
     */
    public static function middleware()
    {
        return function (callable $handler) {
            return new static($handler);
        };
    }
}
