<?php

use Bead\Core\Binders\Crypter;
use Bead\Core\Binders\Logger;
use Bead\Web\Preprocessors\CheckCsrfToken;
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
    ],

    // define additional preprocessors that get to see the request before it's routed (note that by default the
    // CheckCsrfToken preprocessor is always used
    "preprocessors" => [
        AddRequestTimestamp::class
    ],
];
