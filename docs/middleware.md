# Middleware Pipeline

Middleware must implement `Psr\Http\Server\MiddlewareInterface`. The framework also accepts callables and container service identifiers, coercing them into compliant middleware via `CallableMiddleware`.

## Global Middleware

Register global middleware strings or callables in configuration:

```php
use App\Http\Middleware\RequestIdMiddleware;
use Framework\Http\Routing\Router;

return [
    Router::GLOBAL_MIDDLEWARE => [
        RequestIdMiddleware::class,
    ],
];
```

These run for every request before route-specific middleware.

## Route Middleware

Attach middleware only to a specific route:

```php
$router->get('/profile', [ProfileController::class, 'show'], [EnsureAuthenticated::class]);
```

## Custom Middleware Example

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TimingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $started = microtime(true);
        $response = $handler->handle($request);
        $elapsed = number_format((microtime(true) - $started) * 1000, 2);

        return $response->withHeader('X-Response-Time', $elapsed . 'ms');
    }
}
```

Callables with a `(ServerRequestInterface $request, RequestHandlerInterface $next)` signature are supported and automatically wrapped. Middleware may also opt into a third `$attributes` argument to receive the full request attribute bag.

The `MiddlewarePipeline` preserves declaration order: items earlier in the array execute before later ones, allowing deterministic around filters.
