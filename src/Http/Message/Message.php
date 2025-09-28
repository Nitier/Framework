<?php

declare(strict_types=1);

namespace Framework\Http\Message;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

abstract class Message implements MessageInterface
{
    protected string $protocolVersion = '1.1';
    /** @var array<string, string[]> */
    protected array $headers = [];
    /** @var array<string, string> */
    protected array $headerNames = [];
    protected StreamInterface $body;

    /**
     * @param array<string, string|array<int, string>> $headers
     */
    public function __construct(array $headers = [], ?StreamInterface $body = null)
    {
        $this->body = $body ?? new Stream();
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion($version): static
    {
        $clone = clone $this;
        $clone->protocolVersion = (string) $version;
        return $clone;
    }

    /**
     * @return array<string, string[]>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * @return string[]
     */
    public function getHeader($name): array
    {
        $lower = strtolower($name);
        if (!isset($this->headerNames[$lower])) {
            return [];
        }

        $name = $this->headerNames[$lower];
        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value): static
    {
        $clone = clone $this;
        $clone->setHeader($name, $value, true);
        return $clone;
    }

    public function withAddedHeader($name, $value): static
    {
        if (!$this->hasHeader($name)) {
            return $this->withHeader($name, $value);
        }

        $clone = clone $this;
        $existingName = $clone->headerNames[strtolower($name)];
        $clone->headers[$existingName] = array_merge(
            $clone->headers[$existingName],
            $this->normalizeHeaderValue($value)
        );
        return $clone;
    }

    public function withoutHeader($name): static
    {
        $lower = strtolower($name);
        if (!$this->hasHeader($name)) {
            return clone $this;
        }

        $clone = clone $this;
        $originalName = $clone->headerNames[$lower];
        unset($clone->headers[$originalName], $clone->headerNames[$lower]);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    /**
     * @param string $name
     * @param string|array<int|string, string|int|float> $value
     */
    protected function setHeader(string $name, string|array $value, bool $replace = false): void
    {
        $normalized = $this->normalizeHeaderName($name);
        $values = $this->normalizeHeaderValue($value);
        $lower = strtolower($name);

        if ($replace && isset($this->headerNames[$lower])) {
            $existingName = $this->headerNames[$lower];
            unset($this->headers[$existingName]);
        }

        if (isset($this->headerNames[$lower]) && !$replace) {
            $existingName = $this->headerNames[$lower];
            $this->headers[$existingName] = array_merge($this->headers[$existingName], $values);
            return;
        }

        $this->headerNames[$lower] = $normalized;
        $this->headers[$normalized] = $values;
    }

    /**
     * @param string $name
     */
    private function normalizeHeaderName(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9\-]+$/', $name)) {
            throw new \InvalidArgumentException(sprintf('Invalid header name provided: %s', $name));
        }

        return $name;
    }

    /**
     * @param string|array<int|string, string|int|float> $value
     * @return array<int, string>
     */
    private function normalizeHeaderValue(string|array $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if ($value === []) {
            throw new \InvalidArgumentException('Header value cannot be empty');
        }

        $values = [];
        foreach ($value as $item) {
            $values[] = trim((string) $item, " \t");
        }

        return $values;
    }
}
