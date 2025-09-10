<?php

declare(strict_types=1);

namespace Framework\General;

class Mode extends Entity
{
    public const string DEBUG = 'kernel.mode.debug';
    public function isDebug(): bool
    {
        return (bool) $this->kernel()->get(self::DEBUG);
    }

    public function isConsole(): bool
    {
        return php_sapi_name() === 'cli';
    }
}
