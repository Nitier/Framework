<?php

use Framework\Kernel;
use Framework\General\Environment;

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