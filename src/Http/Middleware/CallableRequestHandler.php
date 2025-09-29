<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Closure;
use Framework\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CallableRequestHandler implements RequestHandlerInterface
{
    /** @var callable */
    private $handler;
    private int $parameterCount;

    public function __construct(callable $handler)
    {
        $this->handler = $handler;
        $this->parameterCount = $this->resolveParameterCount($handler);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->parameterCount >= 2) {
            $result = ($this->handler)($request, $request->getAttributes());
        } else {
            $result = ($this->handler)($request);
        }
        return ResponseFactory::from($result);
    }

    private function resolveParameterCount(callable $handler): int
    {
        $closure = Closure::fromCallable($handler);
        $reflection = new \ReflectionFunction($closure);
        return $reflection->getNumberOfParameters();
    }
}
