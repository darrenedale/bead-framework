<?php

declare(strict_types=1);

return [
    // list of sources to use for the environment.
    //
    // sources are defined in the "sources" element below
    "environments" => ["env", "app",],

    // define the environment sources available to the app
    //
    // each entry is keyed with the name of the source (which can be used in environments above). the value
    // is the configuration for the source. Each configuration must have at least the "driver" element
    // specified, which may be "file", "array" or "environment". It may have other elements defined, which
    // specify the configuration for the source
    "sources" => [
        // import the actual environment variables
        //
        // the "environment" driver creates a \Bead\Environment\Sources\Environment source.
        //
        // There's little value in having more than one source using this driver
        "env" => [
            "driver" => "environment",
        ],

        // import environment variables from the file ".env" inside the application's root dir
        //
        // The "file" driver creates a \Bead\Environment\Sources\File source
        "app" => [
            "driver" => "file",
            "path" => ".env",
        ],

        // import environment variables from a PHP array. Keys in the array must all be strings.
        //
        // The "array" driver creates a \Bead\Environment\Sources\StaticArray source
        "fixed" => [
            "driver" => "array",
            "env" => [
                // "bead" => "framework",
            ],
        ],
    ]
];
