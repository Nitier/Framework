<?php

use Framework\Kernel;
use Framework\General\Environment;

/**
 * Minimal kernel bootstrap configuration.
 *
 * The definition registers the application configuration directory and wires
 * the `Environment` service so that `.env` files are parsed during kernel
 * bootstrapping.
 */
return [
    'application' => [
        'config' => 'config'
    ],
    Environment::class => static function (Kernel $kernel): Environment {
        $environment = new Environment($kernel);
        $environment->load();
        return $environment;
    }
    
];
