<?php

declare(strict_types=1);

namespace Framework;

/**
 * Thin wrapper around Symfony's Console application used for DI integration.
 *
 * The constants provide container keys so that service providers can register
 * command classes, the application name and its version in a consistent way.
 */
class Console extends \Symfony\Component\Console\Application
{
    /** Container key holding an array of command service IDs. */
    public const string COMMANDS = 'console.commands';
    /** Container key for the console application's name. */
    public const string NAME = 'console.name';
    /** Container key for the console application's version string. */
    public const string VERSION = 'console.version';
}
