<?php

declare(strict_types=1);

namespace Framework\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    private string $scheme = '';
    private string $userInfo = '';
    private string $host = '';
    private ?int $port = null;
    private string $path = '';
    private string $query = '';
    private string $fragment = '';

    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $components = parse_url($uri);
            if ($components === false) {
                throw new InvalidArgumentException(sprintf('Unable to parse URI: %s', $uri));
            }

            $this->scheme = isset($components['scheme']) ? strtolower($components['scheme']) : '';
            $this->host = isset($components['host']) ? strtolower($components['host']) : '';
            $this->port = $components['port'] ?? null;
            $this->userInfo = isset($components['user'])
                ? $components['user'] . (isset($components['pass']) ? ':' . $components['pass'] : '')
                : '';
            $this->path = $components['path'] ?? '';
            $this->query = $components['query'] ?? '';
            $this->fragment = $components['fragment'] ?? '';
        }
    }

    public function __toString(): string
    {
        return self::createUriString(
            $this->scheme,
            $this->getAuthority(),
            $this->path,
            $this->query,
            $this->fragment
        );
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        $authority = $this->host;

        if ($authority === '') {
            return '';
        }

        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null && $this->port !== $this->getDefaultPort($this->scheme)) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): static
    {
        $clone = clone $this;
        $clone->scheme = strtolower($this->filterScheme($scheme));
        return $clone;
    }

    public function withUserInfo(string $user, ?string $password = null): static
    {
        $clone = clone $this;
        $clone->userInfo = $password === null ? $user : $user . ':' . $password;
        return $clone;
    }

    public function withHost(string $host): static
    {
        $clone = clone $this;
        $clone->host = strtolower($this->filterHost($host));
        return $clone;
    }

    public function withPort(?int $port): static
    {
        $clone = clone $this;
        $clone->port = $this->filterPort($port);
        return $clone;
    }

    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->path = $this->filterPath($path);
        return $clone;
    }

    public function withQuery(string $query): static
    {
        $clone = clone $this;
        $clone->query = $this->filterQueryAndFragment($query);
        return $clone;
    }

    public function withFragment(string $fragment): static
    {
        $clone = clone $this;
        $clone->fragment = $this->filterQueryAndFragment($fragment);
        return $clone;
    }

    private function filterScheme(string $scheme): string
    {
        if ($scheme === '') {
            return '';
        }

        if (!preg_match('/^[a-z][a-z0-9+\-.]*$/i', $scheme)) {
            throw new InvalidArgumentException(sprintf('Invalid scheme: %s', $scheme));
        }

        return $scheme;
    }

    private function filterHost(string $host): string
    {
        if ($host === '') {
            return '';
        }

        if (!preg_match('/^([a-z0-9-_]+\.)*[a-z0-9-_]+$/i', $host)) {
            return $host;
        }

        return $host;
    }

    private function filterPort(?int $port): ?int
    {
        if ($port === null) {
            return null;
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Port must be between 1 and 65535');
        }

        return $port;
    }

    private function filterPath(string $path): string
    {
        return $path === '' ? '' : $this->percentEncode($path, '/:@');
    }

    private function filterQueryAndFragment(string $value): string
    {
        return $value === '' ? '' : $this->percentEncode($value, '/?:@');
    }

    private function percentEncode(string $value, string $reservedChars): string
    {
        return preg_replace_callback(
            '/([^a-zA-Z0-9_\-\.~' . preg_quote($reservedChars, '/') . '])/u',
            static fn(array $matches): string => rawurlencode($matches[0]),
            $value
        ) ?? $value;
    }

    private static function createUriString(
        string $scheme,
        string $authority,
        string $path,
        string $query,
        string $fragment
    ): string {
        $uri = '';

        if ($scheme !== '') {
            $uri .= $scheme . ':';
        }

        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        if ($path !== '') {
            if ($authority !== '' && str_starts_with($path, '/')) {
                $path = '/' . ltrim($path, '/');
            }
            $uri .= $path;
        }

        if ($query !== '') {
            $uri .= '?' . $query;
        }

        if ($fragment !== '') {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    private function getDefaultPort(string $scheme): ?int
    {
        return match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => null,
        };
    }
}
