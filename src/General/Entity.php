<?php

declare(strict_types=1);

namespace Framework\General;

use Framework\Kernel;

/**
 * Base helper class for framework services that require access to the kernel.
 *
 * Many small helpers (environment, path resolver, mode checker) are resolved
 * from the container and frequently need to access other services. This class
 * provides a protected façade so that descendants can easily query the kernel
 * and shared services without exposing the kernel to the public API.
 */
abstract class Entity
{
    protected Kernel $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Retrieve the kernel instance backing the current service.
     */
    protected function kernel(): Kernel
    {
        return $this->kernel;
    }

    /**
     * Shortcut to access the shared `Path` value object from the container.
     */
    protected function path(): Path
    {
        /** @var Path $path */
        $path = $this->kernel->get(Path::class);
        return $path;
    }

    /**
     * Shortcut to resolve the runtime mode helper (`Mode`) from the container.
     */
    protected function mode(): Mode
    {
        /** @var Mode $mode */
        $mode = $this->kernel->get(Mode::class);
        return $mode;
    }
}
