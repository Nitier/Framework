<?php

declare(strict_types=1);

namespace Framework\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class UploadedFile implements UploadedFileInterface
{
    private StreamInterface $stream;
    private ?int $size;
    private int $error;
    private ?string $clientFilename;
    private ?string $clientMediaType;
    private bool $moved = false;

    public function __construct(
        StreamInterface $stream,
        ?int $size,
        int $error,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ) {
        $this->stream = $stream;
        $this->size = $size;
        $this->error = $error;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
    }

    /**
     * @param array<string, mixed> $value
     */
    public static function createFromSpec(array $value): UploadedFileInterface
    {
        if (!isset($value['error'])) {
            throw new InvalidArgumentException('Invalid uploaded file specification: missing error key');
        }

        $stream = new Stream();
        $tmpName = $value['tmp_name'] ?? null;
        if (is_string($tmpName) && $tmpName !== '' && is_uploaded_file($tmpName)) {
            $resource = fopen($tmpName, 'rb');
            if ($resource === false) {
                throw new RuntimeException('Unable to open temporary uploaded file');
            }
            $stream = new Stream($resource);
        }

        $size = $value['size'] ?? null;
        $clientFilename = $value['name'] ?? null;
        $clientMediaType = $value['type'] ?? null;
        $errorValue = $value['error'];
        if (!is_numeric($errorValue)) {
            throw new InvalidArgumentException('Invalid uploaded file specification: error must be numeric');
        }
        $error = (int) $errorValue;

        return new self(
            $stream,
            is_numeric($size) ? (int) $size : null,
            $error,
            is_string($clientFilename) ? $clientFilename : null,
            is_string($clientMediaType) ? $clientMediaType : null
        );
    }

    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new RuntimeException('Uploaded file has already been moved');
        }

        return $this->stream;
    }

    public function moveTo(string $targetPath): void
    {
        if ($this->moved) {
            throw new RuntimeException('Uploaded file has already been moved');
        }

        if ($targetPath === '') {
            throw new InvalidArgumentException('Target path must be a non-empty string');
        }

        $stream = $this->getStream();
        $resource = $stream->detach();
        if (!is_resource($resource)) {
            throw new RuntimeException('Unable to retrieve stream resource');
        }

        $target = fopen($targetPath, 'wb');
        if ($target === false) {
            throw new RuntimeException(sprintf('Unable to open target path: %s', $targetPath));
        }

        stream_copy_to_stream($resource, $target);
        fclose($target);

        $this->moved = true;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}
