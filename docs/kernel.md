# HTTP Kernel

`Framework\Kernel` coordinates dependency injection, environment loading, routing, and middleware dispatch.

## Bootstrapping

```php
use Framework\Kernel;

$kernel = new Kernel();
$kernel->loadApplication(__DIR__.'/app');

// Emits the response automatically and returns the PSR-7 response instance
$response = $kernel->handle();

// Disable emission (useful for testing) by passing `false` as the second argument
// $response = $kernel->handle($request, false);
```

`loadApplication()` merges application configuration into the base container and primes services such as the environment reader. Additional services are discovered automatically through PHP-DI autowiring.

## Handling Requests

- A `ServerRequestInterface` is resolved from the container (default: globals) or can be passed explicitly.
- The router yields a `RouteResult` indicating success, 404 (not found), or 405 (method not allowed).
- When a route matches, all captured parameters are exposed as request attributes alongside `route`, `routeName`, and `routeParameters`.
- Route/global middleware are executed via the PSR-15 `MiddlewarePipeline` before reaching the resolved handler.
- Exceptions bubble up in debug mode; otherwise a configurable error handler produces the response.

## Error Handling

Error pages for 404 (not found), 405 (method not allowed), and 500 (internal error) can be overridden by rebinding the following container keys to either a `ResponseInterface` instance or a callable:

- `Kernel::ERROR_HANDLER_NOT_FOUND`
- `Kernel::ERROR_HANDLER_METHOD_NOT_ALLOWED`
- `Kernel::ERROR_HANDLER_EXCEPTION`

Example override in configuration:

```php
use Framework\Http\ResponseFactory;
use Framework\Kernel;
use Psr\Http\Message\ServerRequestInterface;
use function DI\value;

return [
    Kernel::ERROR_HANDLER_NOT_FOUND => value(
        static fn(ServerRequestInterface $request) => ResponseFactory::from('Custom 404', 404)
    ),
    Kernel::ERROR_HANDLER_EXCEPTION => ResponseFactory::from('Something went wrong', 500),
];
```

## Extending

- Bind additional services in `config/*.php` within your application.
- Register custom route builders to modularise feature routing.
- Replace the default `ResponseEmitter` by binding your own implementation into the container.

See `docs/routing.md` and `docs/middleware.md` for adjacent configuration details.
