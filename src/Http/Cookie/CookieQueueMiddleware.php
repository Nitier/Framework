<?php

declare(strict_types=1);

namespace Framework\Http\Cookie;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CookieQueueMiddleware implements MiddlewareInterface
{
    private CookieJar $cookies;

    public function __construct(CookieJar $cookies)
    {
        $this->cookies = $cookies;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $this->cookies->apply($response);
    }
}
