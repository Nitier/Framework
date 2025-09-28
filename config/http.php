<?php

use DI\Container;
use Framework\Http\Message\ServerRequest;
use Framework\Http\ResponseEmitter;
use Framework\Http\ResponseFactory;
use Framework\Http\Routing\HandlerResolver;
use Framework\Http\Routing\Route;
use Framework\Http\Routing\Router;
use Framework\Kernel;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use function DI\value;

return [
    ServerRequestInterface::class => static function (): ServerRequestInterface {
        return ServerRequest::fromGlobals();
    },
    ServerRequest::class => static function (ContainerInterface $container): ServerRequestInterface {
        return $container->get(ServerRequestInterface::class);
    },
    HandlerResolver::class => static function (Container $container): HandlerResolver {
        return new HandlerResolver($container);
    },
    Router::GLOBAL_MIDDLEWARE => value([]),
    Router::ROUTE_BUILDERS => value([]),
    Router::class => static function (ContainerInterface $container): Router {
        $globalMiddleware = [];
        if ($container->has(Router::GLOBAL_MIDDLEWARE)) {
            $global = $container->get(Router::GLOBAL_MIDDLEWARE);
            if (is_array($global)) {
                $globalMiddleware = $global;
            }
        }

        $router = new Router($globalMiddleware);
        $routeDefinitions = [];
        if ($container->has(Router::ROUTE_BUILDERS)) {
            $routeDefinitions = $container->get(Router::ROUTE_BUILDERS);
            if (!is_array($routeDefinitions)) {
                $routeDefinitions = [$routeDefinitions];
            }
        }

        foreach ($routeDefinitions as $definition) {
            if ($definition instanceof Route) {
                $router->add(
                    $definition->getMethods(),
                    $definition->getPath(),
                    $definition->getHandler(),
                    $definition->getMiddleware()
                );
                continue;
            }

            if (is_callable($definition)) {
                $callable = \Closure::fromCallable($definition);
                $reflection = new \ReflectionFunction($callable);
                $parameters = $reflection->getNumberOfParameters();
                if ($parameters >= 2) {
                    $definition($router, $container);
                } else {
                    $definition($router);
                }
            }
        }

        return $router;
    },
    Kernel::ERROR_HANDLER_NOT_FOUND => value(
        static fn(ServerRequestInterface $request) => ResponseFactory::from('Not Found', 404)
    ),
    Kernel::ERROR_HANDLER_METHOD_NOT_ALLOWED => value(
        static function (ServerRequestInterface $request, array $allowedMethods) {
            $response = ResponseFactory::from('Method Not Allowed', 405);
            if ($allowedMethods !== []) {
                $response = $response->withHeader('Allow', implode(', ', $allowedMethods));
            }
            return $response;
        }
    ),
    Kernel::ERROR_HANDLER_EXCEPTION => value(
        static fn(ServerRequestInterface $request, \Throwable $exception) => ResponseFactory::from('Internal Server Error', 500)
    ),
    ResponseEmitter::class => static function (): ResponseEmitter {
        return new ResponseEmitter();
    },
];
