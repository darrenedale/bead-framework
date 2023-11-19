<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 */

use Bead\Core\WebApplication;
use Bead\View;

require_once __DIR__ . "/../../../vendor/autoload.php";

$app = new WebApplication(__DIR__ . "/..");
View::inject("pageTitle", "Bead test page");

$app->exec();
