#!/usr/bin/env php
<?php
declare(strict_types=1);

use Framework\Console;
use Framework\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$kernel = new Kernel();
$kernel->loadApplication(dirname(__DIR__));

/** @var Console $app */
$app = $kernel->get(Console::class);
exit($app->run());
