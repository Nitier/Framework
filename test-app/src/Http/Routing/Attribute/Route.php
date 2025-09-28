<?php

declare(strict_types=1);

namespace App\Http\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    /**
     * @param array<int, string> $methods
     * @param array<int, callable|string|array{0: mixed, 1?: string}> $middleware
     */
    public function __construct(
        public array $methods,
        public string $path,
        public ?string $name = null,
        public array $middleware = [],
    ) {
    }
}
