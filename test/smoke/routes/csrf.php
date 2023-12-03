<?php

declare(strict_types=1);

use Bead\Contracts\Router;
use Bead\Exceptions\Http\CsrfTokenVerificationException;
use Bead\Facades\WebApplication as WebApp;
use Bead\View;
use Bead\Web\Request;

/** @var Router $router */

$router->registerGet("/csrf", function(): View {
	return new View("csrf");
});

$router->registerPost("/csrf", function(Request $request): View {
	View::inject(["messages" => ["CSRF Passed",],]);
	return new View("csrf", ["text" => $request->postData("text") ?? "",]);
});

$router->registerGet("/csrf/regenerate", function (): View {
    WebApp::regenerateCsrf();
	return new View("csrf");
});

$router->registerGet("/csrf/fail", function(Request $request): void {
    throw new CsrfTokenVerificationException($request);
});