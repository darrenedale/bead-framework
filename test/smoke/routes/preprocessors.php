<?php

declare(strict_types=1);

use Bead\Contracts\Router;
use Bead\View;
use Bead\Web\Request;

/** @var Router $router */

$router->registerGet("/preprocessor/timestamp", fn(Request $request): View => new View("preprocessor-timestamp", ["timestamp" => (int) $request->postData("bead-request-timestamp"),]));
