<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = 'test-request';
        $response = $handler->handle($request->withAttribute('requestId', $requestId));
        return $response->withHeader('X-Request-Id', $requestId);
    }
}
