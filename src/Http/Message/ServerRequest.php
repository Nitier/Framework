<?php

declare(strict_types=1);

namespace Framework\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

class ServerRequest extends Message implements ServerRequestInterface
{
    private string $method;
    private UriInterface $uri;
    private ?string $requestTarget = null;
    /** @var array<string, mixed> */
    private array $serverParams;
    /** @var array<string, mixed> */
    private array $cookieParams = [];
    /** @var array<string, mixed> */
    private array $queryParams = [];
    /** @var array<string, UploadedFileInterface> */
    private array $uploadedFiles = [];
    /** @var array<string, mixed>|object|null */
    private array|object|null $parsedBody = null;
    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param string|UriInterface $uri
     * @param array<string, string|array<int, string>> $headers
     * @param StreamInterface|string|null $body
     * @param array<string, mixed> $serverParams
     */
    public function __construct(
        string $method,
        string|UriInterface $uri,
        array $headers = [],
        StreamInterface|string|null $body = null,
        string $version = '1.1',
        array $serverParams = []
    ) {
        $stream = $body instanceof StreamInterface ? $body : new Stream($body ?? '');
        parent::__construct($headers, $stream);

        $this->method = strtoupper($method);
        $this->uri = $uri instanceof UriInterface ? $uri : new Uri($uri);
        $this->protocolVersion = $version;
        $this->serverParams = $serverParams;

        if (!$this->hasHeader('Host') && $this->uri->getHost() !== '') {
            $this->setHeader('Host', $this->uri->getHost(), true);
        }
    }

    public static function fromGlobals(): self
    {
        /** @var array<string, mixed> $server */
        $server = $_SERVER;
        $methodValue = $server['REQUEST_METHOD'] ?? null;
        $method = is_string($methodValue) && $methodValue !== '' ? $methodValue : 'GET';
        $scheme = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') ? 'https' : 'http';
        $hostValue = $server['HTTP_HOST'] ?? ($server['SERVER_NAME'] ?? null);
        $host = is_string($hostValue) && $hostValue !== '' ? $hostValue : 'localhost';
        $requestUriValue = $server['REQUEST_URI'] ?? null;
        $requestUri = is_string($requestUriValue) ? $requestUriValue : '/';
        $uri = sprintf('%s://%s%s', $scheme, $host, $requestUri);

        $body = file_get_contents('php://input');
        $body = $body === false ? '' : $body;
        $parsedBody = null;
        $headers = static::marshalHeaders($server);

        $contentType = $headers['Content-Type'][0] ?? '';
        if (str_contains(strtolower($contentType), 'application/json') && $body !== '') {
            $parsed = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                /** @var array<string, mixed> $parsed */
                $parsedBody = $parsed;
            }
        } else {
            /** @var array<string, mixed> $post */
            $post = $_POST;
            $parsedBody = $post;
        }

        $files = self::filterStringKeyArray($_FILES);
        $uploadedFiles = self::normalizeFiles($files);
        $protocol = $server['SERVER_PROTOCOL'] ?? '1.1';
        if (is_string($protocol) && str_starts_with($protocol, 'HTTP/')) {
            $protocol = substr($protocol, 5);
        }
        $protocol = is_string($protocol) && $protocol !== '' ? $protocol : '1.1';

        $request = new self(
            $method,
            $uri,
            $headers,
            $body,
            $protocol,
            $server
        );

        $cookies = self::filterStringKeyArray($_COOKIE);
        $query = self::filterStringKeyArray($_GET);

        $parsedBody = self::sanitizeParsedBody($parsedBody);

        return $request
            ->withCookieParams($cookies)
            ->withQueryParams($query)
            ->withParsedBody($parsedBody)
            ->withUploadedFiles($uploadedFiles);
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, array<int, string>>
     */
    protected static function marshalHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = [self::stringifyServerValue($value)];
            }
        }

        if (isset($server['CONTENT_TYPE'])) {
            $headers['Content-Type'] = [self::stringifyServerValue($server['CONTENT_TYPE'])];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = [self::stringifyServerValue($server['CONTENT_LENGTH'])];
        }

        return $headers;
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }

        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): static
    {
        if ($requestTarget === '') {
            throw new InvalidArgumentException('Request target must be a non-empty string');
        }

        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): static
    {
        if ($method === '') {
            throw new InvalidArgumentException('HTTP method cannot be empty');
        }

        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $clone = clone $this;
        $clone->uri = $uri;
        if (!$preserveHost || !$this->hasHeader('Host')) {
            if ($uri->getHost() !== '') {
                $clone->setHeader('Host', $uri->getHost(), true);
            }
        }
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @param array<string, mixed> $cookies
     */
    /**
     * @param array<string, mixed> $cookies
     */
    public function withCookieParams(array $cookies): static
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @param array<string, mixed> $query
     */
    /**
     * @param array<string, mixed> $query
     */
    public function withQueryParams(array $query): static
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    /**
     * @return array<string, UploadedFileInterface>
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @param array<string, UploadedFileInterface> $uploadedFiles
     */
    /**
     * @param array<string, UploadedFileInterface> $uploadedFiles
     */
    public function withUploadedFiles(array $uploadedFiles): static
    {
        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;
        return $clone;
    }

    /**
     * @return array<string, mixed>|object|null
     */
    public function getParsedBody(): array|object|null
    {
        return $this->parsedBody;
    }

    /**
     * @param array<string, mixed>|object|null $data
     */
    /**
     * @param array<string, mixed>|object|null $data
     */
    public function withParsedBody($data): static
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute($name, $value): static
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withoutAttribute($name): static
    {
        if (!array_key_exists($name, $this->attributes)) {
            return clone $this;
        }

        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, UploadedFileInterface>
     */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
                continue;
            }

            if (is_array($value) && isset($value['tmp_name'])) {
                /** @var array<string, mixed> $spec */
                $spec = $value;
                $normalized[$key] = UploadedFile::createFromSpec($spec);
                continue;
            }
        }
        return $normalized;
    }

    private static function stringifyServerValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        if ($value === null) {
            return '';
        }

        if (is_resource($value)) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @param mixed $parsedBody
     * @return array<string, mixed>|object|null
     */
    private static function sanitizeParsedBody(mixed $parsedBody): array|object|null
    {
        if ($parsedBody === null || is_object($parsedBody)) {
            return $parsedBody;
        }

        if (!is_array($parsedBody)) {
            return null;
        }

        /** @var array<string, mixed> $parsedBody */
        return $parsedBody;
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<string, mixed>
     */
    private static function filterStringKeyArray(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
