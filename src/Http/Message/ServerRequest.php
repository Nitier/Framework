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
        /** @var array<string,mixed> $server */
        $server = $_SERVER;
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        $scheme = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') ? 'https' : 'http';
        /** @var string $host */
        $host = $server['HTTP_HOST'] ?? ($server['SERVER_NAME'] ?? 'localhost');
        /** @var string $requestUri */
        $requestUri = $server['REQUEST_URI'] ?? '/';
        $uri = sprintf('%s://%s%s', $scheme, $host, $requestUri);

        $body = file_get_contents('php://input');
        $body = $body === false ? '' : $body;
        $parsedBody = null;
        $headers = static::marshalHeaders($server);

        $contentType = $headers['Content-Type'][0] ?? '';
        if (str_contains(strtolower($contentType), 'application/json') && $body !== '') {
            $parsed = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $parsedBody = $parsed;
            }
        } else {
            $parsedBody = $_POST;
        }

        $uploadedFiles = self::normalizeFiles($_FILES);
        $protocol = $server['SERVER_PROTOCOL'] ?? '1.1';
        if (is_string($protocol) && str_starts_with($protocol, 'HTTP/')) {
            $protocol = substr($protocol, 5);
        }

        $request = new self(
            $method,
            $uri,
            $headers,
            $body,
            (string) $protocol,
            $server
        );

        return $request
            ->withCookieParams($_COOKIE ?? [])
            ->withQueryParams($_GET ?? [])
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
                $headers[$name] = [(string) $value];
            }
        }

        if (isset($server['CONTENT_TYPE'])) {
            $headers['Content-Type'] = [(string) $server['CONTENT_TYPE']];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = [(string) $server['CONTENT_LENGTH']];
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

    public function withRequestTarget($requestTarget): static
    {
        if (!is_string($requestTarget) || $requestTarget === '') {
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

    public function withMethod($method): static
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false): static
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

    public function withUploadedFiles(array $uploadedFiles): static
    {
        $this->validateUploadedFiles($uploadedFiles);
        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;
        return $clone;
    }

    public function getParsedBody(): array|object|null
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): static
    {
        if ($data !== null && !is_array($data) && !is_object($data)) {
            throw new InvalidArgumentException('Parsed body must be an array, object, or null');
        }

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
                $normalized[$key] = UploadedFile::createFromSpec($value);
                continue;
            }
        }
        return $normalized;
    }

    /**
     * @param array<string, mixed> $uploadedFiles
     */
    private function validateUploadedFiles(array $uploadedFiles): void
    {
        foreach ($uploadedFiles as $file) {
            if (!$file instanceof UploadedFileInterface) {
                throw new InvalidArgumentException('Uploaded files must implement UploadedFileInterface');
            }
        }
    }
}
