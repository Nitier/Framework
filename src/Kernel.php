<?php

declare(strict_types=1);

namespace Framework;

class Kernel
{
    /**
     * Kernel constructor.
     * Initializes the kernel by setting up paths and building the container.
     * @param array<mixed> $settings Initial settings array including root path
     */
    public function __construct(array $settings = [])
    {
        var_dump($settings);
    }
}
