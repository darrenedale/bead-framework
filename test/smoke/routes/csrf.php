<?php

use Equit\Contracts\Router;
use Equit\Exceptions\CsrfTokenVerificationException;
use Equit\Request;
use Equit\View;
use Equit\WebApplication;

/** @var Router $router */

$router->registerGet("/csrf", function(): View {
	return new View("csrf");
});

$router->registerPost("/csrf", function(Request $request): View {
	View::inject(["messages" => ["CSRF Passed",],]);
	return new View("csrf", ["text" => $request->postData("text") ?? "",]);
});

$router->registerGet("/csrf/regenerate", function (): View {
	WebApplication::instance()->regenerateCsrf();
	return new View("csrf");
});
