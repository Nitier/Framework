<?php

declare(strict_types=1);

namespace Framework\General;

use Framework\Kernel;

abstract class Entity
{
    protected Kernel $kernel;
    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Returns the Kernel instance from the kernel container.
     * @return Kernel
     */
    protected function kernel(): Kernel
    {
        return $this->kernel;
    }

    /**
     * Returns the Path instance from the kernel container.
     * @return Path
     */
    protected function path(): Path
    {
        /** @var Path $path */
        $path = $this->kernel->get(Path::class);
        return $path;
    }

    /**
     * Returns the Mode instance from the kernel container.
     * @return Mode
     */
    protected function mode(): Mode
    {
        /** @var Mode $mode */
        $mode = $this->kernel->get(Mode::class);
        return $mode;
    }
}
