<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Closure;
use DI\Container;
use Framework\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @var array<int, MiddlewareInterface|callable|string|array{0: class-string|object, 1?: string}> */
    private array $middlewares;
    private Container $container;
    private RequestHandlerInterface $finalHandler;

    /**
     * @param array<int, MiddlewareInterface|callable|string|array{0: class-string|object, 1?: string}> $middlewares
     */
    public function __construct(Container $container, array $middlewares, RequestHandlerInterface $finalHandler)
    {
        $this->container = $container;
        $this->middlewares = $middlewares;
        $this->finalHandler = $finalHandler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = $this->finalHandler;
        foreach (array_reverse($this->middlewares) as $definition) {
            /** @var MiddlewareInterface|callable|string|array{0: class-string|object, 1?: string} $definition */
            $middleware = $this->resolveMiddleware($definition);
            $handler = new class ($middleware, $handler) implements RequestHandlerInterface {
                private MiddlewareInterface $middleware;
                private RequestHandlerInterface $next;

                public function __construct(MiddlewareInterface $middleware, RequestHandlerInterface $next)
                {
                    $this->middleware = $middleware;
                    $this->next = $next;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            };
        }

        $response = $handler->handle($request);
        return ResponseFactory::from($response);
    }

    /**
     * @param MiddlewareInterface|callable|string|array{0: class-string|object, 1?: string} $definition
     */
    private function resolveMiddleware(MiddlewareInterface|callable|string|array $definition): MiddlewareInterface
    {
        if ($definition instanceof MiddlewareInterface) {
            return $definition;
        }

        if (is_callable($definition)) {
            return new CallableMiddleware($this->toCallable($definition));
        }

        if (is_string($definition)) {
            if (str_contains($definition, '@')) {
                [$class, $method] = explode('@', $definition, 2);
                $instance = $this->container->get($class);
                if (!is_object($instance) || !method_exists($instance, $method)) {
                    throw new RuntimeException(sprintf('Middleware method %s::%s not found', $class, $method));
                }

                return new CallableMiddleware($this->toCallable([$instance, $method]));
            }

            if (str_contains($definition, '::') && is_callable($definition)) {
                return new CallableMiddleware($this->toCallable($definition));
            }

            $service = $this->container->get($definition);
            if ($service instanceof MiddlewareInterface) {
                return $service;
            }

            if (is_callable($service)) {
                return new CallableMiddleware($this->toCallable($service));
            }

            if (is_object($service) && method_exists($service, '__invoke')) {
                return new CallableMiddleware($this->toCallable($service));
            }

            throw new RuntimeException(sprintf('Unable to resolve middleware: %s', $definition));
        }

        // $definition is array
        $callable = $definition;
        if (is_string($definition[0])) {
            $resolvedService = $this->container->get($definition[0]);
            if (!is_object($resolvedService)) {
                throw new RuntimeException('Array middleware definition must resolve to an object instance');
            }
            $callable[0] = $resolvedService;
        }

        return new CallableMiddleware($this->toCallable($callable));
    }

    /**
     * @param callable|string|array{0: class-string|object, 1?: string} $callable
     */
    private function toCallable(callable|string|array $callable): callable
    {
        if (!is_callable($callable)) {
            throw new RuntimeException('Resolved middleware is not callable');
        }

        return Closure::fromCallable($callable);
    }
}
