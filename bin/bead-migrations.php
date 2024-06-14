<?php

declare(strict_types=1);

require_once $_composer_autoload_path ?? __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/Migration.php";

(new Migrations($args))->exec();
