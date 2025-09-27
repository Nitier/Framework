<?php
use Framework\Console;
use Framework\General\Mode;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;

return [
    Console::class => function (ContainerInterface $c): Console {
        $app = new Console($c->get(Console::NAME), $c->get(Console::VERSION));
        foreach ($c->get(Console::COMMANDS) ?? [] as $cmd) {
            $instance = is_string($cmd) ? $c->get($cmd) : $cmd;
            if ($instance instanceof Command) {
                $app->add($instance);
            }
        }
        return $app;
    },
    Console::NAME => 'kernel.console',
    Console::VERSION => '1.0.0',
    Console::COMMANDS => [
        // Example: \App\Commands\ExampleCommand::class,
    ],
];
