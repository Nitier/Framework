<?php

declare(strict_types=1);

namespace Framework\General;

/**
 * Facade around the application runtime mode.
 *
 * The mode is stored inside the container so that the bootstrap logic can toggle
 * behaviours (for example, enabling verbose exception output during debugging).
 * This lightweight helper exposes intent-centric checks instead of scattering
 * container lookups throughout the code base.
 */
class Mode extends Entity
{
    /**
     * Service identifier that stores the "debug" flag inside the container.
     */
    public const string DEBUG = 'kernel.mode.debug';

    /**
     * Determine whether the framework runs in debug mode.
     */
    public function isDebug(): bool
    {
        return (bool) $this->kernel()->get(self::DEBUG);
    }

    /**
     * Helper flag used by the console integration to detect CLI invocations.
     */
    public function isConsole(): bool
    {
        return php_sapi_name() === 'cli';
    }
}
