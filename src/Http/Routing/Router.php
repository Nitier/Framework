<?php

declare(strict_types=1);

namespace Framework\Http\Routing;

use Psr\Http\Message\ServerRequestInterface;

class Router
{
    public const ROUTE_BUILDERS = 'kernel.http.routes';
    public const GLOBAL_MIDDLEWARE = 'kernel.http.middleware';

    /** @var array<int, Route> */
    private array $routes = [];
    /** @var array<int, array{prefix: string, middleware: array<int, callable|string|array{0: mixed, 1?: string}>}> */
    private array $groupStack = [];
    /** @var array<int, callable|string|array{0: mixed, 1?: string}> */
    private array $globalMiddleware;

    /**
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $globalMiddleware
     */
    public function __construct(array $globalMiddleware = [])
    {
        $this->globalMiddleware = array_values($globalMiddleware);
    }

    /**
     * @param array<int, string>|string $methods
     * @param callable|string|array{0: mixed, 1?: string} $handler
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $middleware
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
     * @param callable|string|array{0: mixed, 1?: string} $handler
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $middleware
     */
    public function get(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['GET'], $path, $handler, $middleware);
    }

    /**
     * @param callable|string|array{0: mixed, 1?: string} $handler
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $middleware
     */
    public function post(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['POST'], $path, $handler, $middleware);
    }

    /**
     * @param callable|string|array{0: mixed, 1?: string} $handler
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $middleware
     */
    public function put(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['PUT'], $path, $handler, $middleware);
    }

    /**
     * @param callable|string|array{0: mixed, 1?: string} $handler
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $middleware
     */
    public function patch(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['PATCH'], $path, $handler, $middleware);
    }

    /**
     * @param callable|string|array{0: mixed, 1?: string} $handler
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $middleware
     */
    public function delete(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['DELETE'], $path, $handler, $middleware);
    }

    /**
     * @param callable|string|array{0: mixed, 1?: string} $handler
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $middleware
     */
    public function options(string $path, callable|string|array $handler, array $middleware = []): Route
    {
        return $this->add(['OPTIONS'], $path, $handler, $middleware);
    }

    /**
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $middleware
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

        $this->groupStack[] = [
            'prefix' => $prefix === '' ? '' : '/' . trim((string) $prefix, '/'),
            'middleware' => array_values(array_filter($middleware, static fn($item) => $item !== null)),
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
     * @return array<int, callable|string|array{0: mixed, 1?: string}>
     */
    public function getGlobalMiddleware(): array
    {
        return $this->globalMiddleware;
    }

    /**
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $middleware
     */
    public function setGlobalMiddleware(array $middleware): void
    {
        $this->globalMiddleware = array_values($middleware);
    }

    private function applyGroupPrefix(string $path): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            $prefix .= rtrim($group['prefix'], '/');
        }

        $full = $prefix . '/' . ltrim($path, '/');
        if ($full === '' || $full === '/') {
            return '/';
        }

        return '/' . trim($full, '/');
    }

    /**
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $routeMiddleware
     * @return array<int, callable|string|array{0: mixed, 1?: string}>
     */
    private function mergeGroupMiddleware(array $routeMiddleware): array
    {
        $merged = [];
        foreach ($this->groupStack as $group) {
            $merged = array_merge($merged, $group['middleware']);
        }

        return array_merge($merged, $routeMiddleware);
    }
}
