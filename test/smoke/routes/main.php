<?php

use Equit\Contracts\Router;
use Equit\View;

/** @var Router $router */

$router->registerGet("/", function(): View {
	return new View("view", ["foo" => "Home page",]);
});

$router->registerGet("/foo", function(): View {
	return new View("view", ["foo" => "Foo page",]);
});

$router->registerGet("/bar", function(): View {
	return new View("view", ["foo" => "Bar page",]);
});
