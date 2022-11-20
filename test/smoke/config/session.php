<?php

return [
    // which session handler to use - options are "file" and "php" (so far)
    "handler" => "file",

    // how long after creation/regeneration does the session ID need to be regenerated
    "id-regeneration-period" => 300,

    // how long after expiry (i.e. regeneration) should an old session ID be accepted (and upgraded to the new ID)?
    "expired.grace-period" => 60,

    // config for the "file" session handler
    "handlers.file.directory" => "data/session",
];
