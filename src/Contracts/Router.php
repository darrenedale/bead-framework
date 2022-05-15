<?php

/**
 * @author Darren Edale
 * @version 0.9.1
 */

namespace Equit\Contracts;

use Equit\Request;

/**
 * Contract for classes that want to route requests for a WebApplication.
 */
interface Router
{
	/** @var string Use to register a handler for a route requested with the HTTP GET method. */
	public const GetMethod = "GET";

	/** @var string Use to register a handler for a route requested with the HTTP HEAD method. */
	public const HeadMethod = "HEAD";

	/** @var string Use to register a handler for a route requested with the HTTP POST method. */
	public const PostMethod = "POST";

	/** @var string Use to register a handler for a route requested with the HTTP PUT method. */
	public const PutMethod = "PUT";

	/** @var string Use to register a handler for a route requested with the HTTP DELETE method. */
	public const DeleteMethod = "DELETE";

	/** @var string Use to register a handler for a route requested with the HTTP CONNECT method. */
	public const ConnectMethod = "CONNECT";

	/** @var string Use to register a handler for a route requested with the HTTP OPTIONS method. */
	public const OptionsMethod = "OPTIONS";

	/** @var string Use to register a handler for a route requested with the HTTP PATCH method. */
	public const PatchMethod  = "PATCH";

	/** @var string Use to register a handler for a route requested with any HTTP method. */
	public const AnyMethod = "";

	/**
	 * Route a given request.
	 *
	 * @param \Equit\Request $request The request to route.
	 *
	 * @throws \Equit\Exceptions\UnroutableRequestException if no registered route can be found for the request.
	 */
	public function route(Request $request): void;

	/**
	 * Register a route with the router.
	 *
	 * The handler can be any php `callable` or a tuple of [class-name, method-name] strings. If the latter, the handler
	 * is the given method invoked on a new instance of the named class (for non-static methods) or the static method
	 * if it's a static method. The router must instantiate the class, and the class must be default-constructible.
	 *
	 * @param string $route The route to register.
	 * @param string|array<string> $methods The HTTP method(s) to accept on the route.
	 * @param callable|array<class-string, string> $handler The handler to call when the route matches a request.
	 *
	 * @throws \Equit\Exceptions\ConflictingRouteException if a matching route is already registered.
	 */
	public function register(string $route, $methods, $handler): void;

	/**
	 * Register a route with the router that responds only to GET requests.
	 *
	 * @param string $route The route to register.
	 * @param callable|array<class-string, string> $handler The handler to call when the route matches a request.
	 *
	 * @throws \Equit\Exceptions\ConflictingRouteException if a matching route is already registered.
	 */
	public function registerGet(string $route, $handler): void;

	/**
	 * Register a route with the router that responds only to HEAD requests.
	 *
	 * @param string $route The route to register.
	 * @param callable|array<class-string, string> $handler The handler to call when the route matches a request.
	 *
	 * @throws \Equit\Exceptions\ConflictingRouteException if a matching route is already registered.
	 */
	public function registerHead(string $route, $handler): void;

	/**
	 * Register a route with the router that responds only to POST requests.
	 *
	 * @param string $route The route to register.
	 * @param callable|array<class-string, string> $handler The handler to call when the route matches a request.
	 *
	 * @throws \Equit\Exceptions\ConflictingRouteException if a matching route is already registered.
	 */
	public function registerPost(string $route, $handler): void;

	/**
	 * Register a route with the router that responds only to PUT requests.
	 *
	 * @param string $route The route to register.
	 * @param callable|array<class-string, string> $handler The handler to call when the route matches a request.
	 *
	 * @throws \Equit\Exceptions\ConflictingRouteException if a matching route is already registered.
	 */
	public function registerPut(string $route, $handler): void;

	/**
	 * Register a route with the router that responds only to DELETE requests.
	 *
	 * @param string $route The route to register.
	 * @param callable|array<class-string, string> $handler The handler to call when the route matches a request.
	 *
	 * @throws \Equit\Exceptions\ConflictingRouteException if a matching route is already registered.
	 */
	public function registerDelete(string $route, $handler): void;

	/**
	 * Register a route with the router that responds only to CONNECT requests.
	 *
	 * @param string $route The route to register.
	 * @param callable|array<class-string, string> $handler The handler to call when the route matches a request.
	 *
	 * @throws \Equit\Exceptions\ConflictingRouteException if a matching route is already registered.
	 */
	public function registerConnect(string $route, $handler): void;

	/**
	 * Register a route with the router that responds only to OPTIONS requests.
	 *
	 * @param string $route The route to register.
	 * @param callable|array<class-string, string> $handler The handler to call when the route matches a request.
	 *
	 * @throws \Equit\Exceptions\ConflictingRouteException if a matching route is already registered.
	 */
	public function registerOptions(string $route, $handler): void;

	/**
	 * Register a route with the router that responds only to PATCH requests.
	 *
	 * @param string $route The route to register.
	 * @param callable|array<class-string, string> $handler The handler to call when the route matches a request.
	 *
	 * @throws \Equit\Exceptions\ConflictingRouteException if a matching route is already registered.
	 */
	public function registerPatch(string $route, $handler): void;

	/**
	 * Register a route with the router that responds to requests using any HTTP method.
	 *
	 * @param string $route The route to register.
	 * @param callable|array<class-string, string> $handler The handler to call when the route matches a request.
	 *
	 * @throws \Equit\Exceptions\ConflictingRouteException if a matching route is already registered.
	 */
	public function registerAny(string $route, $handler): void;
}
