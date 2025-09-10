<?php
declare(strict_types=1);

use Framework\Kernel;

require_once implode(DIRECTORY_SEPARATOR, [
    dirname(__DIR__),
    'vendor',
    'autoload.php'
]);

$kernel = new Kernel();
$kernel->loadApplication(implode(
    DIRECTORY_SEPARATOR,
    [
        dirname(__DIR__),
        'test-app'
    ]
));