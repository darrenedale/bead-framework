<?php

/**
 * @author Darren Edale
 * @version 1.2.0
 */

namespace Equit;

use Equit\Contracts\Router as RouterContract;
use Equit\Exceptions\ConflictingRouteException;
use Equit\Exceptions\DuplicateRouteParameterNameException;
use Equit\Exceptions\InvalidRouteParameterNameException;
use Equit\Exceptions\UnroutableRequestException;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionFunction;
use Throwable;
use TypeError;
use function Equit\Traversable\all;
use function Equit\Traversable\isSubsetOf;

/**
 * A simple router that routes requests based on the URI path.
 *
 * Routes can be defined with placeholders for parameters that can be extracted from the path, for example the route
 * `/entry/{id}/edit` will match any URI that starts with `/entry/` and ends with `/edit` and has a single path segment
 * between the two, which will be extracted to a parameter named `id`. So `/entry/1/edit` would extract "1" to the `id`
 * parameter, `/entry/2/edit` would extract "2" and so on.
 *
 * The router will inject arguments extracted from the request URI into the handler's parameters: if the handler takes a
 * parameter with the same name as a parameter in the URI path, the handler will be called with that parameter filled
 * with the extracted value from the URI path. So for example if the handler is `function(Request $request, int $id)`,
 * the URI `/entry/1/edit` would call the handler with the value `1` for the `$id` parameter.
 *
 * Any type that can be converted from the string extracted from the URI can be hinted. If the matching parameter in the
 * handler has no type hint, it will be provided as a string. If the handler has any parameter that is not in the route
 * definition and that does not have a default value (i.e. is not optional), an exception will be thrown when routing
 * the request. All handlers can receive the Request instance by having a parameter type-hinted as `Request`. This can
 * appear in any position, but it's recommended that it's the first parameter for consistency.
 */
class Router implements RouterContract
{
	/** @var string Regular expression to capture a segment from a URI for a route parameter. */
	protected const RxCaptureSegment = "([^/]+)";

	/** @var string Regular expression to identify a route parameter in a route definition. */
	protected const RxParameter = "@\{([^/]*)}@";

	/** @var array[] route storage */
	private array $m_routes = [
		self::GetMethod => [],
		self::PostMethod => [],
		self::PutMethod => [],
		self::HeadMethod => [],
	    self::DeleteMethod => [],
	    self::ConnectMethod => [],
	    self::OptionsMethod => [],
	    self::PatchMethod  => [],
	];

	/**
	 * Fetch the route definition that matches a request, if any.
	 *
	 * @param \Equit\Request $request
	 *
	 * @return string|null
	 */
	protected function matchedRoute(Request $request): ?string
	{
		$requestRoute = $request->pathInfo();

		foreach (array_keys($this->m_routes[$request->method()]) as $route) {
			$rxRegisteredRoute = self::regularExpressionForRoute($route);

			if (preg_match($rxRegisteredRoute, $requestRoute)) {
				return $route;
			}
		}

		return null;
	}

	/**
	 * A valid route parameter name is not empty, starts with a letter or underscore and contains only letters, numbers
	 * and underscores.
	 *
	 * @param string $name The name to test.
	 *
	 * @return bool `true` if the parameter name is valid, `false` otherwise.
	 */
	protected static function isValidParameterName(string $name): bool
	{
		return !empty($name) && (ctype_alpha($name[0]) || "_" === $name[0]) && strlen($name) == strspn($name, "abcdefghijklmnopqrstuvwxyz_ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789");
	}

	/**
	 * Extract the parameter names from a route definition.
	 *
	 * The parameter names are returned in the order they appear in the provided route definition.
	 *
	 * @param string $route The route definition.
	 *
	 * @return array<string> The names of the parameters in the route definition.
	 */
	protected static function parametersForRoute(string $route): array
	{
		preg_match_all(self::RxParameter, $route, $parameters, PREG_PATTERN_ORDER);
		return false === $parameters ? [] : $parameters[1];
	}

	/**
	 * Helper to fetch a regular expression that will extract the arguments for route parameters from a request URI.
	 *
	 * Once a request is matched to a route, the returned regular expression can be applied to the request's URI to
	 * extract the arguments from the URI that correspond to the parameters defined in the route. For example, given:
	 *
	 * ```php
	 * $route = /entry/{type}/{id}/edit
	 * $uri = /entry/article/33/edit
	 * preg_match(self::regularExpressionForRoute($route), $uri, $arguments);
	 * array_shift($arguments);
	 * ```
	 *
	 * `$arguments` will be `["article", "1"]`.
	 *
	 * @param string $route The route definition for which to create the regex.
	 *
	 * @return string The regular expression.
	 */
	protected static function regularExpressionForRoute(string $route): string
	{
		$trim = 0;

		while ($trim < (strlen($route) - 1) && "/" === $route[$trim]) {
			++$trim;
		}

		$route = explode("/", substr($route, $trim));

		foreach ($route as & $segment) {
			if (preg_match(self::RxParameter, $segment)) {
				// if the segment is a parameter, replace it with the RX to capture the argument for that parameter
				$segment = self::RxCaptureSegment;
			} else {
				// otherwise, escape it for use in RX
				$segment = preg_quote($segment);
			}
		}

		return "@^/?" . implode("/", $route) . "/?\$@";
	}

	/**
	 * Given a route that's been matched to a request, extract the route's arguments from the request's URI.
	 *
	 * @param string $route The matched route definition.
	 * @param \Equit\Request $request The request that it was matched to.
	 *
	 * @return array The arguments for the route's parameters, keyed by the parameter name.
	 */
	protected static function extractRouteArgumentsFromRequest(string $route, Request $request): array
	{
		$routeParameterNames = self::parametersForRoute($route);
		preg_match(self::regularExpressionForRoute($route), $request->pathInfo(), $requestArguments);
		array_shift($requestArguments);
		return array_combine($routeParameterNames, $requestArguments);
	}

	/**
	 * Given a handler, a route definition and a request, extract the arguments for the handler from the request.
	 *
	 * The arguments are extracted from the request URI and mapped to the parameter names specified in the route
	 * definition. The handler's parameters are then examined for parameters with names matching those in the route
	 * definition, and an array of arguments to provide to the handler is built in the order the handler expects them.
	 *
	 * Any parameter for the handler that is type hinted with the `Request` type is provided with the request.
	 *
	 * @param callable|array<class-string, string> $handler The handler that has been matched to the request.
	 * @param string $route The route definition that matched the request.
	 * @param \Equit\Request $request The request.
	 *
	 * @return array The argument list for the handle.r
	 *
	 * @throws LogicException if the handler has any non-optional parameters that don't have matches in the route
	 * definition.
	 */
	protected static function buildHandlerArguments($handler, string $route, Request $request): array
	{
		// get the args provided in the request URI, keyed by the name in the route definition
		$routeArguments = self::extractRouteArgumentsFromRequest($route, $request);
		$handlerArguments = [];
		$reflector = static::reflectorForHandler($handler);

		foreach ($reflector->getParameters() as $parameter) {
			$type = $parameter->getType();

			if (isset($type) && Request::class === $type->getName()) {
				$handlerArguments[] = $request;
				continue;
			}

			if (!array_key_exists($parameter->getName(), $routeArguments)) {
				if (!$parameter->isOptional()) {
					throw new LogicException("Can't call handler for route parameter \${$parameter->getName()} is not optional and does not have a value in the route definition.");
				}

				continue;
			}

			$handlerArg = $routeArguments[$parameter->getName()];

			if (isset($type) && $type->isBuiltin()) {
				switch ($type->getName()) {
					case "int":
						$filter = FILTER_VALIDATE_INT;
						break;

					case "float":
					case "double":
						$filter = FILTER_VALIDATE_FLOAT;
						break;

					case "bool":
						$filter = FILTER_VALIDATE_BOOLEAN;
						break;

					case "string":
						$filter = null;
						break;

					default:
						throw new LogicException("Handler arguments of type \"{$type->getName()}\" cannot be extracted from route parameters.");
				}

				if (isset ($filter)) {
					$handlerArg = filter_var($handlerArg, $filter, ["flags" => FILTER_NULL_ON_FAILURE]);

					if (!isset($handlerArg)) {
						throw new LogicException("Can't convert value \"{$routeArguments[$parameter->getName()]}\" for route parameter \${$parameter->getName()} to {$type->getName()} for argument #{$parameter->getPosition()} \${$parameter->getName()} for handler.");
					}
				}
			}

			$handlerArguments[] = $handlerArg;
		}

		return $handlerArguments;
	}

	/**
	 * Fetch a reflector for the handler.
	 *
	 * @param callable|array<class-string, string> $handler The handler.
	 *
	 * @return \ReflectionFunction|\ReflectionMethod
	 */
	protected static function reflectorForHandler($handler)
	{
		if (is_string($handler) && false !== strpos("::", $handler)) {
			$handler = explode("::", $handler, 2);
		}

		try {
			if (is_array($handler)) {
				return (new ReflectionClass($handler[0]))->getMethod($handler[1]);
			} else if (is_string($handler) ||  $handler instanceof \Closure) {
				return new ReflectionFunction($handler);
			}
		} catch (Throwable $err) {
			throw new LogicException("Invalid route handler.", 0, $err);
		}

		throw new LogicException("Invalid route handler.");
	}

	/**
	 * @inheritDoc
	 */
	public function route(Request $request): void
	{
		$route = $this->matchedRoute($request);

		if (!isset($route)) {
			throw new UnroutableRequestException($request, "No handler was found for the request.");
		}

		$handler = $this->m_routes[$request->method()][$route];
		$handlerArgs = self::buildHandlerArguments($handler, $route, $request);

		// NOTE reflectorForHandler is always a ReflectionMethod in this case
		if (is_array($handler) && is_string($handler[0]) && !(static::reflectorForHandler($handler)->isStatic())) {
			// if the handler is a non-static method identified by its class name rather than an instance, attempt to
			// instantiate the class
			try {
				$handler = [new $handler[0], $handler[1]];
			} catch (Throwable $err) {
				throw new LogicException("Class {$handler[0]} must have a default constructor to handle request '{$request->pathInfo()}' to route {$route}.", 0, $err);
			}
		}

		$handler(...$handlerArgs);
	}

	/**
	 * Helper for register() to check a route being registered for conflicts with routes already registered.
	 *
	 * @param string $route The route to check.
	 * @param array<string> $methods The
	 *
	 * @throws \Equit\Exceptions\ConflictingRouteException
	 */
	protected function checkRouteConflicts(string $route, array $methods): void
	{
		$routeRegex = self::regularExpressionForRoute($route);

		foreach ($methods as $method) {
			foreach (array_keys($this->m_routes[$method]) as $registeredRoute) {
				if ($routeRegex === self::regularExpressionForRoute($registeredRoute)) {
					throw new ConflictingRouteException($route, "The route '{$route}' conflicts with the previously registered route '{$registeredRoute}' for the {$method} HTTP method.");
				}
			}
		}
	}

	/**
	 * Helper for register() to check a route being registered for sane parameter names.
	 *
	 * @param string $route The route to check.
	 *
	 * @throws \Equit\Exceptions\InvalidRouteParameterNameException if any parameter name is found to be invalid
	 * @throws \Equit\Exceptions\DuplicateRouteParameterNameException if any parameter name used more than once in the
	 * route
	 */
	protected static function checkRouteParameterNames(string $route): void
	{
		$parameterNames = self::parametersForRoute($route);

		for ($idx = 0; $idx < count($parameterNames); ++$idx) {
			if (!self::isValidParameterName($parameterNames[$idx])) {
				throw new InvalidRouteParameterNameException($parameterNames[$idx], $route, "The parameter name '{$parameterNames[$idx]}' in the route '{$route}' is not valid.");
			}

			if ($idx !== array_search($parameterNames[$idx], $parameterNames)) {
				throw new DuplicateRouteParameterNameException($parameterNames[$idx], $route, "The parameter name '{$parameterNames[$idx]}' in the route '{$route}' is used more than once.");
			}
		}
	}

	/**
	 * @inheritDoc
	 * @throws \Equit\Exceptions\InvalidRouteParameterNameException
	 * @throws \Equit\Exceptions\DuplicateRouteParameterNameException
	 */
	public function register(string $route, $methods, $handler): void
	{
		static $allMethods = null;

		if (!isset($allMethods)) {
			$allMethods = array_keys($this->m_routes);
		}

		if (self::AnyMethod === $methods) {
			$methods = $allMethods;
		} else {
			if (is_string($methods)) {
				$methods = [$methods,];
			} else if (!is_array($methods) || !all($methods, "is_string")) {
				throw new TypeError("Argument for parameter \$methods must be a string or an array of strings.");
			}

			if (!isSubsetOf($methods, [...$allMethods, self::AnyMethod,])) {
				throw new InvalidArgumentException("Methods registered must be a subset of the valid methods.");
			}

			$methods = (in_array(self::AnyMethod, $methods) ? $allMethods : array_unique($methods));
		}

		if (!is_callable($handler, true)) {
			throw new TypeError("Argument for parameter \$handler must be a callable or a tuple of class and method name.");
		}

		$this->checkRouteConflicts($route, $methods);
		self::checkRouteParameterNames($route);

		foreach ($methods as $method) {
			$this->m_routes[$method][$route] = $handler;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function registerGet(string $route, $handler): void
	{
		$this->register($route, self::GetMethod, $handler);
	}

	/**
	 * @inheritDoc
	 */
	public function registerPost(string $route, $handler): void
	{
		$this->register($route, self::PostMethod, $handler);
	}

	/**
	 * @inheritDoc
	 */
	public function registerPut(string $route, $handler): void
	{
		$this->register($route, self::PutMethod, $handler);
	}

	/**
	 * @inheritDoc
	 */
	public function registerHead(string $route, $handler): void
	{
		$this->register($route, self::HeadMethod, $handler);
	}

	/**
	 * @inheritDoc
	 */
	public function registerDelete(string $route, $handler): void
	{
		$this->register($route, self::DeleteMethod, $handler);
	}

	/**
	 * @inheritDoc
	 */
	public function registerAny(string $route, $handler): void
	{
		$this->register($route, self::AnyMethod, $handler);
	}

	/**
	 * @inheritDoc
	 */
	public function registerConnect(string $route, $handler): void
	{
		$this->register($route, self::ConnectMethod, $handler);
	}

	/**
	 * @inheritDoc
	 */
	public function registerOptions(string $route, $handler): void
	{
		$this->register($route, self::OptionsMethod, $handler);
	}

	/**
	 * @inheritDoc
	 */
	public function registerPatch(string $route, $handler): void
	{
		$this->register($route, self::PatchMethod, $handler);
	}
}