<?php

use Bead\Core\Binders\Crypter;
use Bead\Core\Binders\Environment;
use Bead\Core\Binders\Logger;
use Bead\Web\RequestProcessors\CheckMaintenanceMode;
use Bead\Web\RequestProcessors\LogRequestDuration;
use BeadTests\smoke\app\Web\Preprocessors\AddRequestTimestamp;

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
        Environment::class,
    ],

    // define additional pre- and post-processors that get to see the request before it's routed (note that by default
    // the CheckCsrfToken preprocessor is added by the app when it boots)
    "processors" => [
        AddRequestTimestamp::class,
        CheckMaintenanceMode::class,
        LogRequestDuration::class,
    ],
];
