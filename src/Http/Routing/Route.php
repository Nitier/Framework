<?php

declare(strict_types=1);

namespace Framework\Http\Routing;

use Psr\Http\Message\ServerRequestInterface;

class Route
{
    /** @var array<int, string> */
    private array $methods;
    private string $path;
    /** @var callable|string|array{0: class-string|object, 1?: string} */
    private $handler;
    /** @var list<callable|string|array{0: class-string|object, 1?: string}> */
    private array $middleware;
    private string $pattern;
    /** @var array<int, string> */
    private array $parameterNames;
    private ?string $name = null;

    /**
     * @param array<int, string> $methods
     * @param callable|string|array{0: class-string|object, 1?: string} $handler
     * @param array<int, callable|string|array{0: class-string|object, 1?: string}> $middleware
     */
    public function __construct(array $methods, string $path, callable|string|array $handler, array $middleware = [])
    {
        $this->methods = array_map(static fn(string $method): string => strtoupper($method), $methods);
        $this->path = $this->normalizePath($path);
        $this->handler = $handler;
        $this->middleware = array_values($middleware);

        [$pattern, $parameterNames] = $this->compilePattern($this->path);
        $this->pattern = $pattern;
        $this->parameterNames = $parameterNames;
    }

    public function acceptsMethod(string $method): bool
    {
        $method = strtoupper($method);
        if ($method === 'HEAD' && in_array('GET', $this->methods, true)) {
            return true;
        }

        return in_array($method, $this->methods, true);
    }

    /**
     * @param ServerRequestInterface $request
     * @return array<string, string>|null
     */
    public function match(ServerRequestInterface $request): ?array
    {
        $path = $request->getUri()->getPath() ?: '/';
        return $this->matchPath($path);
    }
    /**
     * @return array<string, string>|null
     */
    public function matchPath(string $path): ?array
    {
        $path = $path === '' ? '/' : '/' . ltrim($path, '/');
        $path = $path === '//' ? '/' : $path;

        if (!preg_match($this->pattern, rawurldecode($path), $matches)) {
            return null;
        }

        /** @var array<string, string> $params */
        $params = [];
        foreach ($this->parameterNames as $name) {
            if (array_key_exists($name, $matches)) {
                $params[$name] = $matches[$name];
            }
        }

        return $params;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return array<int, string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return callable|string|array{0: class-string|object, 1?: string}
     */
    public function getHandler(): callable|string|array
    {
        return $this->handler;
    }

    /**
     * @return array<int, callable|string|array{0: class-string|object, 1?: string}>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * @return array<int, string>
     */
    public function getParameterNames(): array
    {
        return $this->parameterNames;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $path = $path === '//' ? '/' : $path;
        return rtrim($path, '/') ?: '/';
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function compilePattern(string $path): array
    {
        $parameterNames = [];
        $tokenIndex = 0;
        $tokens = [];

        $placeholderReplaced = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_-]*)(?::([^}]+))?}/',
            static function (array $matches) use (&$parameterNames, &$tokenIndex, &$tokens): string {
                $parameterNames[] = $matches[1];
                $token = sprintf('::param%s::', $tokenIndex++);
                $tokens[$token] = [$matches[1], $matches[2] ?? '[^/]+'];
                return $token;
            },
            $path
        );

        if ($placeholderReplaced === null) {
            $placeholderReplaced = $path;
        }

        $quoted = preg_quote($placeholderReplaced, '#');
        foreach ($tokens as $token => [$name, $expression]) {
            $quoted = str_replace(preg_quote($token, '#'), sprintf('(?P<%s>%s)', $name, $expression), $quoted);
        }

        $quoted = rtrim($quoted, '\\/');
        if ($quoted === '') {
            $quoted = '\\/';
        }

        return ['#^' . $quoted . '$#u', $parameterNames];
    }
}
