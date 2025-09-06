<?php
declare(strict_types=1);

use Framework\Kernel;

require_once implode(DIRECTORY_SEPARATOR,[
    dirname(__DIR__),
    'vendor',
    'autoload.php'
]);

new Kernel([
    dirname(__DIR__)
]);