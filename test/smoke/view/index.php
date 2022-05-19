<?php

use Equit\View;
use Equit\WebApplication;

require_once __DIR__ . "/../bootstrap.php";

$app = new WebApplication(__DIR__);
$app->setPluginsDirectory("plugins");
View::inject("pageTitle", "View Test Page");

$app->exec();
