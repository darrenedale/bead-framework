<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 */

use Bead\View;
use Bead\Web\Application;

require_once __DIR__ . "/../../../vendor/autoload.php";

$app = new Application(__DIR__ . "/..");
View::inject("pageTitle", "Bead test page");

$app->exec();
