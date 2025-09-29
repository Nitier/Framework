<?php
use App\Command\ExampleCommand;
use Framework\Console;

return [
    // Test Commands
    Console::COMMANDS => [
        ExampleCommand::class,
    ],
];
