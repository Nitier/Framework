<?php

declare(strict_types=1);

namespace Framework\Http\Routing;

use DI\Container;
use Framework\Http\Middleware\CallableRequestHandler;
use Framework\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

class HandlerResolver
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param callable|string|array{0: mixed, 1?: string} $handler
     */
    public function resolve(callable|string|array $handler): RequestHandlerInterface
    {
        if ($handler instanceof RequestHandlerInterface) {
            return $handler;
        }

        if (is_callable($handler)) {
            return new CallableRequestHandler($handler);
        }

        if (is_string($handler)) {
            if (function_exists($handler)) {
                return new CallableRequestHandler($handler);
            }

            if (str_contains($handler, '@')) {
                [$class, $method] = explode('@', $handler, 2);
                $instance = $this->container->get($class);
                if (!method_exists($instance, $method)) {
                    throw new RuntimeException(sprintf('Handler method %s::%s not found', $class, $method));
                }

                return new CallableRequestHandler([$instance, $method]);
            }

            if (str_contains($handler, '::') && is_callable($handler)) {
                return new CallableRequestHandler($handler);
            }

            if (class_exists($handler)) {
                $service = $this->container->get($handler);
                if ($service instanceof RequestHandlerInterface) {
                    return $service;
                }

                if (is_callable($service)) {
                    return new CallableRequestHandler($service);
                }

                if (method_exists($service, '__invoke')) {
                    return new CallableRequestHandler($service);
                }

                throw new RuntimeException(sprintf('Resolved handler %s is not callable', $handler));
            }

            throw new RuntimeException(sprintf('Unable to resolve handler: %s', $handler));
        }

        if (is_array($handler)) {
            $callable = $handler;
            if (isset($handler[0]) && is_string($handler[0])) {
                $callable[0] = $this->container->get($handler[0]);
            }

            if (!is_callable($callable)) {
                throw new RuntimeException('Array handler definition is not callable');
            }

            return new CallableRequestHandler($callable);
        }

        throw new RuntimeException('Invalid handler provided');
    }

    public function toResponse(mixed $result): ResponseInterface
    {
        return ResponseFactory::from($result);
    }
}
