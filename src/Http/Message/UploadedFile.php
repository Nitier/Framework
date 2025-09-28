<?php

declare(strict_types=1);

namespace Framework\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class UploadedFile implements UploadedFileInterface
{
    private const ERROR_MESSAGES = [
        UPLOAD_ERR_OK => 'There is no error, the file uploaded successfully',
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
    ];

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

    public static function createFromSpec(array $value): UploadedFileInterface
    {
        if (!isset($value['error'])) {
            throw new InvalidArgumentException('Invalid uploaded file specification: missing error key');
        }

        $stream = new Stream();
        if (!empty($value['tmp_name']) && is_uploaded_file($value['tmp_name'])) {
            $resource = fopen($value['tmp_name'], 'rb');
            if ($resource === false) {
                throw new RuntimeException('Unable to open temporary uploaded file');
            }
            $stream = new Stream($resource);
        }

        return new self(
            $stream,
            isset($value['size']) ? (int) $value['size'] : null,
            (int) $value['error'],
            $value['name'] ?? null,
            $value['type'] ?? null
        );
    }

    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new RuntimeException('Uploaded file has already been moved');
        }

        return $this->stream;
    }

    public function moveTo($targetPath): void
    {
        if ($this->moved) {
            throw new RuntimeException('Uploaded file has already been moved');
        }

        if (!is_string($targetPath) || $targetPath === '') {
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
