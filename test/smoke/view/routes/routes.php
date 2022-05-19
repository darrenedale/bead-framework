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

$router->registerGet("/fizz", function(): View {
	return new View("view", ["foo" => "Fizz page",]);
});

$router->registerGet("/buzz", function(): View {
	return new View("view", ["foo" => "Buzz page",]);
});

$router->registerGet("/quux", function(): View {
	return new View("view", ["foo" => "Quux page",]);
});
