<?php

declare(strict_types=1);

namespace Framework\Http\Routing;

class RouteMatch
{
    private Route $route;
    /** @var array<string, string> */
    private array $parameters;

    /**
     * @param array<string, string> $parameters
     */
    public function __construct(Route $route, array $parameters = [])
    {
        $this->route = $route;
        $this->parameters = $parameters;
    }

    public function getRoute(): Route
    {
        return $this->route;
    }

    /**
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getParameter(string $name, ?string $default = null): ?string
    {
        return $this->parameters[$name] ?? $default;
    }
}
