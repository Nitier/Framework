<?php

declare(strict_types=1);

namespace Framework\Http\Routing;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

class Router
{
    public const ROUTE_BUILDERS = 'kernel.http.routes';
    public const GLOBAL_MIDDLEWARE = 'kernel.http.middleware';
    public const ATTRIBUTE_CONTROLLERS = 'kernel.http.attribute.controllers';

    /** @var array<int, Route> */
    private array $routes = [];
    /**
     * @var array<int, array{
     *     prefix: string,
     *     middleware: array<int, callable|string|array{0: class-string|object, 1?: string}>
     * }>
     */
    private array $groupStack = [];
    /** @var array<int, callable|string|array{0: class-string|object, 1?: string}> */
    private array $globalMiddleware;

    /**
     * @param array<int, callable|string|array{0: class-string|object, 1?: string}> $globalMiddleware
     */
    public function __construct(array $globalMiddleware = [])
    {
        $this->globalMiddleware = array_is_list($globalMiddleware)
            ? $globalMiddleware
            : array_values($globalMiddleware);
    }

    /**
     * @param list<string>|string $methods
     * @param callable|string|array{0: class-string|object, 1?: string} $handler
     * @param list<callable|string|array{0: class-string|object, 1?: string}> $middleware
     */
    public function add(
        array|string $methods,
        string $path,
        callable|string|array $handler,
        array $middleware = []
    ): Route {
        $methods = is_array($methods) ? $methods : [$methods];
        $route = new Route(
            $methods,
            $this->applyGroupPrefix($path),
            $handler,
            $this->mergeGroupMiddleware($middleware)
        );
        $this->routes[] = $route;
        return $route;
    }

    /**
     * @param callable|string|array{0: class-string|object, 1?: string} $handler
     * @param list<callable|string|array{0: class-string|object, 1?: string}> $middleware
     */
    public function get(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['GET'], $path, $handler, $middleware);
    }

    /**
     * @param callable|string|array{0: class-string|object, 1?: string} $handler
     * @param list<callable|string|array{0: class-string|object, 1?: string}> $middleware
     */
    public function post(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['POST'], $path, $handler, $middleware);
    }

    /**
     * @param callable|string|array{0: class-string|object, 1?: string} $handler
     * @param list<callable|string|array{0: class-string|object, 1?: string}> $middleware
     */
    public function put(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['PUT'], $path, $handler, $middleware);
    }

    /**
     * @param callable|string|array{0: class-string|object, 1?: string} $handler
     * @param list<callable|string|array{0: class-string|object, 1?: string}> $middleware
     */
    public function patch(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['PATCH'], $path, $handler, $middleware);
    }

    /**
     * @param callable|string|array{0: class-string|object, 1?: string} $handler
     * @param list<callable|string|array{0: class-string|object, 1?: string}> $middleware
     */
    public function delete(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['DELETE'], $path, $handler, $middleware);
    }

    /**
     * @param callable|string|array{0: class-string|object, 1?: string} $handler
     * @param list<callable|string|array{0: class-string|object, 1?: string}> $middleware
     */
    public function options(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['OPTIONS'], $path, $handler, $middleware);
    }

    /**
     * @param callable|string|array{0: class-string|object, 1?: string} $handler
     * @param list<callable|string|array{0: class-string|object, 1?: string}> $middleware
     */
    public function any(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $path, $handler, $middleware);
    }

    /**
     * @param array{
     *     prefix?: string,
     *     middleware?: callable|string|array{0: mixed, 1?: string}
     *         | array<int, callable|string|array{0: mixed, 1?: string}>
     * } $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $prefix = $attributes['prefix'] ?? '';
        $middleware = $attributes['middleware'] ?? [];
        $middleware = is_array($middleware) && array_is_list($middleware) ? $middleware : [$middleware];

        /** @var list<callable|string|array{0: class-string|object, 1?: string}> $sanitized */
        $sanitized = [];
        foreach ($middleware as $item) {
            if ($item === null) {
                continue;
            }

            if (!is_string($item) && !is_callable($item) && !is_array($item)) {
                throw new InvalidArgumentException('Middleware group definition must be callable, array, or string.');
            }

            if (is_array($item)) {
                if (!array_key_exists(0, $item)) {
                    throw new InvalidArgumentException('Array middleware definition must have a callable at index 0.');
                }

                /** @var array{0: class-string|object, 1?: string} $item */
                $sanitized[] = $item;
                continue;
            }

            $sanitized[] = $item;
        }

        $this->groupStack[] = [
            'prefix' => $prefix === '' ? '' : '/' . trim((string) $prefix, '/'),
            'middleware' => $sanitized,
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    public function match(ServerRequestInterface $request): RouteResult
    {
        $path = $request->getUri()->getPath() ?: '/';
        $method = strtoupper($request->getMethod());
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $route->matchPath($path);
            if ($params === null) {
                continue;
            }

            if ($route->acceptsMethod($method)) {
                return RouteResult::matched($route, $params);
            }

            $allowed = array_merge($allowed, $route->getMethods());
        }

        if ($allowed !== []) {
            return RouteResult::methodNotAllowed($allowed);
        }

        return RouteResult::notFound();
    }

    /**
     * @return array<int, Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @return list<callable|string|array{0: class-string|object, 1?: string}>
     */
    public function getGlobalMiddleware(): array
    {
        return array_values($this->globalMiddleware);
    }

    /**
     * @param array<int, callable|string|array{0: class-string|object, 1?: string}> $middleware
     */
    public function setGlobalMiddleware(array $middleware): void
    {
        if (!array_is_list($middleware)) {
            $middleware = array_values($middleware);
        }

        $this->globalMiddleware = $middleware;
    }

    private function applyGroupPrefix(string $path): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            $prefix .= rtrim($group['prefix'], '/');
        }

        $combined = trim($prefix . '/' . ltrim($path, '/'), '/');

        return $combined === '' ? '/' : '/' . $combined;
    }

    /**
     * @param list<callable|string|array{0: class-string|object, 1?: string}> $routeMiddleware
     * @return list<callable|string|array{0: class-string|object, 1?: string}>
     */
    private function mergeGroupMiddleware(array $routeMiddleware): array
    {
        /** @var list<callable|string|array{0: class-string|object, 1?: string}> $merged */
        $merged = [];
        foreach ($this->groupStack as $group) {
            $merged = array_merge($merged, $group['middleware']);
        }

        return array_merge($merged, $routeMiddleware);
    }
}
