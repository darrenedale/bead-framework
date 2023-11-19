<?php

use Bead\Logging\FileLogger;

return [
    // which logging driver to use. options are "file", "stdout", "stdin" and "null". composite is also available,
    // but currently requires you to bind the service manually rather than configuring it here
    "driver"=> "file",

    // the configuration for the file logger
    "file" => [
        "path" => "data/logs/bead-smoke-test.log",
        "flags" => FileLogger::FlagAppend
    ],
];
