<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use DI\Container;
use Framework\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

class MiddlewarePipeline implements RequestHandlerInterface
{
    private Container $container;
    /** @var array<int, callable|string|array{0: mixed, 1?: string}> */
    private array $middlewares;
    private RequestHandlerInterface $finalHandler;

    /**
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $middlewares
     */
    public function __construct(Container $container, array $middlewares, RequestHandlerInterface $finalHandler)
    {
        $this->container = $container;
        $this->middlewares = array_values($middlewares);
        $this->finalHandler = $finalHandler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = array_reduce(
            array_reverse($this->middlewares),
            function (RequestHandlerInterface $next, callable|string|array $definition): RequestHandlerInterface {
                $middleware = $this->resolveMiddleware($definition);
                return new class ($middleware, $next) implements RequestHandlerInterface {
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
            },
            $this->finalHandler
        );

        $response = $handler->handle($request);
        return ResponseFactory::from($response);
    }

    /**
     * @param callable|string|array{0: mixed, 1?: string} $definition
     */
    private function resolveMiddleware(callable|string|array $definition): MiddlewareInterface
    {
        if ($definition instanceof MiddlewareInterface) {
            return $definition;
        }

        if (is_callable($definition)) {
            return new CallableMiddleware($definition);
        }

        if (is_string($definition)) {
            if (str_contains($definition, '@')) {
                [$class, $method] = explode('@', $definition, 2);
                $instance = $this->container->get($class);
                if (!method_exists($instance, $method)) {
                    throw new RuntimeException(sprintf('Middleware method %s::%s not found', $class, $method));
                }

                return new CallableMiddleware([$instance, $method]);
            }

            if (str_contains($definition, '::') && is_callable($definition)) {
                return new CallableMiddleware($definition);
            }

            $service = $this->container->get($definition);
            if ($service instanceof MiddlewareInterface) {
                return $service;
            }

            if (is_callable($service)) {
                return new CallableMiddleware($service);
            }

            if (method_exists($service, '__invoke')) {
                return new CallableMiddleware($service);
            }

            throw new RuntimeException(sprintf('Unable to resolve middleware: %s', $definition));
        }

        if (is_array($definition)) {
            $resolved = $definition;
            if (isset($definition[0]) && is_string($definition[0])) {
                $resolved[0] = $this->container->get($definition[0]);
            }

            if (!is_callable($resolved)) {
                throw new RuntimeException('Array middleware definition is not callable');
            }

            return new CallableMiddleware($resolved);
        }

        throw new RuntimeException('Invalid middleware definition provided');
    }
}
