<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 */

use Bead\Contracts\Logger;
use Bead\Logging\FileLogger;
use Bead\View;
use Bead\WebApplication;

require_once __DIR__ . "/../../../vendor/autoload.php";

$app = new WebApplication(__DIR__ . "/..");
$log = new FileLogger(__DIR__ . "/../logs/bead-smoke-test.log", FileLogger::FlagAppend);
$log->setLevel(Logger::DebugLevel);
$app->bindService(Logger::class, $log);
$app->setPluginsDirectory("plugins");
View::inject("pageTitle", "Bead test page");

$app->exec();
