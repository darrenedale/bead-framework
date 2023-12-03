<?php

/** @var Router $router */

declare(strict_types=1);

use Bead\Contracts\Environment as EnvironmentContract;
use Bead\Contracts\Router;
use Bead\Facades\Application as App;
use Bead\View;

$router->registerGet("/env", fn( ) => new View("environment", ["env" => App::get(EnvironmentContract::class),]));
