<?php

use App\Http\Controller\DocsController;
use App\Http\Controller\HomeController;
use App\Http\Controller\InfoController;
use Framework\Http\Session\StartSessionMiddleware;
use Framework\Http\Session\SessionManager;  
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\RouteAttributeMiddleware;
use Framework\Http\Routing\Router;
use function DI\value;
use Framework\Http\Cookie\CookieJar;
use Framework\Http\Cookie\CookieQueueMiddleware;

return [
    Router::GLOBAL_MIDDLEWARE => value([
        new StartSessionMiddleware(new SessionManager()),
        RequestIdMiddleware::class,
    ]),
    Router::ATTRIBUTE_CONTROLLERS => value([
        InfoController::class,
    ]),
    Router::ROUTE_BUILDERS => value([
        static function (Router $router): void {
            $router->get('/', [HomeController::class, 'index'])->name('home');
            $router->get('/hello/{name}', [HomeController::class, 'greet'], [RouteAttributeMiddleware::class])
                ->name('hello');

            $router->group(['prefix' => 'docs'], static function (Router $router): void {
                $router->get('/', [DocsController::class, 'index'])->name('docs.index');
                $router->get('/{slug}', [DocsController::class, 'show'])->name('docs.show');
            });
        },
    ]),
];
