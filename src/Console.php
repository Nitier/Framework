<?php

declare(strict_types=1);

namespace Framework;

class Console extends \Symfony\Component\Console\Application
{
    public const string COMMANDS = 'console.commands';
    public const string NAME = 'console.name';
    public const string VERSION = 'console.version';
}
