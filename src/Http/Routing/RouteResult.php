<?php

declare(strict_types=1);

namespace Framework\Http\Routing;

class RouteResult
{
    private ?RouteMatch $match;
    /** @var array<int, string> */
    private array $allowedMethods;

    private function __construct(?RouteMatch $match, array $allowedMethods = [])
    {
        $this->match = $match;
        $this->allowedMethods = $allowedMethods;
    }

    public static function matched(Route $route, array $parameters): self
    {
        return new self(new RouteMatch($route, $parameters));
    }

    /**
     * @param array<int, string> $allowedMethods
     */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        return new self(null, array_values(array_unique(array_map('strtoupper', $allowedMethods))));
    }

    public static function notFound(): self
    {
        return new self(null);
    }

    public function isSuccess(): bool
    {
        return $this->match !== null;
    }

    public function isMethodNotAllowed(): bool
    {
        return !$this->isSuccess() && $this->allowedMethods !== [];
    }

    public function getMatch(): ?RouteMatch
    {
        return $this->match;
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
