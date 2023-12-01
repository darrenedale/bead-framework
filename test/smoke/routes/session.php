<?php

/** @var Router $router */

use Bead\Contracts\Router;
use Bead\Request;
use Bead\Facades\Session;
use Bead\View;
use BeadTests\smoke\app\Controllers\SessionController;

$router->registerGet("/session", [SessionController::class, "showDetails",]);

$router->registerGet("/prefixed-session", [SessionController::class, "prefixedSession",]);

$router->registerGet("/session/transient/refresh", [SessionController::class, "refreshTransientData",]);

$router->registerGet("/session/transient/add", [SessionController::class, "addTransientData",]);

$router->registerGet("/session/set", [SessionController::class, "set",]);
