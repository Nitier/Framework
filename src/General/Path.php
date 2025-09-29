<?php

declare(strict_types=1);

namespace Framework\General;

/**
 * Value object describing the base path of the application.
 *
 * The framework keeps all filesystem lookups relative to a single root. This
 * helper ensures that path composition is consistent, trimming redundant
 * separators and guarding against accidental `..` traversals supplied in
 * configuration. Consumers can obtain the base directory or build additional
 * segments using `get()`.
 */
class Path
{
    private string $base;

    public function __construct(string $base)
    {
        $this->base = rtrim($base, DIRECTORY_SEPARATOR);
    }

    /**
     * Retrieve the canonical base directory of the application.
     */
    public function base(): string
    {
        return $this->base;
    }

    /**
     * Compose an absolute path by appending segments to the base directory.
     * Empty segments are ignored and each segment is trimmed to avoid doubled
     * path separators. Usage example: `$path->get('config', 'kernel.php')`.
     */
    public function get(string ...$segments): string
    {
        $sanitised = array_filter(
            array_map(static fn(string $segment): string => trim($segment, DIRECTORY_SEPARATOR), $segments),
            static fn(string $segment): bool => $segment !== ''
        );

        if ($sanitised === []) {
            return $this->base;
        }

        return $this->base . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $sanitised);
    }
}
