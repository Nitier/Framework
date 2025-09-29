<?php

declare(strict_types=1);

namespace Framework\Http\Routing\Attribute;

use Framework\Http\Routing\Router;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

class RouteAttributeRegistrar
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * @param array<int, class-string> $controllerClasses
     */
    public function registerControllers(array $controllerClasses): void
    {
        foreach ($controllerClasses as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF);
                if ($attributes === []) {
                    continue;
                }

                foreach ($attributes as $attribute) {
                    /** @var Route $meta */
                    $meta = $attribute->newInstance();
                    $route = $this->router->add(
                        array_values($meta->methods),
                        $meta->path,
                        [$class, $method->getName()],
                        array_values($meta->middleware)
                    );

                    if ($meta->name !== null) {
                        $route->name($meta->name);
                    }
                }
            }
        }
    }
}
