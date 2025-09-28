<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

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
        if (is_array($handler)) {
            [$classOrObject, $method] = $handler;
            if (is_object($classOrObject)) {
                $reflection = new \ReflectionMethod($classOrObject, (string) $method);
            } else {
                $reflection = new \ReflectionMethod((string) $classOrObject, (string) $method);
            }
            return $reflection->getNumberOfParameters();
        }

        if (is_object($handler) && method_exists($handler, '__invoke')) {
            $reflection = new \ReflectionMethod($handler, '__invoke');
            return $reflection->getNumberOfParameters();
        }

        $reflection = new \ReflectionFunction($handler);
        return $reflection->getNumberOfParameters();
    }
}
