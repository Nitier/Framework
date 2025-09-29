<?php

declare(strict_types=1);

namespace Framework\Http\Routing\Attribute;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    /** @var array<int, string> */
    public array $methods;
    /** @var array<int, callable|string|array{0: class-string|object, 1?: string}> */
    public array $middleware;

    /**
     * @param array<int, string> $methods
     * @param array<int, callable|string|array{0: class-string|object, 1?: string}> $middleware
     */
    public function __construct(
        array $methods,
        public string $path,
        public ?string $name = null,
        array $middleware = [],
    ) {
        $this->methods = $methods;

        $sanitized = [];
        foreach ($middleware as $item) {
            if (is_array($item)) {
                if (!isset($item[0]) || (!is_string($item[0]) && !is_object($item[0]))) {
                    throw new InvalidArgumentException(
                        'Array middleware definition must contain a class-string or object at index 0.'
                    );
                }

                /** @var array{0: class-string|object, 1?: string} $item */
                $sanitized[] = $item;
                continue;
            }

            if (
                is_string($item) ||
                $item instanceof \Closure ||
                (is_object($item) && method_exists($item, '__invoke'))
            ) {
                $sanitized[] = $item;
                continue;
            }

            throw new InvalidArgumentException('Invalid middleware definition provided in Route attribute.');
        }

        $this->middleware = $sanitized;
    }
}
