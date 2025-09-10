<?php

declare(strict_types=1);

namespace Framework\General;

class Path
{
    private string $base;

    public function __construct(string $base)
    {
        $this->base = rtrim($base, DIRECTORY_SEPARATOR);
    }
    /**
     * Returns the base path of the application.
     * @return string
     */
    public function base(): string
    {
        return $this->base;
    }

    /**
     * Returns the path of a file or directory relative to the base path.
     * @param string ...$segments The segments of the path
     * @return string The absolute path
     * @example $path->get('app', 'controllers', 'HomeController.php');
     */
    public function get(...$segments): string
    {
        $segments = array_map(
            fn($segment) => trim($segment, DIRECTORY_SEPARATOR),
            $segments
        );
        $segments = array_filter($segments, fn($segment) => trim($segment) !== '');
        return sprintf(
            '%s%s%s',
            $this->base,
            DIRECTORY_SEPARATOR,
            implode(
                DIRECTORY_SEPARATOR,
                $segments
            )
        );
    }
}
