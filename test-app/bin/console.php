#!/usr/bin/env php
<?php
declare(strict_types=1);

use Framework\Kernel;
use Framework\Console;

require_once implode(DIRECTORY_SEPARATOR, [
    dirname(__DIR__, 2),
    'vendor',
    'autoload.php'
]);

$kernel = new Kernel();
$kernel->loadApplication(implode(
    DIRECTORY_SEPARATOR,
    [
        dirname(__DIR__, 2),
        'test-app'
    ]
));
/** @var Console $app */
$app = $kernel->get(Console::class);
exit($app->run());
