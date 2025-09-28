# Routing Guide

Routes are registered through the `Framework\Http\Routing\Router`. Definitions can be added at runtime or declared via configuration.

```php
use App\Http\Controller\HomeController;
use Framework\Http\Routing\Router;

return [
    Router::ROUTE_BUILDERS => [
        static function (Router $router): void {
            $router->get('/', [HomeController::class, 'index'])->name('home');
            $router->post('/contact', [HomeController::class, 'submit']);

            $router->group(['prefix' => 'admin', 'middleware' => AdminAuthMiddleware::class], static function (Router $router): void {
                $router->get('/dashboard', [DashboardController::class, 'show']);
            });
        },
    ],
];
```

Key features:

- **HTTP verbs**: `get`, `post`, `put`, `patch`, `delete`, `options`, and `any` helper methods map to the respective verbs.
- **Parameters**: Path segments like `/users/{id}` are captured and injected as request attributes.
- **Constraints**: Use `/users/{id:\d+}` to restrict matches with custom regular expressions.
- **Naming**: Assign a name (`->name('users.show')`) for easier reverse routing and debugging.
- **Grouping**: `group()` applies a shared prefix and middleware stack to multiple routes.

During handling, matched route data is attached to the request as:

- `route` – the `Route` instance.
- `routeName` – the user-defined name (if any).
- `routeParameters` – key/value pairs for captured parameters.

These attributes can be consumed inside controllers or middleware to customize the response.
