<?php

use App\Http\Controller\HomeController;
use App\Http\Controller\InfoController;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\RouteAttributeMiddleware;
use App\Http\Routing\Attribute\Route as RouteAttribute;
use Framework\Http\Routing\Router;
use function DI\value;

return [
    Router::GLOBAL_MIDDLEWARE => value([
        RequestIdMiddleware::class,
    ]),
    Router::ROUTE_BUILDERS => value([
        static function (Router $router): void {
            $router->get('/', [HomeController::class, 'index'])->name('home');
            $router->get('/hello/{name}', [HomeController::class, 'greet'], [RouteAttributeMiddleware::class])
                ->name('hello');

            $controllers = [
                InfoController::class,
            ];

            foreach ($controllers as $controller) {
                $reflection = new \ReflectionClass($controller);
                foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    foreach ($method->getAttributes(RouteAttribute::class) as $attribute) {
                        /** @var RouteAttribute $meta */
                        $meta = $attribute->newInstance();
                        $route = $router->add(
                            $meta->methods,
                            $meta->path,
                            [$controller, $method->getName()],
                            $meta->middleware
                        );
                        if ($meta->name !== null) {
                            $route->name($meta->name);
                        }
                    }
                }
            }
        },
    ]),
];
