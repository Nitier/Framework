<?php

declare(strict_types=1);

namespace Framework\Http\Message;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;
    private bool $seekable;
    private bool $readable;
    private bool $writable;
    private ?array $metadata;

    /**
     * @param resource|string $body
     */
    public function __construct($body = '')
    {
        if (is_string($body)) {
            $resource = fopen('php://temp', 'rb+');
            if ($resource === false) {
                throw new RuntimeException('Unable to open php://temp stream.');
            }
            if ($body !== '') {
                fwrite($resource, $body);
                rewind($resource);
            }
            $this->resource = $resource;
        } elseif (is_resource($body)) {
            $this->resource = $body;
        } else {
            throw new RuntimeException('Stream body must be a string or resource.');
        }

        $this->metadata = stream_get_meta_data($this->resource) ?: null;
        $mode = $this->metadata['mode'] ?? '';
        $this->seekable = (bool) ($this->metadata['seekable'] ?? false);
        $this->readable = strpbrk($mode, 'r+') !== false;
        $this->writable = strpbrk($mode, 'waxc+') !== false;
    }

    public function __toString(): string
    {
        if ($this->resource === null) {
            return '';
        }

        try {
            $this->seek(0);
        } catch (RuntimeException $exception) {
            return '';
        }

        return stream_get_contents($this->resource) ?: '';
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            fclose($this->resource);
            $this->detach();
        }
    }

    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        $this->metadata = null;
        $this->seekable = false;
        $this->readable = false;
        $this->writable = false;

        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        $stats = fstat($this->resource);
        return $stats !== false ? ($stats['size'] ?? null) : null;
    }

    public function tell(): int
    {
        if ($this->resource === null) {
            throw new RuntimeException('No stream available');
        }

        $position = ftell($this->resource);
        if ($position === false) {
            throw new RuntimeException('Unable to determine stream position');
        }

        return $position;
    }

    public function eof(): bool
    {
        if ($this->resource === null) {
            return true;
        }

        return feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->seekable || $this->resource === null) {
            throw new RuntimeException('Stream is not seekable.');
        }

        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException('Unable to seek to stream position.');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function write(string $string): int
    {
        if (!$this->writable || $this->resource === null) {
            throw new RuntimeException('Stream is not writable.');
        }

        $result = fwrite($this->resource, $string);
        if ($result === false) {
            throw new RuntimeException('Unable to write to stream.');
        }

        return $result;
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function read(int $length): string
    {
        if (!$this->readable || $this->resource === null) {
            throw new RuntimeException('Stream is not readable.');
        }

        $result = fread($this->resource, $length);
        if ($result === false) {
            throw new RuntimeException('Unable to read from stream.');
        }

        return $result;
    }

    public function getContents(): string
    {
        if ($this->resource === null) {
            throw new RuntimeException('No stream available');
        }

        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }

        if ($key === null) {
            return stream_get_meta_data($this->resource);
        }

        $meta = stream_get_meta_data($this->resource);
        return $meta[$key] ?? null;
    }
}
