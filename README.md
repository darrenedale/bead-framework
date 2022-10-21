# Bead

A basic MVC application framework for PHP.

[![PHP 8.0 unit tests](https://github.com/darrenedale/bead-framework/actions/workflows/run-tests-php8.0.yml/badge.svg)](https://github.com/darrenedale/bead-framework/actions/workflows/run-tests-php8.0.yml)
[![PHP 8.1 unit tests](https://github.com/darrenedale/bead-framework/actions/workflows/run-tests-php8.1.yml/badge.svg)](https://github.com/darrenedale/bead-framework/actions/workflows/run-tests-php8.1.yml)

## Introduction

Bead is a simple MVC framework.

## WebApplication

Every web application that uses bead must create an instance of this singleton class. It is the core of your app,
providing a bunch of useful features such as access to configuration details, plugin loading, routing of requests,
event registration and dispatch, error handling and sending the response. In most cases you'll probably want to create a
subclass and reimplement the constructor and/or `exec()` methods to perform your application-specific initialisation.
The application is executed by calling the `exec()` method from your main `index.php` script.

During initialisation in `exec()` the `WebApplication` loads your app's configuration, plugins and routes. Routes are
registered with your app's router, which by default is an instance of `\Equit\Router`. You can provide your own router
by calling `WebApplication::setRouter()` with an object that implements the `\Equit\Contracts\Router` contract either 
from your custom WebApplication subclass's constructor, or from within your `index.php` file after you have created your
`WebApplication` instance (and before you call `exec()`, obviously).

The WebApplication singleton is always available from the `WebApplication::instance()` static method.

## Routes

Routes are loaded from route files stored in a subdirectory of your app's source, `/routes` by default. Each route
file is a standard PHP file whose purpose is to register routes with the router. Route files are loaded sandboxed by an
anonymous function - the only variables available to the route files are `$app`, the WebApplication instance (for
convenience); `$router`, the Router instance; and the PHP superglobals.

The default router uses HTTP methods and request path info to route requests. For example, you would call
`$router->registerGet()` to register a route for the GET HTTP method, or `$router->registerPost()` to register a route
for the POST HTTP method. The following registration methods are available in the default router:
- `register($path, $methods, $handler)` - register a handler for a path with a specified set of HTTP methods
- `registerGet($path, $handler)` - register a handler for a path with the GET HTTP method only
- `registerPost($path, $handler)` - register a handler for a path with the POST HTTP method only
- `registerPut($path, $handler)` - register a handler for a path with the POST HTTP method only
- `registerHead($path, $handler)` - register a handler for a path with the POST HTTP method only
- `registerDelete($path, $handler)` - register a handler for a path with the POST HTTP method only
- `registerConnect($path, $handler)` - register a handler for a path with the POST HTTP method only
- `registerOptions($path, $handler)` - register a handler for a path with the POST HTTP method only
- `registerPatch($path, $handler)` - register a handler for a path with the POST HTTP method only
- `registerPatch($path, $handler)` - register a handler for a path with the POST HTTP method only
- `registerAny($path, $handler)` - register a handler for a path with any HTTP method

You can organise your route files any way that makes sense for your project. There is no artificial limit on the number
of route files you can define.

Route handlers can be any callable. Typically you will define controllers with methods to use as route handlers. The 
default router will instantiate a controller instance and call the specified method on that instance if you provide a
handler of the form

    $router->registerGet("/some/path", [MyController::class, "theRequestHandler"]);

The controller must have a default constructor for this to work. If your controller requires constructor arguments, or
you want to use a specific instance of the controller, you can do so:

    $router->registerGet("/some/path", [new MyController($arg), "theRequestHandler"]);

Any other callable expression is also usable:

    $router->registerGet("/", fn() => new View("home"));
    $router->registerGet("/", "home_page_handler");     // home_page_handler() is a function defined by your app

The default router can provide arguments to your route handlers. By using path segments enclosed using `{braces}` you
can extract portions of the URL path to provide to your handlers as arguments. The router will match segment names to
parameter names in your handler and pass the value extracted from the URL as that argument to your handler. It will also
convert the argument where possible based on the type hint for the parameter in your handler. For example, defining
the route

    $router->registerGet("/article/{articleId}/author/{authorId}/remove", function (int $articleId, int $authorId) {});

an incoming HTTP GET request for `/article/333/author/200/remove` will call the handler with (int) 333 for `$articleId`
and (int) 200 as `$authorId` as its arguments.

In any route handler, you can also type-hint a parameter (of any name) with the `\Equit\Request` type and the handler
will receive the incoming Request as the argument for that parameter.

## Plugin

This is a base class for components that augment the functionality of the application. Typically, plugins will register
event handlers with the `WebApplication` singleton to respond to events in your app. For example, you could use a plugin
to listen for the "application.handlerequest.requestreceived" event and throw a `NotAuthorisedException` if it detects 
the request is from a banned IP address.

All plugins located in your plugins directory (`/app/plugins` by default) are loaded and instantiated for every request
to your application. You should therefore keep your plugin constructors relatively simple to avoid performance hits.

## Request

An encapsulation of a request from the client. The call to `WebApplication::exec()` constructs an instance of this class
by examining the `$_GET`, `$_POST`, `$_FILES` and `$_SERVER` superglobals. The incoming request is always available by
calling the `WebApplication` object's `originalRequest()` method.

The request class provides access to the URL parameters, POST data and uploaded files that were provided with the client
request. It also provides access to the HTTP headers.

## Views

Views are plain PHP files, stored in the `/views` directory. You can structure your views in whatever way makes sense
for your project. Views are identified using dot notation. To instantiate a view pass its name to the `View` constuctor -
for example the view stored in `/views/users/edit.php` would be instantiated using `new View("users.edit")` Note that
the base `/views` directory is not included in the name, nor is the `.php` fiole extension.

You can pass data to views using a second argument to the constructor. Data is passed as an assiciative array. The view
will receive a set of variables named after the keys in the array. All views also receive two convenience variables:
`$app` is the current running `WebApplication` instance; and `$data` is the array of data for the view passed to the
constructor. Views are rendered sandboxed by an anonymous function - no global variables are available to views, only
`$app`, the view data and the PHP superglobals.

To keep your pages consistent, each view can use a layout. Layouts are no different from regular views, except that (in
order to be useful), they should contain named sections. Views intended to be used as layouts should have one or more
calls to `View::yieldSection("section-name")`. Each named section defines a location where views can add content. Views
indicate which layout they use by calling `View::layout("layouts.layout-name")` at the start of the view, and provide
content for the layout's sections by placing content between calls to `View::section("section-name")` and
`View::endSection()`. When the layout yields the section, the content placed in it by the view will be rendered. Layouts
can define as many named sections as they wish. Layouts can be stored anwyhere in your `/views` tree, but it is
recommended that you use `/views/layouts` for clarity.

You can build re-usable parts for your views in two ways: one way is to call `View::include("view-name")` to include the
content of another view directly; the other is to call `View::component("component-name")` and `View::endComponent()` to 
include the content of another view as a component. There are only small differences between using includes and
components:

- includes inherit all the view data from the view that includes them
- components have their own data context and can have content provided to them in _slots_

In general, if the view fragment you wish to include is a relatively static part of your page (e.g. a navigation) that
you're just abstracting for ease of housekeeping, using `View::include()` is probably most appropriate. If you're
including a configurable component that requires parameters (e.g. a type of user input control) you're probably best using
components. For full details, see the separate documentation for the `View` class.

All `View`s fulfil the `Response` contract, so you can simply return a `View` instance from your handler methods.

## Database and Models

The framework contains a very simple ORM to make it easy to query and update the data in your app's database in most
cases. The `Equit\Database\Connection` class extends the built-in `PDO` class with a few static methods. The `Model`
base class makes it easy to create code representations of the data stored in your database. In the simplest cases, you
just need to create a subclass and fill the `$properties` static member with the names and types of the columns in the
table the model represents, and the `$table` static member with the name of the database table.

## Validation

Validation will feel familiar to anyone who has used Laravel's validation framework. You validate data by creating a
`Equit\Validation\Validator` and giving it a set of rules to apply and the data to apply them to. If the data is valid,
the Validator will return `true` from `passes()`. If it returns `false` it will provide a set of error messages from
`errors()`.
