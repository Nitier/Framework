<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Closure;
use Framework\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CallableMiddleware implements MiddlewareInterface
{
    /** @var callable */
    private $callable;
    private int $parameterCount;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
        $this->parameterCount = $this->resolveParameterCount($callable);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->parameterCount >= 3) {
            $result = ($this->callable)($request, $handler, $request->getAttributes());
        } elseif ($this->parameterCount >= 2) {
            $result = ($this->callable)($request, $handler);
        } else {
            $result = ($this->callable)($request);
        }

        return ResponseFactory::from($result);
    }

    private function resolveParameterCount(callable $callable): int
    {
        $closure = Closure::fromCallable($callable);
        $reflection = new \ReflectionFunction($closure);
        return $reflection->getNumberOfParameters();
    }
}
