<?php

use Bead\Core\Binders\Crypter;
use Bead\Core\Binders\Logger;

return [
    // set this to true to put the app into debug mode. this can be queried from the Application singleton, and your
    // controllers and services can act accordingly
    "debugmode"=> true,

    // configure whether plugins are loaded, and where they're loaded from
    "plugins" => [
        "enabled" => false,
    ],

    // define the service binders that your app loads during initialisation
    "binders" => [
        Logger::class,
        Crypter::class,
    ],
];
