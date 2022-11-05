<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 */

declare(strict_types=1);

use Equit\Contracts\Response;
use Equit\Exceptions\ConflictingRouteException;
use Equit\Exceptions\DuplicateRouteParameterNameException;
use Equit\Exceptions\InvalidRouteParameterNameException;
use Equit\Test\Framework\TestCase;
use Equit\Exceptions\UnroutableRequestException;
use Equit\Contracts\Router as RouterContract;
use Equit\Router;
use Equit\Request;

use function Equit\Helpers\Iterable\accumulate;

/**
 * Test case for the Router class.
 */
class RouterTest extends TestCase
{
	/**
	 * @var int The number of times the nullStaticRouteHandler was called during a test.
	 *
	 * This is useful for asserting that the handler was called when a request was routed.
	 */
	private static int $s_nullStaticRouteHandlerCallCount = 0;

	/**
	 * @var int The number of times the nullRouteHandler was called during a test.
	 *
	 * This is useful for asserting that the handler was called when a request was routed.
	 */
	private int $m_nullRouteHandlerCallCount = 0;

	/**
	 * Route handler that does nothing except increment a call counter.
	 */
	public static function nullStaticRouteHandler(): void
	{
		++self::$s_nullStaticRouteHandlerCallCount;
	}

	/**
	 * Route handler that does nothing except increment a call counter.
	 */
	public function nullRouteHandler(): void
	{
		++$this->m_nullRouteHandlerCallCount;
	}

	/**
	 * Make a Request test double with a given pathInfo and HTTP method.
	 *
	 * @param string $pathInfo The path_info for the request (used in route matching).
	 * @param string $method The HTTP method.
	 *
	 * @return \Equit\Request
	 */
	protected static function makeRequest(string $pathInfo, string $method = RouterContract::GetMethod): Request
	{
		return new class($pathInfo, $method) extends Request
		{
			/** @noinspection PhpMissingParentConstructorInspection we're creating a test double, we don't want to call the parent constructor. */
			public function __construct(string $pathInfo, string $method)
			{
				$this->setPathInfo($pathInfo);
				$this->setMethod($method);
			}
		};
	}

	/**
	 * Fetch all the HTTP methods supported by the router.
	 * @return array The methods.
	 */
	protected static function allHttpMethods(): array
	{
		static $methods = [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::PutMethod, RouterContract::HeadMethod, RouterContract::DeleteMethod, RouterContract::ConnectMethod, RouterContract::OptionsMethod, RouterContract::PatchMethod,];
		return $methods;
	}

	/**
	 * Fetch a random HTTP method supported by the Router.
	 * @return string The method.
	 */
	protected static function randomHttpMethod(): string
	{
		return self::allHttpMethods()[mt_rand(0, count(self::allHttpMethods()) - 1)];
	}

	/**
	 * Generate a random valid handler for a route registration.
	 */
	protected function randomValidHandler()
	{
		switch (mt_rand(0, 4)) {
			case 0:
				return [$this, "nullRouteHandler"];

			case 1:
				return [self::class, "nullStaticRouteHandler"];

			case 2:
				return "phpinfo";

			case 3:
			default:
				return function() {};
		}
	}

	/**
	 * Generate a random route string with between 1 and 5 path components, a random number of which will be parameters.
	 *
	 * @return string
	 */
	protected static function randomRoute(): string
	{
		static $componentNames = ["post", "article", "section", "admin", "edit", "delete", "update", "move", "user", "account", "slice",];
		static $paramNames = ["{id}", "{name}", "{slug}", "{source}", "{destination}", "{code}", "{identifier}", "{item_id}", "{uuid}",];
		$components = mt_rand(1, 5);
		$usedParamNames = [];

		$route = [];

		for ($idx = 0; $idx < $components; ++$idx) {
			if (20 > mt_rand(0, 100)) {
				// ensure we don't generate duplicate parameter names
				do {
					$paramName = $paramNames[mt_rand(0, count($paramNames) - 1)];
				} while (in_array($paramName, $usedParamNames));

				$route[] = $paramName;
				$usedParamNames[] = $paramName;
			} else {
				$route[] = $componentNames[mt_rand(0, count($componentNames) - 1)];
			}
		}

		return "/" . implode("/", $route);
	}

	/**
	 * Data provider for tests for the Router register convenience methods for a single HTTP method.
	 *
	 * @return array The test data.
	 */
	public function dataForTestRegisterSingleMethod(): array
	{
		return [
			"typicalRootStaticMethod" => ["/", [self::class, "nullStaticRouteHandler"],],
			"typicalRootMethod" => ["/", [$this, "nullRouteHandler"],],
			"typicalRootClosure" => ["/", function() {},],
			"typicalRootFunctionName" => ["/", "phpinfo",],
			"typicalRootStaticMethodString" => ["/", "self::nullStaticRouteHandler",],
			"invalidRootNull" => ["/", null, TypeError::class,],
			"invalidRootInt" => ["/", 1, TypeError::class,],
			"invalidRootFloat" => ["/", 1.5, TypeError::class,],
			"invalidRootTrue" => ["/", true, TypeError::class,],
			"invalidRootFalse" => ["/", false, TypeError::class,],
			"invalidRootObject" => ["/", (object)[], TypeError::class,],
			"invalidRootAnonymousClass" => ["/", new class {}, TypeError::class,],
			"invalidRootEmptyArray" => ["/", [], TypeError::class,],
			"invalidRootArrayWithSingleFunctionName" => ["/", ["phpinfo"], TypeError::class,],
			"extremeRootWithNonExistentStaticMethod" => ["/", ["foo", "bar"],],
			"extremeRootWithNonExistentStaticMethodString" => ["/", "self::fooBar",],
			"extremeRootWithNonExistentFunctionName" => ["/", "foobar",],
		];
	}

	/**
	 * @dataProvider dataForTestRegisterSingleMethod
	 */
	public function testRegisterGet($route, $handler, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$router = new Router();
		/** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
		$router->registerGet($route, $handler);
		/** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
		$match = self::accessibleMethod($router, "matchedRoute");
		$this->assertSame($route, $match(self::makeRequest($route)));
	}

	/**
	 * @dataProvider dataForTestRegisterSingleMethod
	 */
	public function testRegisterPost($route, $handler, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$router = new Router();
		/** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
		$router->registerPost($route, $handler);
		/** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
		$match = self::accessibleMethod($router, "matchedRoute");
		$this->assertSame($route, $match(self::makeRequest($route, RouterContract::PostMethod)));
	}

	/**
	 * @dataProvider dataForTestRegisterSingleMethod
	 */
	public function testRegisterPut($route, $handler, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$router = new Router();
		/** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
		$router->registerPut($route, $handler);
		/** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
		$match = self::accessibleMethod($router, "matchedRoute");
		$this->assertSame($route, $match(self::makeRequest($route, RouterContract::PutMethod)));
	}

	/**
	 * @dataProvider dataForTestRegisterSingleMethod
	 */
	public function testRegisterDelete($route, $handler, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$router = new Router();
		/** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
		$router->registerDelete($route, $handler);
		/** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
		$match = self::accessibleMethod($router, "matchedRoute");
		$this->assertSame($route, $match(self::makeRequest($route, RouterContract::DeleteMethod)));
	}

	/**
	 * @dataProvider dataForTestRegisterSingleMethod
	 */
	public function testRegisterOptions($route, $handler, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$router = new Router();
		/** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
		$router->registerOptions($route, $handler);
		/** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
		$match = self::accessibleMethod($router, "matchedRoute");
		$this->assertSame($route, $match(self::makeRequest($route, RouterContract::OptionsMethod)));
	}

	/**
	 * @dataProvider dataForTestRegisterSingleMethod
	 */
	public function testRegisterHead($route, $handler, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$router = new Router();
		/** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
		$router->registerHead($route, $handler);
		/** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
		$match = self::accessibleMethod($router, "matchedRoute");
		$this->assertSame($route, $match(self::makeRequest($route, RouterContract::HeadMethod)));
	}

	/**
	 * @dataProvider dataForTestRegisterSingleMethod
	 */
	public function testRegisterConnect($route, $handler, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$router = new Router();
		/** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
		$router->registerConnect($route, $handler);
		/** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
		$match = self::accessibleMethod($router, "matchedRoute");
		$this->assertSame($route, $match(self::makeRequest($route, RouterContract::ConnectMethod)));
	}

	/**
	 * @dataProvider dataForTestRegisterSingleMethod
	 */
	public function testRegisterPatch($route, $handler, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$router = new Router();
		/** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
		$router->registerPatch($route, $handler);
		/** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
		$match = self::accessibleMethod($router, "matchedRoute");
		$this->assertSame($route, $match(self::makeRequest($route, RouterContract::PatchMethod)));
	}

	/**
	 * Data provider for testRegister.
	 *
	 * @return Generator The test data.
	 */
	public function dataForTestRegister(): Generator
	{
		yield from [
			// tests for a single HTTP method, as string and as single array element
			"typicalRootStaticMethodGetMethodString" => ["/", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodPostMethodString" => ["/", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodPutMethodString" => ["/", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodPatchMethodString" => ["/", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodHeadMethodString" => ["/", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodDeleteMethodString" => ["/", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodOptionsMethodString" => ["/", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodConnectMethodString" => ["/", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodAnyMethodString" => ["/", RouterContract::AnyMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodGetMethodArray" => ["/", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodPostMethodArray" => ["/", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodPutMethodArray" => ["/", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodPatchMethodArray" => ["/", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodHeadMethodArray" => ["/", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodOptionsMethodArray" => ["/", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalRootStaticMethodAnyMethodArray" => ["/", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalRootMethodGetMethodString" => ["/", RouterContract::GetMethod, [$this, "nullRouteHandler"],],
			"typicalRootMethodPostMethodString" => ["/", RouterContract::PostMethod, [$this, "nullRouteHandler"],],
			"typicalRootMethodPutMethodString" => ["/", RouterContract::PutMethod, [$this, "nullRouteHandler"],],
			"typicalRootMethodPatchMethodString" => ["/", RouterContract::PatchMethod, [$this, "nullRouteHandler"],],
			"typicalRootMethodHeadMethodString" => ["/", RouterContract::HeadMethod, [$this, "nullRouteHandler"],],
			"typicalRootMethodDeleteMethodString" => ["/", RouterContract::DeleteMethod, [$this, "nullRouteHandler"],],
			"typicalRootMethodOptionsMethodString" => ["/", RouterContract::GetMethod, [$this, "nullRouteHandler"],],
			"typicalRootMethodConnectMethodString" => ["/", RouterContract::ConnectMethod, [$this, "nullRouteHandler"],],
			"typicalRootMethodAnyMethodString" => ["/", RouterContract::AnyMethod, [$this, "nullRouteHandler"],],
			"typicalRootMethodGetMethodArray" => ["/", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],],
			"typicalRootMethodPostMethodArray" => ["/", [RouterContract::PostMethod,], [$this, "nullRouteHandler"],],
			"typicalRootMethodPutMethodArray" => ["/", [RouterContract::PutMethod,], [$this, "nullRouteHandler"],],
			"typicalRootMethodPatchMethodArray" => ["/", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"],],
			"typicalRootMethodHeadMethodArray" => ["/", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"],],
			"typicalRootMethodDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"],],
			"typicalRootMethodOptionsMethodArray" => ["/", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],],
			"typicalRootMethodConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"],],
			"typicalRootMethodAnyMethodArray" => ["/", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"],],
			"typicalRootClosureGetMethodString" => ["/", RouterContract::GetMethod, function() {},],
			"typicalRootClosurePostMethodString" => ["/", RouterContract::PostMethod, function() {},],
			"typicalRootClosurePutMethodString" => ["/", RouterContract::PutMethod, function() {},],
			"typicalRootClosurePatchMethodString" => ["/", RouterContract::PatchMethod, function() {},],
			"typicalRootClosureHeadMethodString" => ["/", RouterContract::HeadMethod, function() {},],
			"typicalRootClosureDeleteMethodString" => ["/", RouterContract::DeleteMethod, function() {},],
			"typicalRootClosureOptionsMethodString" => ["/", RouterContract::GetMethod, function() {},],
			"typicalRootClosureConnectMethodString" => ["/", RouterContract::ConnectMethod, function() {},],
			"typicalRootClosureAnyMethodString" => ["/", RouterContract::AnyMethod, function() {},],
			"typicalRootClosureGetMethodArray" => ["/", [RouterContract::GetMethod,], function() {},],
			"typicalRootClosurePostMethodArray" => ["/", [RouterContract::PostMethod,], function() {},],
			"typicalRootClosurePutMethodArray" => ["/", [RouterContract::PutMethod,], function() {},],
			"typicalRootClosurePatchMethodArray" => ["/", [RouterContract::PatchMethod,], function() {},],
			"typicalRootClosureHeadMethodArray" => ["/", [RouterContract::HeadMethod,], function() {},],
			"typicalRootClosureDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], function() {},],
			"typicalRootClosureOptionsMethodArray" => ["/", [RouterContract::GetMethod,], function() {},],
			"typicalRootClosureConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], function() {},],
			"typicalRootClosureAnyMethodArray" => ["/", [RouterContract::AnyMethod,], function() {},],
			"typicalRootFunctionNameGetMethodString" => ["/", RouterContract::GetMethod, "phpinfo",],
			"typicalRootFunctionNamePostMethodString" => ["/", RouterContract::PostMethod, "phpinfo",],
			"typicalRootFunctionNamePutMethodString" => ["/", RouterContract::PutMethod, "phpinfo",],
			"typicalRootFunctionNamePatchMethodString" => ["/", RouterContract::PatchMethod, "phpinfo",],
			"typicalRootFunctionNameHeadMethodString" => ["/", RouterContract::HeadMethod, "phpinfo",],
			"typicalRootFunctionNameDeleteMethodString" => ["/", RouterContract::DeleteMethod, "phpinfo",],
			"typicalRootFunctionNameOptionsMethodString" => ["/", RouterContract::GetMethod, "phpinfo",],
			"typicalRootFunctionNameConnectMethodString" => ["/", RouterContract::ConnectMethod, "phpinfo",],
			"typicalRootFunctionNameAnyMethodString" => ["/", RouterContract::AnyMethod, "phpinfo",],
			"typicalRootFunctionNameGetMethodArray" => ["/", [RouterContract::GetMethod,], "phpinfo",],
			"typicalRootFunctionNamePostMethodArray" => ["/", [RouterContract::PostMethod,], "phpinfo",],
			"typicalRootFunctionNamePutMethodArray" => ["/", [RouterContract::PutMethod,], "phpinfo",],
			"typicalRootFunctionNamePatchMethodArray" => ["/", [RouterContract::PatchMethod,], "phpinfo",],
			"typicalRootFunctionNameHeadMethodArray" => ["/", [RouterContract::HeadMethod,], "phpinfo",],
			"typicalRootFunctionNameDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], "phpinfo",],
			"typicalRootFunctionNameOptionsMethodArray" => ["/", [RouterContract::GetMethod,], "phpinfo",],
			"typicalRootFunctionNameConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], "phpinfo",],
			"typicalRootFunctionNameAnyMethodArray" => ["/", [RouterContract::AnyMethod,], "phpinfo",],
			"typicalRootStaticMethodStringGetMethodString" => ["/", RouterContract::GetMethod, "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringPostMethodString" => ["/", RouterContract::PostMethod, "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringPutMethodString" => ["/", RouterContract::PutMethod, "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringPatchMethodString" => ["/", RouterContract::PatchMethod, "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringHeadMethodString" => ["/", RouterContract::HeadMethod, "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringDeleteMethodString" => ["/", RouterContract::DeleteMethod, "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringOptionsMethodString" => ["/", RouterContract::GetMethod, "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringConnectMethodString" => ["/", RouterContract::ConnectMethod, "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringAnyMethodString" => ["/", RouterContract::AnyMethod, "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringGetMethodArray" => ["/", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringPostMethodArray" => ["/", [RouterContract::PostMethod,], "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringPutMethodArray" => ["/", [RouterContract::PutMethod,], "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringPatchMethodArray" => ["/", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringHeadMethodArray" => ["/", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringOptionsMethodArray" => ["/", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler",],
			"typicalRootStaticMethodStringAnyMethodArray" => ["/", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",],
			"invalidRootNullGetMethodString" => ["/", RouterContract::GetMethod, null, TypeError::class,],
			"invalidRootNullPostMethodString" => ["/", RouterContract::PostMethod, null, TypeError::class,],
			"invalidRootNullPutMethodString" => ["/", RouterContract::PutMethod, null, TypeError::class,],
			"invalidRootNullPatchMethodString" => ["/", RouterContract::PatchMethod, null, TypeError::class,],
			"invalidRootNullHeadMethodString" => ["/", RouterContract::HeadMethod, null, TypeError::class,],
			"invalidRootNullDeleteMethodString" => ["/", RouterContract::DeleteMethod, null, TypeError::class,],
			"invalidRootNullOptionsMethodString" => ["/", RouterContract::GetMethod, null, TypeError::class,],
			"invalidRootNullConnectMethodString" => ["/", RouterContract::ConnectMethod, null, TypeError::class,],
			"invalidRootNullAnyMethodString" => ["/", RouterContract::AnyMethod, null, TypeError::class,],
			"invalidRootNullGetMethodArray" => ["/", [RouterContract::GetMethod,], null, TypeError::class,],
			"invalidRootNullPostMethodArray" => ["/", [RouterContract::PostMethod,], null, TypeError::class,],
			"invalidRootNullPutMethodArray" => ["/", [RouterContract::PutMethod,], null, TypeError::class,],
			"invalidRootNullPatchMethodArray" => ["/", [RouterContract::PatchMethod,], null, TypeError::class,],
			"invalidRootNullHeadMethodArray" => ["/", [RouterContract::HeadMethod,], null, TypeError::class,],
			"invalidRootNullDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], null, TypeError::class,],
			"invalidRootNullOptionsMethodArray" => ["/", [RouterContract::GetMethod,], null, TypeError::class,],
			"invalidRootNullConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], null, TypeError::class,],
			"invalidRootNullAnyMethodArray" => ["/", [RouterContract::AnyMethod,], null, TypeError::class,],
			"invalidRootIntGetMethodString" => ["/", RouterContract::GetMethod, 1, TypeError::class,],
			"invalidRootIntPostMethodString" => ["/", RouterContract::PostMethod, 1, TypeError::class,],
			"invalidRootIntPutMethodString" => ["/", RouterContract::PutMethod, 1, TypeError::class,],
			"invalidRootIntPatchMethodString" => ["/", RouterContract::PatchMethod, 1, TypeError::class,],
			"invalidRootIntHeadMethodString" => ["/", RouterContract::HeadMethod, 1, TypeError::class,],
			"invalidRootIntDeleteMethodString" => ["/", RouterContract::DeleteMethod, 1, TypeError::class,],
			"invalidRootIntOptionsMethodString" => ["/", RouterContract::GetMethod, 1, TypeError::class,],
			"invalidRootIntConnectMethodString" => ["/", RouterContract::ConnectMethod, 1, TypeError::class,],
			"invalidRootIntAnyMethodString" => ["/", RouterContract::AnyMethod, 1, TypeError::class,],
			"invalidRootIntGetMethodArray" => ["/", [RouterContract::GetMethod,], 1, TypeError::class,],
			"invalidRootIntPostMethodArray" => ["/", [RouterContract::PostMethod,], 1, TypeError::class,],
			"invalidRootIntPutMethodArray" => ["/", [RouterContract::PutMethod,], 1, TypeError::class,],
			"invalidRootIntPatchMethodArray" => ["/", [RouterContract::PatchMethod,], 1, TypeError::class,],
			"invalidRootIntHeadMethodArray" => ["/", [RouterContract::HeadMethod,], 1, TypeError::class,],
			"invalidRootIntDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], 1, TypeError::class,],
			"invalidRootIntOptionsMethodArray" => ["/", [RouterContract::GetMethod,], 1, TypeError::class,],
			"invalidRootIntConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], 1, TypeError::class,],
			"invalidRootIntAnyMethodArray" => ["/", [RouterContract::AnyMethod,], 1, TypeError::class,],
			"invalidRootFloatGetMethodString" => ["/", RouterContract::GetMethod, 1.5, TypeError::class,],
			"invalidRootFloatPostMethodString" => ["/", RouterContract::PostMethod, 1.5, TypeError::class,],
			"invalidRootFloatPutMethodString" => ["/", RouterContract::PutMethod, 1.5, TypeError::class,],
			"invalidRootFloatPatchMethodString" => ["/", RouterContract::PatchMethod, 1.5, TypeError::class,],
			"invalidRootFloatHeadMethodString" => ["/", RouterContract::HeadMethod, 1.5, TypeError::class,],
			"invalidRootFloatDeleteMethodString" => ["/", RouterContract::DeleteMethod, 1.5, TypeError::class,],
			"invalidRootFloatOptionsMethodString" => ["/", RouterContract::GetMethod, 1.5, TypeError::class,],
			"invalidRootFloatConnectMethodString" => ["/", RouterContract::ConnectMethod, 1.5, TypeError::class,],
			"invalidRootFloatAnyMethodString" => ["/", RouterContract::AnyMethod, 1.5, TypeError::class,],
			"invalidRootFloatGetMethodArray" => ["/", [RouterContract::GetMethod,], 1.5, TypeError::class,],
			"invalidRootFloatPostMethodArray" => ["/", [RouterContract::PostMethod,], 1.5, TypeError::class,],
			"invalidRootFloatPutMethodArray" => ["/", [RouterContract::PutMethod,], 1.5, TypeError::class,],
			"invalidRootFloatPatchMethodArray" => ["/", [RouterContract::PatchMethod,], 1.5, TypeError::class,],
			"invalidRootFloatHeadMethodArray" => ["/", [RouterContract::HeadMethod,], 1.5, TypeError::class,],
			"invalidRootFloatDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], 1.5, TypeError::class,],
			"invalidRootFloatOptionsMethodArray" => ["/", [RouterContract::GetMethod,], 1.5, TypeError::class,],
			"invalidRootFloatConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], 1.5, TypeError::class,],
			"invalidRootFloatAnyMethodArray" => ["/", [RouterContract::AnyMethod,], 1.5, TypeError::class,],
			"invalidRootTrueGetMethodString" => ["/", RouterContract::GetMethod, true, TypeError::class,],
			"invalidRootTruePostMethodString" => ["/", RouterContract::PostMethod, true, TypeError::class,],
			"invalidRootTruePutMethodString" => ["/", RouterContract::PutMethod, true, TypeError::class,],
			"invalidRootTruePatchMethodString" => ["/", RouterContract::PatchMethod, true, TypeError::class,],
			"invalidRootTrueHeadMethodString" => ["/", RouterContract::HeadMethod, true, TypeError::class,],
			"invalidRootTrueDeleteMethodString" => ["/", RouterContract::DeleteMethod, true, TypeError::class,],
			"invalidRootTrueOptionsMethodString" => ["/", RouterContract::GetMethod, true, TypeError::class,],
			"invalidRootTrueConnectMethodString" => ["/", RouterContract::ConnectMethod, true, TypeError::class,],
			"invalidRootTrueAnyMethodString" => ["/", RouterContract::AnyMethod, true, TypeError::class,],
			"invalidRootTrueGetMethodArray" => ["/", [RouterContract::GetMethod,], true, TypeError::class,],
			"invalidRootTruePostMethodArray" => ["/", [RouterContract::PostMethod,], true, TypeError::class,],
			"invalidRootTruePutMethodArray" => ["/", [RouterContract::PutMethod,], true, TypeError::class,],
			"invalidRootTruePatchMethodArray" => ["/", [RouterContract::PatchMethod,], true, TypeError::class,],
			"invalidRootTrueHeadMethodArray" => ["/", [RouterContract::HeadMethod,], true, TypeError::class,],
			"invalidRootTrueDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], true, TypeError::class,],
			"invalidRootTrueOptionsMethodArray" => ["/", [RouterContract::GetMethod,], true, TypeError::class,],
			"invalidRootTrueConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], true, TypeError::class,],
			"invalidRootTrueAnyMethodArray" => ["/", [RouterContract::AnyMethod,], true, TypeError::class,],
			"invalidRootFalseGetMethodString" => ["/", RouterContract::GetMethod, false, TypeError::class,],
			"invalidRootFalsePostMethodString" => ["/", RouterContract::PostMethod, false, TypeError::class,],
			"invalidRootFalsePutMethodString" => ["/", RouterContract::PutMethod, false, TypeError::class,],
			"invalidRootFalsePatchMethodString" => ["/", RouterContract::PatchMethod, false, TypeError::class,],
			"invalidRootFalseHeadMethodString" => ["/", RouterContract::HeadMethod, false, TypeError::class,],
			"invalidRootFalseDeleteMethodString" => ["/", RouterContract::DeleteMethod, false, TypeError::class,],
			"invalidRootFalseOptionsMethodString" => ["/", RouterContract::GetMethod, false, TypeError::class,],
			"invalidRootFalseConnectMethodString" => ["/", RouterContract::ConnectMethod, false, TypeError::class,],
			"invalidRootFalseAnyMethodString" => ["/", RouterContract::AnyMethod, false, TypeError::class,],
			"invalidRootFalseGetMethodArray" => ["/", [RouterContract::GetMethod,], false, TypeError::class,],
			"invalidRootFalsePostMethodArray" => ["/", [RouterContract::PostMethod,], false, TypeError::class,],
			"invalidRootFalsePutMethodArray" => ["/", [RouterContract::PutMethod,], false, TypeError::class,],
			"invalidRootFalsePatchMethodArray" => ["/", [RouterContract::PatchMethod,], false, TypeError::class,],
			"invalidRootFalseHeadMethodArray" => ["/", [RouterContract::HeadMethod,], false, TypeError::class,],
			"invalidRootFalseDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], false, TypeError::class,],
			"invalidRootFalseOptionsMethodArray" => ["/", [RouterContract::GetMethod,], false, TypeError::class,],
			"invalidRootFalseConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], false, TypeError::class,],
			"invalidRootFalseAnyMethodArray" => ["/", [RouterContract::AnyMethod,], false, TypeError::class,],
			"invalidRootObjectGetMethodString" => ["/", RouterContract::GetMethod, (object) [], TypeError::class,],
			"invalidRootObjectPostMethodString" => ["/", RouterContract::PostMethod, (object) [], TypeError::class,],
			"invalidRootObjectPutMethodString" => ["/", RouterContract::PutMethod, (object) [], TypeError::class,],
			"invalidRootObjectPatchMethodString" => ["/", RouterContract::PatchMethod, (object) [], TypeError::class,],
			"invalidRootObjectHeadMethodString" => ["/", RouterContract::HeadMethod, (object) [], TypeError::class,],
			"invalidRootObjectDeleteMethodString" => ["/", RouterContract::DeleteMethod, (object) [], TypeError::class,],
			"invalidRootObjectOptionsMethodString" => ["/", RouterContract::GetMethod, (object) [], TypeError::class,],
			"invalidRootObjectConnectMethodString" => ["/", RouterContract::ConnectMethod, (object) [], TypeError::class,],
			"invalidRootObjectAnyMethodString" => ["/", RouterContract::AnyMethod, (object) [], TypeError::class,],
			"invalidRootObjectGetMethodArray" => ["/", [RouterContract::GetMethod,], (object) [], TypeError::class,],
			"invalidRootObjectPostMethodArray" => ["/", [RouterContract::PostMethod,], (object) [], TypeError::class,],
			"invalidRootObjectPutMethodArray" => ["/", [RouterContract::PutMethod,], (object) [], TypeError::class,],
			"invalidRootObjectPatchMethodArray" => ["/", [RouterContract::PatchMethod,], (object) [], TypeError::class,],
			"invalidRootObjectHeadMethodArray" => ["/", [RouterContract::HeadMethod,], (object) [], TypeError::class,],
			"invalidRootObjectDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], (object) [], TypeError::class,],
			"invalidRootObjectOptionsMethodArray" => ["/", [RouterContract::GetMethod,], (object) [], TypeError::class,],
			"invalidRootObjectConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], (object) [], TypeError::class,],
			"invalidRootObjectAnyMethodArray" => ["/", [RouterContract::AnyMethod,], (object) [], TypeError::class,],
			"invalidRootAnonymousArrayGetMethodString" => ["/", RouterContract::GetMethod, new class{}, TypeError::class,],
			"invalidRootAnonymousArrayPostMethodString" => ["/", RouterContract::PostMethod, new class{}, TypeError::class,],
			"invalidRootAnonymousArrayPutMethodString" => ["/", RouterContract::PutMethod, new class{}, TypeError::class,],
			"invalidRootAnonymousArrayPatchMethodString" => ["/", RouterContract::PatchMethod, new class{}, TypeError::class,],
			"invalidRootAnonymousArrayHeadMethodString" => ["/", RouterContract::HeadMethod, new class{}, TypeError::class,],
			"invalidRootAnonymousArrayDeleteMethodString" => ["/", RouterContract::DeleteMethod, new class{}, TypeError::class,],
			"invalidRootAnonymousArrayOptionsMethodString" => ["/", RouterContract::GetMethod, new class{}, TypeError::class,],
			"invalidRootAnonymousArrayConnectMethodString" => ["/", RouterContract::ConnectMethod, new class{}, TypeError::class,],
			"invalidRootAnonymousArrayAnyMethodString" => ["/", RouterContract::AnyMethod, new class{}, TypeError::class,],
			"invalidRootAnonymousArrayGetMethodArray" => ["/", [RouterContract::GetMethod,], new class{}, TypeError::class,],
			"invalidRootAnonymousArrayPostMethodArray" => ["/", [RouterContract::PostMethod,], new class{}, TypeError::class,],
			"invalidRootAnonymousArrayPutMethodArray" => ["/", [RouterContract::PutMethod,], new class{}, TypeError::class,],
			"invalidRootAnonymousArrayPatchMethodArray" => ["/", [RouterContract::PatchMethod,], new class{}, TypeError::class,],
			"invalidRootAnonymousArrayHeadMethodArray" => ["/", [RouterContract::HeadMethod,], new class{}, TypeError::class,],
			"invalidRootAnonymousArrayDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], new class{}, TypeError::class,],
			"invalidRootAnonymousArrayOptionsMethodArray" => ["/", [RouterContract::GetMethod,], new class{}, TypeError::class,],
			"invalidRootAnonymousArrayConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], new class{}, TypeError::class,],
			"invalidRootAnonymousArrayAnyMethodArray" => ["/", [RouterContract::AnyMethod,], new class{}, TypeError::class,],
			"invalidRootEmptyArrayGetMethodString" => ["/", RouterContract::GetMethod, [], TypeError::class,],
			"invalidRootEmptyArrayPostMethodString" => ["/", RouterContract::PostMethod, [], TypeError::class,],
			"invalidRootEmptyArrayPutMethodString" => ["/", RouterContract::PutMethod, [], TypeError::class,],
			"invalidRootEmptyArrayPatchMethodString" => ["/", RouterContract::PatchMethod, [], TypeError::class,],
			"invalidRootEmptyArrayHeadMethodString" => ["/", RouterContract::HeadMethod, [], TypeError::class,],
			"invalidRootEmptyArrayDeleteMethodString" => ["/", RouterContract::DeleteMethod, [], TypeError::class,],
			"invalidRootEmptyArrayOptionsMethodString" => ["/", RouterContract::GetMethod, [], TypeError::class,],
			"invalidRootEmptyArrayConnectMethodString" => ["/", RouterContract::ConnectMethod, [], TypeError::class,],
			"invalidRootEmptyArrayAnyMethodString" => ["/", RouterContract::AnyMethod, [], TypeError::class,],
			"invalidRootEmptyArrayGetMethodArray" => ["/", [RouterContract::GetMethod,], [], TypeError::class,],
			"invalidRootEmptyArrayPostMethodArray" => ["/", [RouterContract::PostMethod,], [], TypeError::class,],
			"invalidRootEmptyArrayPutMethodArray" => ["/", [RouterContract::PutMethod,], [], TypeError::class,],
			"invalidRootEmptyArrayPatchMethodArray" => ["/", [RouterContract::PatchMethod,], [], TypeError::class,],
			"invalidRootEmptyArrayHeadMethodArray" => ["/", [RouterContract::HeadMethod,], [], TypeError::class,],
			"invalidRootEmptyArrayDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], [], TypeError::class,],
			"invalidRootEmptyArrayOptionsMethodArray" => ["/", [RouterContract::GetMethod,], [], TypeError::class,],
			"invalidRootEmptyArrayConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], [], TypeError::class,],
			"invalidRootEmptyArrayAnyMethodArray" => ["/", [RouterContract::AnyMethod,], [], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNameGetMethodString" => ["/", RouterContract::GetMethod, ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNamePostMethodString" => ["/", RouterContract::PostMethod, ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNamePutMethodString" => ["/", RouterContract::PutMethod, ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNamePatchMethodString" => ["/", RouterContract::PatchMethod, ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNameHeadMethodString" => ["/", RouterContract::HeadMethod, ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNameDeleteMethodString" => ["/", RouterContract::DeleteMethod, ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNameOptionsMethodString" => ["/", RouterContract::GetMethod, ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNameConnectMethodString" => ["/", RouterContract::ConnectMethod, ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNameAnyMethodString" => ["/", RouterContract::AnyMethod, ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNameGetMethodArray" => ["/", [RouterContract::GetMethod,], ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNamePostMethodArray" => ["/", [RouterContract::PostMethod,], ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNamePutMethodArray" => ["/", [RouterContract::PutMethod,], ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNamePatchMethodArray" => ["/", [RouterContract::PatchMethod,], ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNameHeadMethodArray" => ["/", [RouterContract::HeadMethod,], ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNameDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNameOptionsMethodArray" => ["/", [RouterContract::GetMethod,], ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNameConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], ["phpinfo"], TypeError::class,],
			"invalidRootArrayWithSingleFunctionNameAnyMethodArray" => ["/", [RouterContract::AnyMethod,], ["phpinfo"], TypeError::class,],
			"extremeRootArrayWithNonExistentStaticMethodGetMethodString" => ["/", RouterContract::GetMethod, ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodPostMethodString" => ["/", RouterContract::PostMethod, ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodPutMethodString" => ["/", RouterContract::PutMethod, ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodPatchMethodString" => ["/", RouterContract::PatchMethod, ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodHeadMethodString" => ["/", RouterContract::HeadMethod, ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodDeleteMethodString" => ["/", RouterContract::DeleteMethod, ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodOptionsMethodString" => ["/", RouterContract::GetMethod, ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodConnectMethodString" => ["/", RouterContract::ConnectMethod, ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodAnyMethodString" => ["/", RouterContract::AnyMethod, ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodGetMethodArray" => ["/", [RouterContract::GetMethod,], ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodPostMethodArray" => ["/", [RouterContract::PostMethod,], ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodPutMethodArray" => ["/", [RouterContract::PutMethod,], ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodPatchMethodArray" => ["/", [RouterContract::PatchMethod,], ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodHeadMethodArray" => ["/", [RouterContract::HeadMethod,], ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodOptionsMethodArray" => ["/", [RouterContract::GetMethod,], ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodAnyMethodArray" => ["/", [RouterContract::AnyMethod,], ["foo", "bar"],],
			"extremeRootArrayWithNonExistentStaticMethodStringGetMethodString" => ["/", RouterContract::GetMethod, "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringPostMethodString" => ["/", RouterContract::PostMethod, "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringPutMethodString" => ["/", RouterContract::PutMethod, "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringPatchMethodString" => ["/", RouterContract::PatchMethod, "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringHeadMethodString" => ["/", RouterContract::HeadMethod, "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringDeleteMethodString" => ["/", RouterContract::DeleteMethod, "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringOptionsMethodString" => ["/", RouterContract::GetMethod, "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringConnectMethodString" => ["/", RouterContract::ConnectMethod, "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringAnyMethodString" => ["/", RouterContract::AnyMethod, "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringGetMethodArray" => ["/", [RouterContract::GetMethod,], "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringPostMethodArray" => ["/", [RouterContract::PostMethod,], "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringPutMethodArray" => ["/", [RouterContract::PutMethod,], "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringPatchMethodArray" => ["/", [RouterContract::PatchMethod,], "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringHeadMethodArray" => ["/", [RouterContract::HeadMethod,], "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringOptionsMethodArray" => ["/", [RouterContract::GetMethod,], "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], "self::fooBar",],
			"extremeRootArrayWithNonExistentStaticMethodStringAnyMethodArray" => ["/", [RouterContract::AnyMethod,], "self::fooBar",],
			"extremeRootArrayWithNonExistentFunctionNameStringGetMethodString" => ["/", RouterContract::GetMethod, "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringPostMethodString" => ["/", RouterContract::PostMethod, "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringPutMethodString" => ["/", RouterContract::PutMethod, "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringPatchMethodString" => ["/", RouterContract::PatchMethod, "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringHeadMethodString" => ["/", RouterContract::HeadMethod, "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringDeleteMethodString" => ["/", RouterContract::DeleteMethod, "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringOptionsMethodString" => ["/", RouterContract::GetMethod, "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringConnectMethodString" => ["/", RouterContract::ConnectMethod, "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringAnyMethodString" => ["/", RouterContract::AnyMethod, "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringGetMethodArray" => ["/", [RouterContract::GetMethod,], "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringPostMethodArray" => ["/", [RouterContract::PostMethod,], "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringPutMethodArray" => ["/", [RouterContract::PutMethod,], "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringPatchMethodArray" => ["/", [RouterContract::PatchMethod,], "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringHeadMethodArray" => ["/", [RouterContract::HeadMethod,], "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringOptionsMethodArray" => ["/", [RouterContract::GetMethod,], "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], "foobar",],
			"extremeRootArrayWithNonExistentFunctionNameStringAnyMethodArray" => ["/", [RouterContract::AnyMethod,], "foobar",],

			// tests for multiple HTTP methods at once
			"typicalRootGetAndPostStaticMethodArray" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], [self::class, "nullRouteHandler"],],
			"typicalRootGetAndPostStaticMethodString" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], "self::nullStaticRouteHandler",],
			"typicalRootGetAndPostMethodArray" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], [$this, "nullRouteHandler"],],
			"typicalRootGetAndPostClosure" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], function() {},],
			"typicalRootGetAndPostFunctionName" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], "phpinfo",],

			// test duplicate HTTP methods don't attempt to register a handler more than once for the same method and
			// route - the router should array_unique() the methods to avoid throwing ConflictingRouteException
			"extremeRootDuplicatedMethodStaticMethodArray" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], [self::class, "nullRouteHandler"],],
			"extremeRootDuplicatedMethodStaticMethodString" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], "self::nullStaticRouteHandler",],
			"extremeRootDuplicatedMethodMethodArray" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], [$this, "nullRouteHandler"],],
			"extremeRootDuplicatedMethodClosure" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], function() {},],
			"extremeRootDuplicatedMethodFunctionName" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], "phpinfo",],

			// tests for invalid methods
			"invalidRootWithInvalidMethodString" => ["/", "foo", "phpinfo", InvalidArgumentException::class],
			"invalidRootWithInvalidMethodArray" => ["/", ["foo"], "phpinfo", InvalidArgumentException::class],
			"invalidRootWithInvalidMethodInOtherwiseValidArray" => ["/", ["foo", RouterContract::GetMethod, RouterContract::PostMethod,], "phpinfo", InvalidArgumentException::class],
			"invalidRootWithInvalidMethodStringable" => ["/", new class {
				public function __toString(): string
				{
					return RouterContract::GetMethod;
				}
			}, "phpinfo", TypeError::class],
			
			// tests with other route strings
			"typicalHomeStaticMethodGetMethodString" => ["/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodPostMethodString" => ["/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodPutMethodString" => ["/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodPatchMethodString" => ["/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodHeadMethodString" => ["/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodDeleteMethodString" => ["/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodOptionsMethodString" => ["/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodConnectMethodString" => ["/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodGetMethodArray" => ["/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodPostMethodArray" => ["/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodPutMethodArray" => ["/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodPatchMethodArray" => ["/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodHeadMethodArray" => ["/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodDeleteMethodArray" => ["/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodOptionsMethodArray" => ["/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodConnectMethodArray" => ["/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalHomeStaticMethodAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalHomeMethodGetMethodString" => ["/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],],
			"typicalHomeMethodPostMethodString" => ["/home", RouterContract::PostMethod, [$this, "nullRouteHandler"],],
			"typicalHomeMethodPutMethodString" => ["/home", RouterContract::PutMethod, [$this, "nullRouteHandler"],],
			"typicalHomeMethodPatchMethodString" => ["/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"],],
			"typicalHomeMethodHeadMethodString" => ["/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"],],
			"typicalHomeMethodDeleteMethodString" => ["/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"],],
			"typicalHomeMethodOptionsMethodString" => ["/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],],
			"typicalHomeMethodConnectMethodString" => ["/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"],],
			"typicalHomeMethodAnyMethodString" => ["/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"],],
			"typicalHomeMethodGetMethodArray" => ["/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],],
			"typicalHomeMethodPostMethodArray" => ["/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"],],
			"typicalHomeMethodPutMethodArray" => ["/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"],],
			"typicalHomeMethodPatchMethodArray" => ["/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"],],
			"typicalHomeMethodHeadMethodArray" => ["/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"],],
			"typicalHomeMethodDeleteMethodArray" => ["/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"],],
			"typicalHomeMethodOptionsMethodArray" => ["/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],],
			"typicalHomeMethodConnectMethodArray" => ["/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"],],
			"typicalHomeMethodAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"],],
			"typicalHomeClosureGetMethodString" => ["/home", RouterContract::GetMethod, function() {},],
			"typicalHomeClosurePostMethodString" => ["/home", RouterContract::PostMethod, function() {},],
			"typicalHomeClosurePutMethodString" => ["/home", RouterContract::PutMethod, function() {},],
			"typicalHomeClosurePatchMethodString" => ["/home", RouterContract::PatchMethod, function() {},],
			"typicalHomeClosureHeadMethodString" => ["/home", RouterContract::HeadMethod, function() {},],
			"typicalHomeClosureDeleteMethodString" => ["/home", RouterContract::DeleteMethod, function() {},],
			"typicalHomeClosureOptionsMethodString" => ["/home", RouterContract::GetMethod, function() {},],
			"typicalHomeClosureConnectMethodString" => ["/home", RouterContract::ConnectMethod, function() {},],
			"typicalHomeClosureAnyMethodString" => ["/home", RouterContract::AnyMethod, function() {},],
			"typicalHomeClosureGetMethodArray" => ["/home", [RouterContract::GetMethod,], function() {},],
			"typicalHomeClosurePostMethodArray" => ["/home", [RouterContract::PostMethod,], function() {},],
			"typicalHomeClosurePutMethodArray" => ["/home", [RouterContract::PutMethod,], function() {},],
			"typicalHomeClosurePatchMethodArray" => ["/home", [RouterContract::PatchMethod,], function() {},],
			"typicalHomeClosureHeadMethodArray" => ["/home", [RouterContract::HeadMethod,], function() {},],
			"typicalHomeClosureDeleteMethodArray" => ["/home", [RouterContract::DeleteMethod,], function() {},],
			"typicalHomeClosureOptionsMethodArray" => ["/home", [RouterContract::GetMethod,], function() {},],
			"typicalHomeClosureConnectMethodArray" => ["/home", [RouterContract::ConnectMethod,], function() {},],
			"typicalHomeClosureAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], function() {},],
			"typicalHomeFunctionNameGetMethodString" => ["/home", RouterContract::GetMethod, "phpinfo",],
			"typicalHomeFunctionNamePostMethodString" => ["/home", RouterContract::PostMethod, "phpinfo",],
			"typicalHomeFunctionNamePutMethodString" => ["/home", RouterContract::PutMethod, "phpinfo",],
			"typicalHomeFunctionNamePatchMethodString" => ["/home", RouterContract::PatchMethod, "phpinfo",],
			"typicalHomeFunctionNameHeadMethodString" => ["/home", RouterContract::HeadMethod, "phpinfo",],
			"typicalHomeFunctionNameDeleteMethodString" => ["/home", RouterContract::DeleteMethod, "phpinfo",],
			"typicalHomeFunctionNameOptionsMethodString" => ["/home", RouterContract::GetMethod, "phpinfo",],
			"typicalHomeFunctionNameConnectMethodString" => ["/home", RouterContract::ConnectMethod, "phpinfo",],
			"typicalHomeFunctionNameAnyMethodString" => ["/home", RouterContract::AnyMethod, "phpinfo",],
			"typicalHomeFunctionNameGetMethodArray" => ["/home", [RouterContract::GetMethod,], "phpinfo",],
			"typicalHomeFunctionNamePostMethodArray" => ["/home", [RouterContract::PostMethod,], "phpinfo",],
			"typicalHomeFunctionNamePutMethodArray" => ["/home", [RouterContract::PutMethod,], "phpinfo",],
			"typicalHomeFunctionNamePatchMethodArray" => ["/home", [RouterContract::PatchMethod,], "phpinfo",],
			"typicalHomeFunctionNameHeadMethodArray" => ["/home", [RouterContract::HeadMethod,], "phpinfo",],
			"typicalHomeFunctionNameDeleteMethodArray" => ["/home", [RouterContract::DeleteMethod,], "phpinfo",],
			"typicalHomeFunctionNameOptionsMethodArray" => ["/home", [RouterContract::GetMethod,], "phpinfo",],
			"typicalHomeFunctionNameConnectMethodArray" => ["/home", [RouterContract::ConnectMethod,], "phpinfo",],
			"typicalHomeFunctionNameAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], "phpinfo",],
			"typicalHomeStaticMethodStringGetMethodString" => ["/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringPostMethodString" => ["/home", RouterContract::PostMethod, "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringPutMethodString" => ["/home", RouterContract::PutMethod, "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringPatchMethodString" => ["/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringHeadMethodString" => ["/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringDeleteMethodString" => ["/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringOptionsMethodString" => ["/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringConnectMethodString" => ["/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringAnyMethodString" => ["/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringGetMethodArray" => ["/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringPostMethodArray" => ["/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringPutMethodArray" => ["/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringPatchMethodArray" => ["/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringHeadMethodArray" => ["/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringDeleteMethodArray" => ["/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringOptionsMethodArray" => ["/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringConnectMethodArray" => ["/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler",],
			"typicalHomeStaticMethodStringAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",],
			
			"typicalMultiSegmentRouteStaticMethodGetMethodString" => ["/account/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodPostMethodString" => ["/account/user/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodPutMethodString" => ["/account/user/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodPatchMethodString" => ["/account/user/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodHeadMethodString" => ["/account/user/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodDeleteMethodString" => ["/account/user/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodOptionsMethodString" => ["/account/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodConnectMethodString" => ["/account/user/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodGetMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodPostMethodArray" => ["/account/user/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodPutMethodArray" => ["/account/user/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodPatchMethodArray" => ["/account/user/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodHeadMethodArray" => ["/account/user/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodDeleteMethodArray" => ["/account/user/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodOptionsMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodConnectMethodArray" => ["/account/user/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteStaticMethodAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiSegmentRouteMethodGetMethodString" => ["/account/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodPostMethodString" => ["/account/user/home", RouterContract::PostMethod, [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodPutMethodString" => ["/account/user/home", RouterContract::PutMethod, [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodPatchMethodString" => ["/account/user/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodHeadMethodString" => ["/account/user/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodDeleteMethodString" => ["/account/user/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodOptionsMethodString" => ["/account/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodConnectMethodString" => ["/account/user/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodAnyMethodString" => ["/account/user/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodGetMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodPostMethodArray" => ["/account/user/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodPutMethodArray" => ["/account/user/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodPatchMethodArray" => ["/account/user/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodHeadMethodArray" => ["/account/user/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodDeleteMethodArray" => ["/account/user/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodOptionsMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodConnectMethodArray" => ["/account/user/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteMethodAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiSegmentRouteClosureGetMethodString" => ["/account/user/home", RouterContract::GetMethod, function() {},],
			"typicalMultiSegmentRouteClosurePostMethodString" => ["/account/user/home", RouterContract::PostMethod, function() {},],
			"typicalMultiSegmentRouteClosurePutMethodString" => ["/account/user/home", RouterContract::PutMethod, function() {},],
			"typicalMultiSegmentRouteClosurePatchMethodString" => ["/account/user/home", RouterContract::PatchMethod, function() {},],
			"typicalMultiSegmentRouteClosureHeadMethodString" => ["/account/user/home", RouterContract::HeadMethod, function() {},],
			"typicalMultiSegmentRouteClosureDeleteMethodString" => ["/account/user/home", RouterContract::DeleteMethod, function() {},],
			"typicalMultiSegmentRouteClosureOptionsMethodString" => ["/account/user/home", RouterContract::GetMethod, function() {},],
			"typicalMultiSegmentRouteClosureConnectMethodString" => ["/account/user/home", RouterContract::ConnectMethod, function() {},],
			"typicalMultiSegmentRouteClosureAnyMethodString" => ["/account/user/home", RouterContract::AnyMethod, function() {},],
			"typicalMultiSegmentRouteClosureGetMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], function() {},],
			"typicalMultiSegmentRouteClosurePostMethodArray" => ["/account/user/home", [RouterContract::PostMethod,], function() {},],
			"typicalMultiSegmentRouteClosurePutMethodArray" => ["/account/user/home", [RouterContract::PutMethod,], function() {},],
			"typicalMultiSegmentRouteClosurePatchMethodArray" => ["/account/user/home", [RouterContract::PatchMethod,], function() {},],
			"typicalMultiSegmentRouteClosureHeadMethodArray" => ["/account/user/home", [RouterContract::HeadMethod,], function() {},],
			"typicalMultiSegmentRouteClosureDeleteMethodArray" => ["/account/user/home", [RouterContract::DeleteMethod,], function() {},],
			"typicalMultiSegmentRouteClosureOptionsMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], function() {},],
			"typicalMultiSegmentRouteClosureConnectMethodArray" => ["/account/user/home", [RouterContract::ConnectMethod,], function() {},],
			"typicalMultiSegmentRouteClosureAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], function() {},],
			"typicalMultiSegmentRouteFunctionNameGetMethodString" => ["/account/user/home", RouterContract::GetMethod, "phpinfo",],
			"typicalMultiSegmentRouteFunctionNamePostMethodString" => ["/account/user/home", RouterContract::PostMethod, "phpinfo",],
			"typicalMultiSegmentRouteFunctionNamePutMethodString" => ["/account/user/home", RouterContract::PutMethod, "phpinfo",],
			"typicalMultiSegmentRouteFunctionNamePatchMethodString" => ["/account/user/home", RouterContract::PatchMethod, "phpinfo",],
			"typicalMultiSegmentRouteFunctionNameHeadMethodString" => ["/account/user/home", RouterContract::HeadMethod, "phpinfo",],
			"typicalMultiSegmentRouteFunctionNameDeleteMethodString" => ["/account/user/home", RouterContract::DeleteMethod, "phpinfo",],
			"typicalMultiSegmentRouteFunctionNameOptionsMethodString" => ["/account/user/home", RouterContract::GetMethod, "phpinfo",],
			"typicalMultiSegmentRouteFunctionNameConnectMethodString" => ["/account/user/home", RouterContract::ConnectMethod, "phpinfo",],
			"typicalMultiSegmentRouteFunctionNameAnyMethodString" => ["/account/user/home", RouterContract::AnyMethod, "phpinfo",],
			"typicalMultiSegmentRouteFunctionNameGetMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], "phpinfo",],
			"typicalMultiSegmentRouteFunctionNamePostMethodArray" => ["/account/user/home", [RouterContract::PostMethod,], "phpinfo",],
			"typicalMultiSegmentRouteFunctionNamePutMethodArray" => ["/account/user/home", [RouterContract::PutMethod,], "phpinfo",],
			"typicalMultiSegmentRouteFunctionNamePatchMethodArray" => ["/account/user/home", [RouterContract::PatchMethod,], "phpinfo",],
			"typicalMultiSegmentRouteFunctionNameHeadMethodArray" => ["/account/user/home", [RouterContract::HeadMethod,], "phpinfo",],
			"typicalMultiSegmentRouteFunctionNameDeleteMethodArray" => ["/account/user/home", [RouterContract::DeleteMethod,], "phpinfo",],
			"typicalMultiSegmentRouteFunctionNameOptionsMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], "phpinfo",],
			"typicalMultiSegmentRouteFunctionNameConnectMethodArray" => ["/account/user/home", [RouterContract::ConnectMethod,], "phpinfo",],
			"typicalMultiSegmentRouteFunctionNameAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], "phpinfo",],
			"typicalMultiSegmentRouteStaticMethodStringGetMethodString" => ["/account/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringPostMethodString" => ["/account/user/home", RouterContract::PostMethod, "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringPutMethodString" => ["/account/user/home", RouterContract::PutMethod, "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringPatchMethodString" => ["/account/user/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringHeadMethodString" => ["/account/user/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringDeleteMethodString" => ["/account/user/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringOptionsMethodString" => ["/account/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringConnectMethodString" => ["/account/user/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringAnyMethodString" => ["/account/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringGetMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringPostMethodArray" => ["/account/user/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringPutMethodArray" => ["/account/user/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringPatchMethodArray" => ["/account/user/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringHeadMethodArray" => ["/account/user/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringDeleteMethodArray" => ["/account/user/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringOptionsMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringConnectMethodArray" => ["/account/user/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiSegmentRouteStaticMethodStringAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",],

			// tests with route strings containing parameters
			"typicalParameterisedRouteStaticMethodGetMethodString" => ["/user/{id}/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodPostMethodString" => ["/user/{id}/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodPutMethodString" => ["/user/{id}/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodPatchMethodString" => ["/user/{id}/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodHeadMethodString" => ["/user/{id}/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodDeleteMethodString" => ["/user/{id}/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodOptionsMethodString" => ["/user/{id}/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodConnectMethodString" => ["/user/{id}/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodGetMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodPostMethodArray" => ["/user/{id}/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodPutMethodArray" => ["/user/{id}/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodPatchMethodArray" => ["/user/{id}/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodHeadMethodArray" => ["/user/{id}/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodDeleteMethodArray" => ["/user/{id}/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodOptionsMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodConnectMethodArray" => ["/user/{id}/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteStaticMethodAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalParameterisedRouteMethodGetMethodString" => ["/user/{id}/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodPostMethodString" => ["/user/{id}/home", RouterContract::PostMethod, [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodPutMethodString" => ["/user/{id}/home", RouterContract::PutMethod, [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodPatchMethodString" => ["/user/{id}/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodHeadMethodString" => ["/user/{id}/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodDeleteMethodString" => ["/user/{id}/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodOptionsMethodString" => ["/user/{id}/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodConnectMethodString" => ["/user/{id}/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodAnyMethodString" => ["/user/{id}/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodGetMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodPostMethodArray" => ["/user/{id}/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodPutMethodArray" => ["/user/{id}/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodPatchMethodArray" => ["/user/{id}/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodHeadMethodArray" => ["/user/{id}/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodDeleteMethodArray" => ["/user/{id}/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodOptionsMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodConnectMethodArray" => ["/user/{id}/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteMethodAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"],],
			"typicalParameterisedRouteClosureGetMethodString" => ["/user/{id}/home", RouterContract::GetMethod, function() {},],
			"typicalParameterisedRouteClosurePostMethodString" => ["/user/{id}/home", RouterContract::PostMethod, function() {},],
			"typicalParameterisedRouteClosurePutMethodString" => ["/user/{id}/home", RouterContract::PutMethod, function() {},],
			"typicalParameterisedRouteClosurePatchMethodString" => ["/user/{id}/home", RouterContract::PatchMethod, function() {},],
			"typicalParameterisedRouteClosureHeadMethodString" => ["/user/{id}/home", RouterContract::HeadMethod, function() {},],
			"typicalParameterisedRouteClosureDeleteMethodString" => ["/user/{id}/home", RouterContract::DeleteMethod, function() {},],
			"typicalParameterisedRouteClosureOptionsMethodString" => ["/user/{id}/home", RouterContract::GetMethod, function() {},],
			"typicalParameterisedRouteClosureConnectMethodString" => ["/user/{id}/home", RouterContract::ConnectMethod, function() {},],
			"typicalParameterisedRouteClosureAnyMethodString" => ["/user/{id}/home", RouterContract::AnyMethod, function() {},],
			"typicalParameterisedRouteClosureGetMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], function() {},],
			"typicalParameterisedRouteClosurePostMethodArray" => ["/user/{id}/home", [RouterContract::PostMethod,], function() {},],
			"typicalParameterisedRouteClosurePutMethodArray" => ["/user/{id}/home", [RouterContract::PutMethod,], function() {},],
			"typicalParameterisedRouteClosurePatchMethodArray" => ["/user/{id}/home", [RouterContract::PatchMethod,], function() {},],
			"typicalParameterisedRouteClosureHeadMethodArray" => ["/user/{id}/home", [RouterContract::HeadMethod,], function() {},],
			"typicalParameterisedRouteClosureDeleteMethodArray" => ["/user/{id}/home", [RouterContract::DeleteMethod,], function() {},],
			"typicalParameterisedRouteClosureOptionsMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], function() {},],
			"typicalParameterisedRouteClosureConnectMethodArray" => ["/user/{id}/home", [RouterContract::ConnectMethod,], function() {},],
			"typicalParameterisedRouteClosureAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], function() {},],
			"typicalParameterisedRouteFunctionNameGetMethodString" => ["/user/{id}/home", RouterContract::GetMethod, "phpinfo",],
			"typicalParameterisedRouteFunctionNamePostMethodString" => ["/user/{id}/home", RouterContract::PostMethod, "phpinfo",],
			"typicalParameterisedRouteFunctionNamePutMethodString" => ["/user/{id}/home", RouterContract::PutMethod, "phpinfo",],
			"typicalParameterisedRouteFunctionNamePatchMethodString" => ["/user/{id}/home", RouterContract::PatchMethod, "phpinfo",],
			"typicalParameterisedRouteFunctionNameHeadMethodString" => ["/user/{id}/home", RouterContract::HeadMethod, "phpinfo",],
			"typicalParameterisedRouteFunctionNameDeleteMethodString" => ["/user/{id}/home", RouterContract::DeleteMethod, "phpinfo",],
			"typicalParameterisedRouteFunctionNameOptionsMethodString" => ["/user/{id}/home", RouterContract::GetMethod, "phpinfo",],
			"typicalParameterisedRouteFunctionNameConnectMethodString" => ["/user/{id}/home", RouterContract::ConnectMethod, "phpinfo",],
			"typicalParameterisedRouteFunctionNameAnyMethodString" => ["/user/{id}/home", RouterContract::AnyMethod, "phpinfo",],
			"typicalParameterisedRouteFunctionNameGetMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], "phpinfo",],
			"typicalParameterisedRouteFunctionNamePostMethodArray" => ["/user/{id}/home", [RouterContract::PostMethod,], "phpinfo",],
			"typicalParameterisedRouteFunctionNamePutMethodArray" => ["/user/{id}/home", [RouterContract::PutMethod,], "phpinfo",],
			"typicalParameterisedRouteFunctionNamePatchMethodArray" => ["/user/{id}/home", [RouterContract::PatchMethod,], "phpinfo",],
			"typicalParameterisedRouteFunctionNameHeadMethodArray" => ["/user/{id}/home", [RouterContract::HeadMethod,], "phpinfo",],
			"typicalParameterisedRouteFunctionNameDeleteMethodArray" => ["/user/{id}/home", [RouterContract::DeleteMethod,], "phpinfo",],
			"typicalParameterisedRouteFunctionNameOptionsMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], "phpinfo",],
			"typicalParameterisedRouteFunctionNameConnectMethodArray" => ["/user/{id}/home", [RouterContract::ConnectMethod,], "phpinfo",],
			"typicalParameterisedRouteFunctionNameAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], "phpinfo",],
			"typicalParameterisedRouteStaticMethodStringGetMethodString" => ["/user/{id}/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringPostMethodString" => ["/user/{id}/home", RouterContract::PostMethod, "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringPutMethodString" => ["/user/{id}/home", RouterContract::PutMethod, "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringPatchMethodString" => ["/user/{id}/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringHeadMethodString" => ["/user/{id}/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringDeleteMethodString" => ["/user/{id}/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringOptionsMethodString" => ["/user/{id}/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringConnectMethodString" => ["/user/{id}/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringAnyMethodString" => ["/user/{id}/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringGetMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringPostMethodArray" => ["/user/{id}/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringPutMethodArray" => ["/user/{id}/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringPatchMethodArray" => ["/user/{id}/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringHeadMethodArray" => ["/user/{id}/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringDeleteMethodArray" => ["/user/{id}/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringOptionsMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringConnectMethodArray" => ["/user/{id}/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler",],
			"typicalParameterisedRouteStaticMethodStringAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",],

			"typicalMultiParameterRouteStaticMethodGetMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodPostMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodPutMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodPatchMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodHeadMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodDeleteMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodOptionsMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodConnectMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodGetMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodPostMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodPutMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodPatchMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodHeadMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodDeleteMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodOptionsMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodConnectMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteStaticMethodAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],],
			"typicalMultiParameterRouteMethodGetMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodPostMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PostMethod, [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodPutMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PutMethod, [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodPatchMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodHeadMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodDeleteMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodOptionsMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodConnectMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodAnyMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodGetMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodPostMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodPutMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodPatchMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodHeadMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodDeleteMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodOptionsMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodConnectMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteMethodAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"],],
			"typicalMultiParameterRouteClosureGetMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, function() {},],
			"typicalMultiParameterRouteClosurePostMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PostMethod, function() {},],
			"typicalMultiParameterRouteClosurePutMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PutMethod, function() {},],
			"typicalMultiParameterRouteClosurePatchMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PatchMethod, function() {},],
			"typicalMultiParameterRouteClosureHeadMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::HeadMethod, function() {},],
			"typicalMultiParameterRouteClosureDeleteMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::DeleteMethod, function() {},],
			"typicalMultiParameterRouteClosureOptionsMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, function() {},],
			"typicalMultiParameterRouteClosureConnectMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::ConnectMethod, function() {},],
			"typicalMultiParameterRouteClosureAnyMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::AnyMethod, function() {},],
			"typicalMultiParameterRouteClosureGetMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], function() {},],
			"typicalMultiParameterRouteClosurePostMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PostMethod,], function() {},],
			"typicalMultiParameterRouteClosurePutMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PutMethod,], function() {},],
			"typicalMultiParameterRouteClosurePatchMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PatchMethod,], function() {},],
			"typicalMultiParameterRouteClosureHeadMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::HeadMethod,], function() {},],
			"typicalMultiParameterRouteClosureDeleteMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::DeleteMethod,], function() {},],
			"typicalMultiParameterRouteClosureOptionsMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], function() {},],
			"typicalMultiParameterRouteClosureConnectMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::ConnectMethod,], function() {},],
			"typicalMultiParameterRouteClosureAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], function() {},],
			"typicalMultiParameterRouteFunctionNameGetMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, "phpinfo",],
			"typicalMultiParameterRouteFunctionNamePostMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PostMethod, "phpinfo",],
			"typicalMultiParameterRouteFunctionNamePutMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PutMethod, "phpinfo",],
			"typicalMultiParameterRouteFunctionNamePatchMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PatchMethod, "phpinfo",],
			"typicalMultiParameterRouteFunctionNameHeadMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::HeadMethod, "phpinfo",],
			"typicalMultiParameterRouteFunctionNameDeleteMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::DeleteMethod, "phpinfo",],
			"typicalMultiParameterRouteFunctionNameOptionsMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, "phpinfo",],
			"typicalMultiParameterRouteFunctionNameConnectMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::ConnectMethod, "phpinfo",],
			"typicalMultiParameterRouteFunctionNameAnyMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::AnyMethod, "phpinfo",],
			"typicalMultiParameterRouteFunctionNameGetMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], "phpinfo",],
			"typicalMultiParameterRouteFunctionNamePostMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PostMethod,], "phpinfo",],
			"typicalMultiParameterRouteFunctionNamePutMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PutMethod,], "phpinfo",],
			"typicalMultiParameterRouteFunctionNamePatchMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PatchMethod,], "phpinfo",],
			"typicalMultiParameterRouteFunctionNameHeadMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::HeadMethod,], "phpinfo",],
			"typicalMultiParameterRouteFunctionNameDeleteMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::DeleteMethod,], "phpinfo",],
			"typicalMultiParameterRouteFunctionNameOptionsMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], "phpinfo",],
			"typicalMultiParameterRouteFunctionNameConnectMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::ConnectMethod,], "phpinfo",],
			"typicalMultiParameterRouteFunctionNameAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], "phpinfo",],
			"typicalMultiParameterRouteStaticMethodStringGetMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringPostMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PostMethod, "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringPutMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PutMethod, "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringPatchMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringHeadMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringDeleteMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringOptionsMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringConnectMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringAnyMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringGetMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringPostMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringPutMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringPatchMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringHeadMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringDeleteMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringOptionsMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringConnectMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler",],
			"typicalMultiParameterRouteStaticMethodStringAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",],

			"invalidDuplicateParameterRouteStaticMethodGetMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodPostMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodPutMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodPatchMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodHeadMethodString" => ["/account/{id}/user/{id}/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodDeleteMethodString" => ["/account/{id}/user/{id}/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodOptionsMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodConnectMethodString" => ["/account/{id}/user/{id}/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodGetMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodPostMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodPutMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodPatchMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodHeadMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodDeleteMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodOptionsMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodConnectMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodGetMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodPostMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PostMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodPutMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PutMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodPatchMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodHeadMethodString" => ["/account/{id}/user/{id}/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodDeleteMethodString" => ["/account/{id}/user/{id}/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodOptionsMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodConnectMethodString" => ["/account/{id}/user/{id}/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodAnyMethodString" => ["/account/{id}/user/{id}/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodGetMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodPostMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodPutMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodPatchMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodHeadMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodDeleteMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodOptionsMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodConnectMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteMethodAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosureGetMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosurePostMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PostMethod, function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosurePutMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PutMethod, function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosurePatchMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PatchMethod, function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosureHeadMethodString" => ["/account/{id}/user/{id}/home", RouterContract::HeadMethod, function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosureDeleteMethodString" => ["/account/{id}/user/{id}/home", RouterContract::DeleteMethod, function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosureOptionsMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosureConnectMethodString" => ["/account/{id}/user/{id}/home", RouterContract::ConnectMethod, function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosureAnyMethodString" => ["/account/{id}/user/{id}/home", RouterContract::AnyMethod, function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosureGetMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosurePostMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PostMethod,], function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosurePutMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PutMethod,], function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosurePatchMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PatchMethod,], function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosureHeadMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::HeadMethod,], function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosureDeleteMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::DeleteMethod,], function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosureOptionsMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosureConnectMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::ConnectMethod,], function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteClosureAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], function() {}, DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNameGetMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNamePostMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PostMethod, "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNamePutMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PutMethod, "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNamePatchMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PatchMethod, "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNameHeadMethodString" => ["/account/{id}/user/{id}/home", RouterContract::HeadMethod, "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNameDeleteMethodString" => ["/account/{id}/user/{id}/home", RouterContract::DeleteMethod, "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNameOptionsMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNameConnectMethodString" => ["/account/{id}/user/{id}/home", RouterContract::ConnectMethod, "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNameAnyMethodString" => ["/account/{id}/user/{id}/home", RouterContract::AnyMethod, "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNameGetMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNamePostMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PostMethod,], "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNamePutMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PutMethod,], "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNamePatchMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PatchMethod,], "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNameHeadMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::HeadMethod,], "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNameDeleteMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::DeleteMethod,], "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNameOptionsMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNameConnectMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::ConnectMethod,], "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteFunctionNameAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], "phpinfo", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringGetMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringPostMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PostMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringPutMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PutMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringPatchMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringHeadMethodString" => ["/account/{id}/user/{id}/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringDeleteMethodString" => ["/account/{id}/user/{id}/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringOptionsMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringConnectMethodString" => ["/account/{id}/user/{id}/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringAnyMethodString" => ["/account/{id}/user/{id}/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringGetMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringPostMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringPutMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringPatchMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringHeadMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringDeleteMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringOptionsMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringConnectMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],
			"invalidDuplicateParameterRouteStaticMethodStringAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,],

			"invalidBadParameterNameEmptyRouteStaticMethodGetMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodPostMethodString" => ["/account/{}/user/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodPutMethodString" => ["/account/{}/user/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodPatchMethodString" => ["/account/{}/user/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodHeadMethodString" => ["/account/{}/user/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodDeleteMethodString" => ["/account/{}/user/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodOptionsMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodConnectMethodString" => ["/account/{}/user/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodGetMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodPostMethodArray" => ["/account/{}/user/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodPutMethodArray" => ["/account/{}/user/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodPatchMethodArray" => ["/account/{}/user/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodHeadMethodArray" => ["/account/{}/user/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodDeleteMethodArray" => ["/account/{}/user/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodOptionsMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodConnectMethodArray" => ["/account/{}/user/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodGetMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodPostMethodString" => ["/account/{}/user/home", RouterContract::PostMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodPutMethodString" => ["/account/{}/user/home", RouterContract::PutMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodPatchMethodString" => ["/account/{}/user/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodHeadMethodString" => ["/account/{}/user/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodDeleteMethodString" => ["/account/{}/user/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodOptionsMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodConnectMethodString" => ["/account/{}/user/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodAnyMethodString" => ["/account/{}/user/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodGetMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodPostMethodArray" => ["/account/{}/user/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodPutMethodArray" => ["/account/{}/user/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodPatchMethodArray" => ["/account/{}/user/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodHeadMethodArray" => ["/account/{}/user/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodDeleteMethodArray" => ["/account/{}/user/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodOptionsMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodConnectMethodArray" => ["/account/{}/user/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteMethodAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosureGetMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosurePostMethodString" => ["/account/{}/user/home", RouterContract::PostMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosurePutMethodString" => ["/account/{}/user/home", RouterContract::PutMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosurePatchMethodString" => ["/account/{}/user/home", RouterContract::PatchMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosureHeadMethodString" => ["/account/{}/user/home", RouterContract::HeadMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosureDeleteMethodString" => ["/account/{}/user/home", RouterContract::DeleteMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosureOptionsMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosureConnectMethodString" => ["/account/{}/user/home", RouterContract::ConnectMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosureAnyMethodString" => ["/account/{}/user/home", RouterContract::AnyMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosureGetMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosurePostMethodArray" => ["/account/{}/user/home", [RouterContract::PostMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosurePutMethodArray" => ["/account/{}/user/home", [RouterContract::PutMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosurePatchMethodArray" => ["/account/{}/user/home", [RouterContract::PatchMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosureHeadMethodArray" => ["/account/{}/user/home", [RouterContract::HeadMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosureDeleteMethodArray" => ["/account/{}/user/home", [RouterContract::DeleteMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosureOptionsMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosureConnectMethodArray" => ["/account/{}/user/home", [RouterContract::ConnectMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteClosureAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNameGetMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNamePostMethodString" => ["/account/{}/user/home", RouterContract::PostMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNamePutMethodString" => ["/account/{}/user/home", RouterContract::PutMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNamePatchMethodString" => ["/account/{}/user/home", RouterContract::PatchMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNameHeadMethodString" => ["/account/{}/user/home", RouterContract::HeadMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNameDeleteMethodString" => ["/account/{}/user/home", RouterContract::DeleteMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNameOptionsMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNameConnectMethodString" => ["/account/{}/user/home", RouterContract::ConnectMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNameAnyMethodString" => ["/account/{}/user/home", RouterContract::AnyMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNameGetMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNamePostMethodArray" => ["/account/{}/user/home", [RouterContract::PostMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNamePutMethodArray" => ["/account/{}/user/home", [RouterContract::PutMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNamePatchMethodArray" => ["/account/{}/user/home", [RouterContract::PatchMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNameHeadMethodArray" => ["/account/{}/user/home", [RouterContract::HeadMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNameDeleteMethodArray" => ["/account/{}/user/home", [RouterContract::DeleteMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNameOptionsMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNameConnectMethodArray" => ["/account/{}/user/home", [RouterContract::ConnectMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteFunctionNameAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringGetMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringPostMethodString" => ["/account/{}/user/home", RouterContract::PostMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringPutMethodString" => ["/account/{}/user/home", RouterContract::PutMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringPatchMethodString" => ["/account/{}/user/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringHeadMethodString" => ["/account/{}/user/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringDeleteMethodString" => ["/account/{}/user/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringOptionsMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringConnectMethodString" => ["/account/{}/user/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringAnyMethodString" => ["/account/{}/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringGetMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringPostMethodArray" => ["/account/{}/user/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringPutMethodArray" => ["/account/{}/user/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringPatchMethodArray" => ["/account/{}/user/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringHeadMethodArray" => ["/account/{}/user/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringDeleteMethodArray" => ["/account/{}/user/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringOptionsMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringConnectMethodArray" => ["/account/{}/user/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameEmptyRouteStaticMethodStringAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],

			"invalidBadParameterNameInvalidCharacterRouteStaticMethodGetMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodPostMethodString" => ["/account/{account-id}/user/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodPutMethodString" => ["/account/{account-id}/user/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodPatchMethodString" => ["/account/{account-id}/user/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodHeadMethodString" => ["/account/{account-id}/user/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodDeleteMethodString" => ["/account/{account-id}/user/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodOptionsMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodConnectMethodString" => ["/account/{account-id}/user/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodGetMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodPostMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodPutMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodPatchMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodHeadMethodArray" => ["/account/{account-id}/user/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodDeleteMethodArray" => ["/account/{account-id}/user/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodOptionsMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodConnectMethodArray" => ["/account/{account-id}/user/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodGetMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodPostMethodString" => ["/account/{account-id}/user/home", RouterContract::PostMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodPutMethodString" => ["/account/{account-id}/user/home", RouterContract::PutMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodPatchMethodString" => ["/account/{account-id}/user/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodHeadMethodString" => ["/account/{account-id}/user/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodDeleteMethodString" => ["/account/{account-id}/user/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodOptionsMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodConnectMethodString" => ["/account/{account-id}/user/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodAnyMethodString" => ["/account/{account-id}/user/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodGetMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodPostMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodPutMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodPatchMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodHeadMethodArray" => ["/account/{account-id}/user/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodDeleteMethodArray" => ["/account/{account-id}/user/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodOptionsMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodConnectMethodArray" => ["/account/{account-id}/user/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteMethodAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosureGetMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosurePostMethodString" => ["/account/{account-id}/user/home", RouterContract::PostMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosurePutMethodString" => ["/account/{account-id}/user/home", RouterContract::PutMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosurePatchMethodString" => ["/account/{account-id}/user/home", RouterContract::PatchMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosureHeadMethodString" => ["/account/{account-id}/user/home", RouterContract::HeadMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosureDeleteMethodString" => ["/account/{account-id}/user/home", RouterContract::DeleteMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosureOptionsMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosureConnectMethodString" => ["/account/{account-id}/user/home", RouterContract::ConnectMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosureAnyMethodString" => ["/account/{account-id}/user/home", RouterContract::AnyMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosureGetMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosurePostMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PostMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosurePutMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PutMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosurePatchMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PatchMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosureHeadMethodArray" => ["/account/{account-id}/user/home", [RouterContract::HeadMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosureDeleteMethodArray" => ["/account/{account-id}/user/home", [RouterContract::DeleteMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosureOptionsMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosureConnectMethodArray" => ["/account/{account-id}/user/home", [RouterContract::ConnectMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteClosureAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNameGetMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNamePostMethodString" => ["/account/{account-id}/user/home", RouterContract::PostMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNamePutMethodString" => ["/account/{account-id}/user/home", RouterContract::PutMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNamePatchMethodString" => ["/account/{account-id}/user/home", RouterContract::PatchMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNameHeadMethodString" => ["/account/{account-id}/user/home", RouterContract::HeadMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNameDeleteMethodString" => ["/account/{account-id}/user/home", RouterContract::DeleteMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNameOptionsMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNameConnectMethodString" => ["/account/{account-id}/user/home", RouterContract::ConnectMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNameAnyMethodString" => ["/account/{account-id}/user/home", RouterContract::AnyMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNameGetMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNamePostMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PostMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNamePutMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PutMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNamePatchMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PatchMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNameHeadMethodArray" => ["/account/{account-id}/user/home", [RouterContract::HeadMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNameDeleteMethodArray" => ["/account/{account-id}/user/home", [RouterContract::DeleteMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNameOptionsMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNameConnectMethodArray" => ["/account/{account-id}/user/home", [RouterContract::ConnectMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteFunctionNameAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringGetMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringPostMethodString" => ["/account/{account-id}/user/home", RouterContract::PostMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringPutMethodString" => ["/account/{account-id}/user/home", RouterContract::PutMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringPatchMethodString" => ["/account/{account-id}/user/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringHeadMethodString" => ["/account/{account-id}/user/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringDeleteMethodString" => ["/account/{account-id}/user/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringOptionsMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringConnectMethodString" => ["/account/{account-id}/user/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringAnyMethodString" => ["/account/{account-id}/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringGetMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringPostMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringPutMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringPatchMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringHeadMethodArray" => ["/account/{account-id}/user/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringDeleteMethodArray" => ["/account/{account-id}/user/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringOptionsMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringConnectMethodArray" => ["/account/{account-id}/user/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidCharacterRouteStaticMethodStringAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],

			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodGetMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodPostMethodString" => ["/account/{-account_id}/user/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodPutMethodString" => ["/account/{-account_id}/user/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodPatchMethodString" => ["/account/{-account_id}/user/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodHeadMethodString" => ["/account/{-account_id}/user/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodDeleteMethodString" => ["/account/{-account_id}/user/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodOptionsMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodConnectMethodString" => ["/account/{-account_id}/user/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodGetMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodPostMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodPutMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodPatchMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodHeadMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodDeleteMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodOptionsMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodConnectMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodGetMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodPostMethodString" => ["/account/{-account_id}/user/home", RouterContract::PostMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodPutMethodString" => ["/account/{-account_id}/user/home", RouterContract::PutMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodPatchMethodString" => ["/account/{-account_id}/user/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodHeadMethodString" => ["/account/{-account_id}/user/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodDeleteMethodString" => ["/account/{-account_id}/user/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodOptionsMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodConnectMethodString" => ["/account/{-account_id}/user/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodAnyMethodString" => ["/account/{-account_id}/user/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodGetMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodPostMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodPutMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodPatchMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodHeadMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodDeleteMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodOptionsMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodConnectMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteMethodAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosureGetMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosurePostMethodString" => ["/account/{-account_id}/user/home", RouterContract::PostMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosurePutMethodString" => ["/account/{-account_id}/user/home", RouterContract::PutMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosurePatchMethodString" => ["/account/{-account_id}/user/home", RouterContract::PatchMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosureHeadMethodString" => ["/account/{-account_id}/user/home", RouterContract::HeadMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosureDeleteMethodString" => ["/account/{-account_id}/user/home", RouterContract::DeleteMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosureOptionsMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosureConnectMethodString" => ["/account/{-account_id}/user/home", RouterContract::ConnectMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosureAnyMethodString" => ["/account/{-account_id}/user/home", RouterContract::AnyMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosureGetMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosurePostMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PostMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosurePutMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PutMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosurePatchMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PatchMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosureHeadMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::HeadMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosureDeleteMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::DeleteMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosureOptionsMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosureConnectMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::ConnectMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteClosureAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameGetMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNamePostMethodString" => ["/account/{-account_id}/user/home", RouterContract::PostMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNamePutMethodString" => ["/account/{-account_id}/user/home", RouterContract::PutMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNamePatchMethodString" => ["/account/{-account_id}/user/home", RouterContract::PatchMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameHeadMethodString" => ["/account/{-account_id}/user/home", RouterContract::HeadMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameDeleteMethodString" => ["/account/{-account_id}/user/home", RouterContract::DeleteMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameOptionsMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameConnectMethodString" => ["/account/{-account_id}/user/home", RouterContract::ConnectMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameAnyMethodString" => ["/account/{-account_id}/user/home", RouterContract::AnyMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameGetMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNamePostMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PostMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNamePutMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PutMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNamePatchMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PatchMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameHeadMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::HeadMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameDeleteMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::DeleteMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameOptionsMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameConnectMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::ConnectMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringGetMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringPostMethodString" => ["/account/{-account_id}/user/home", RouterContract::PostMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringPutMethodString" => ["/account/{-account_id}/user/home", RouterContract::PutMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringPatchMethodString" => ["/account/{-account_id}/user/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringHeadMethodString" => ["/account/{-account_id}/user/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringDeleteMethodString" => ["/account/{-account_id}/user/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringOptionsMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringConnectMethodString" => ["/account/{-account_id}/user/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringAnyMethodString" => ["/account/{-account_id}/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringGetMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringPostMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringPutMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringPatchMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringHeadMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringDeleteMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringOptionsMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringConnectMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],

			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodGetMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodPostMethodString" => ["/account/{1account_id}/user/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodPutMethodString" => ["/account/{1account_id}/user/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodPatchMethodString" => ["/account/{1account_id}/user/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodHeadMethodString" => ["/account/{1account_id}/user/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodDeleteMethodString" => ["/account/{1account_id}/user/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodOptionsMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodConnectMethodString" => ["/account/{1account_id}/user/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodGetMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodPostMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodPutMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodPatchMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodHeadMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodDeleteMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodOptionsMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodConnectMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodGetMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodPostMethodString" => ["/account/{1account_id}/user/home", RouterContract::PostMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodPutMethodString" => ["/account/{1account_id}/user/home", RouterContract::PutMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodPatchMethodString" => ["/account/{1account_id}/user/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodHeadMethodString" => ["/account/{1account_id}/user/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodDeleteMethodString" => ["/account/{1account_id}/user/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodOptionsMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodConnectMethodString" => ["/account/{1account_id}/user/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodAnyMethodString" => ["/account/{1account_id}/user/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodGetMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodPostMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodPutMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodPatchMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodHeadMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodDeleteMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodOptionsMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodConnectMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteMethodAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosureGetMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosurePostMethodString" => ["/account/{1account_id}/user/home", RouterContract::PostMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosurePutMethodString" => ["/account/{1account_id}/user/home", RouterContract::PutMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosurePatchMethodString" => ["/account/{1account_id}/user/home", RouterContract::PatchMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosureHeadMethodString" => ["/account/{1account_id}/user/home", RouterContract::HeadMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosureDeleteMethodString" => ["/account/{1account_id}/user/home", RouterContract::DeleteMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosureOptionsMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosureConnectMethodString" => ["/account/{1account_id}/user/home", RouterContract::ConnectMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosureAnyMethodString" => ["/account/{1account_id}/user/home", RouterContract::AnyMethod, function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosureGetMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosurePostMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PostMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosurePutMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PutMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosurePatchMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PatchMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosureHeadMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::HeadMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosureDeleteMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::DeleteMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosureOptionsMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosureConnectMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::ConnectMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteClosureAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], function() {}, InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNameGetMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNamePostMethodString" => ["/account/{1account_id}/user/home", RouterContract::PostMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNamePutMethodString" => ["/account/{1account_id}/user/home", RouterContract::PutMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNamePatchMethodString" => ["/account/{1account_id}/user/home", RouterContract::PatchMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNameHeadMethodString" => ["/account/{1account_id}/user/home", RouterContract::HeadMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNameDeleteMethodString" => ["/account/{1account_id}/user/home", RouterContract::DeleteMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNameOptionsMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNameConnectMethodString" => ["/account/{1account_id}/user/home", RouterContract::ConnectMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNameAnyMethodString" => ["/account/{1account_id}/user/home", RouterContract::AnyMethod, "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNameGetMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNamePostMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PostMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNamePutMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PutMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNamePatchMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PatchMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNameHeadMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::HeadMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNameDeleteMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::DeleteMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNameOptionsMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNameConnectMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::ConnectMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteFunctionNameAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], "phpinfo", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringGetMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringPostMethodString" => ["/account/{1account_id}/user/home", RouterContract::PostMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringPutMethodString" => ["/account/{1account_id}/user/home", RouterContract::PutMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringPatchMethodString" => ["/account/{1account_id}/user/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringHeadMethodString" => ["/account/{1account_id}/user/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringDeleteMethodString" => ["/account/{1account_id}/user/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringOptionsMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringConnectMethodString" => ["/account/{1account_id}/user/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringAnyMethodString" => ["/account/{1account_id}/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringGetMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringPostMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringPutMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringPatchMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringHeadMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringDeleteMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringOptionsMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringConnectMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
			"invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,],
		];

		// yield 100 random valid combinations
		for ($idx = 0; $idx < 100; ++$idx) {
			$methods = [];

			for ($idxMethod = mt_rand(1, count(self::allHttpMethods())); $idxMethod > 0; --$idxMethod) {
				$methods[] = self::randomHttpMethod();
			}

			$methods = array_unique($methods);
			yield [self::randomRoute(), $methods, $this->randomValidHandler(),];
		}
	}

	/**
	 * Test for Router::register().
	 *
	 * @dataProvider dataForTestRegister
	 *
	 * @param mixed $route The route to register.
	 * @param mixed $methods The HTTML method to register.
	 * @param mixed $handler The handler.
	 * @param string|null $exceptionClass The expected exception class, if any.
	 *
	 * @noinspection PhpDocMissingThrowsInspection Only test exceptions are thrown.
	 */
	public function testRegister($route, $methods, $handler, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$router = new Router();
		/** @noinspection PhpUnhandledExceptionInspection register() should not throw with test data. */
		$router->register($route, $methods, $handler);
		/** @noinspection PhpUnhandledExceptionInspection guaranteed not to throw with these arguments. */
		$match = self::accessibleMethod($router, "matchedRoute");

		if (is_string($methods)) {
			$methods = [$methods,];
		}

		foreach ($methods as $method) {
			if (RouterContract::AnyMethod === $method) {
				foreach (self::allHttpMethods() as $anyMethod) {
					$this->assertSame($route, $match(self::makeRequest($route, $anyMethod)));
				}
			} else {
				$this->assertSame($route, $match(self::makeRequest($route, $method)));
			}
		}
	}

	/**
	 * Data provider for testRoute().
	 *
	 * @return array[] The test data.
	 */
	public function dataForTestRoute(): array
	{
		return [
			"typicalGetWithNoParameters" => [RouterContract::GetMethod, "/home", RouterContract::GetMethod, "/home", function(Request $request): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalGetWithLongerPathAndNoParameters" => [RouterContract::GetMethod, "/admin/users/home", RouterContract::GetMethod, "/admin/users/home", function(Request $request): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/admin/users/home", $request->pathInfo());
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyGetWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::GetMethod, "/home", function(Request $request): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPostWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::PostMethod, "/home", function(Request $request): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPutWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::PutMethod, "/home", function(Request $request): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyDeleteWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::DeleteMethod, "/home", function(Request $request): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyHeadWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::HeadMethod, "/home", function(Request $request): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyOptionsWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::OptionsMethod, "/home", function(Request $request): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyConnectWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::ConnectMethod, "/home", function(Request $request): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPatchWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::PatchMethod, "/home", function(Request $request): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalGetWithParameterInt" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function(Request $request, int $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalGetWithParameterString" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function(Request $request, string $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalGetWithParameterFloat" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function(Request $request, float $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalGetWithParameterBoolTrueInt" => [RouterContract::GetMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/1", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalGetWithParameterBoolTrueString" => [RouterContract::GetMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/true", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalGetWithParameterBoolFalseInt" => [RouterContract::GetMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/0", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalGetWithParameterBoolFalseString" => [RouterContract::GetMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/false", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyGetWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function(Request $request, int $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyGetWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function(Request $request, string $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyGetWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function(Request $request, float $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyGetWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/1", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyGetWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/true", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyGetWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/0", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyGetWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/false", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPostWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PostMethod, "/edit/123", function(Request $request, int $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPostWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PostMethod, "/edit/123", function(Request $request, string $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPostWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PostMethod, "/edit/123", function(Request $request, float $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPostWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PostMethod, "/edit/1", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPostWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PostMethod, "/edit/true", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPostWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PostMethod, "/edit/0", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPostWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PostMethod, "/edit/false", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPutWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PutMethod, "/edit/123", function(Request $request, int $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPutWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PutMethod, "/edit/123", function(Request $request, string $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPutWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PutMethod, "/edit/123", function(Request $request, float $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPutWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PutMethod, "/edit/1", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPutWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PutMethod, "/edit/true", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPutWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PutMethod, "/edit/0", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPutWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PutMethod, "/edit/false", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyHeadWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::HeadMethod, "/edit/123", function(Request $request, int $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyHeadWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::HeadMethod, "/edit/123", function(Request $request, string $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyHeadWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::HeadMethod, "/edit/123", function(Request $request, float $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyHeadWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::HeadMethod, "/edit/1", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyHeadWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::HeadMethod, "/edit/true", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyHeadWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::HeadMethod, "/edit/0", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyHeadWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::HeadMethod, "/edit/false", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyConnectWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::ConnectMethod, "/edit/123", function(Request $request, int $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyConnectWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::ConnectMethod, "/edit/123", function(Request $request, string $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyConnectWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::ConnectMethod, "/edit/123", function(Request $request, float $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyConnectWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::ConnectMethod, "/edit/1", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyConnectWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::ConnectMethod, "/edit/true", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyConnectWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::ConnectMethod, "/edit/0", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyConnectWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::ConnectMethod, "/edit/false", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyDeleteWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::DeleteMethod, "/edit/123", function(Request $request, int $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyDeleteWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::DeleteMethod, "/edit/123", function(Request $request, string $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyDeleteWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::DeleteMethod, "/edit/123", function(Request $request, float $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyDeleteWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::DeleteMethod, "/edit/1", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyDeleteWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::DeleteMethod, "/edit/true", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyDeleteWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::DeleteMethod, "/edit/0", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyDeleteWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::DeleteMethod, "/edit/false", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPatchWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PatchMethod, "/edit/123", function(Request $request, int $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPatchWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PatchMethod, "/edit/123", function(Request $request, string $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPatchWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PatchMethod, "/edit/123", function(Request $request, float $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPatchWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PatchMethod, "/edit/1", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPatchWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PatchMethod, "/edit/true", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPatchWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PatchMethod, "/edit/0", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyPatchWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PatchMethod, "/edit/false", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyOptionsWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::OptionsMethod, "/edit/123", function(Request $request, int $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyOptionsWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::OptionsMethod, "/edit/123", function(Request $request, string $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyOptionsWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::OptionsMethod, "/edit/123", function(Request $request, float $id): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyOptionsWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::OptionsMethod, "/edit/1", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyOptionsWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::OptionsMethod, "/edit/true", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyOptionsWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::OptionsMethod, "/edit/0", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalAnyOptionsWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::OptionsMethod, "/edit/false", function(Request $request, bool $confirmed): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalGetWithParametersDifferentOrderManyTypes" => [RouterContract::GetMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::GetMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalGetWithAllParametersDifferentOrderManyTypes" => [RouterContract::GetMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::GetMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalPostWithParametersDifferentOrderManyTypes" => [RouterContract::PostMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::PostMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalPostWithAllParametersDifferentOrderManyTypes" => [RouterContract::PostMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::PostMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalPutWithParametersDifferentOrderManyTypes" => [RouterContract::PutMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::PutMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalPutWithAllParametersDifferentOrderManyTypes" => [RouterContract::PutMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::PutMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalHeadWithParametersDifferentOrderManyTypes" => [RouterContract::HeadMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::HeadMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalHeadWithAllParametersDifferentOrderManyTypes" => [RouterContract::HeadMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::HeadMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalOptionsWithParametersDifferentOrderManyTypes" => [RouterContract::OptionsMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::OptionsMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalOptionsWithAllParametersDifferentOrderManyTypes" => [RouterContract::OptionsMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::OptionsMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalDeleteWithParametersDifferentOrderManyTypes" => [RouterContract::DeleteMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::DeleteMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalDeleteWithAllParametersDifferentOrderManyTypes" => [RouterContract::DeleteMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::DeleteMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalPatchWithParametersDifferentOrderManyTypes" => [RouterContract::PatchMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::PatchMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalPatchWithAllParametersDifferentOrderManyTypes" => [RouterContract::PatchMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::PatchMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalConnectWithParametersDifferentOrderManyTypes" => [RouterContract::ConnectMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::ConnectMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalConnectWithAllParametersDifferentOrderManyTypes" => [RouterContract::ConnectMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::ConnectMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): Response {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}],
			"typicalUnroutableIncorrectMethodOneRegisteredMethod" => [RouterContract::GetMethod, "/", RouterContract::PostMethod, "/", function(Request $request, bool $confirmed): Response {
				$this->fail("Handler should not be called: Request method '{$request->method()}' should not match registered method '" . RouterContract::GetMethod . "'.");
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}, UnroutableRequestException::class,],
			"typicalUnroutableIncorrectMethodManyRegisteredMethods" => [[RouterContract::GetMethod, RouterContract::PostMethod,], "/", RouterContract::PutMethod, "/", function(Request $request, bool $confirmed): Response {
				$this->fail("Handler should not be called: Request method '{$request->method()}' should not match registered methods '" . implode("', '", [RouterContract::GetMethod, RouterContract::PostMethod,]) . "'.");
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}, UnroutableRequestException::class,],
			"typicalUnroutableNoMatchedRoute" => [RouterContract::GetMethod, "/", RouterContract::PostMethod, "/home", function(Request $request, bool $confirmed): Response {
				$this->fail("Handler should not be called: Request path '{$request->pathInfo()}' should not match registered route '/'.");
				return new class extends \Equit\Responses\AbstractResponse {
					public function content(): string
					{
						return "";
					}
				};
			}, UnroutableRequestException::class,],
		];
	}

	/**
	 * Test for Router::route()
	 *
	 * @dataProvider dataForTestRoute
	 *
	 * @param string|array<string> $routeMethods The HTTP methods to define for the test route.
	 * @param string $route The test route.
	 * @param string $requestMethod The HTTP method for the request to test with.
	 * @param string $requestPath The path for the request to test with.
	 * @param \Closure|null $handler The handler to register for the route.
	 * @param string|null $exceptionClass The class name of the expected exception, if any.
	 *
	 * @noinspection PhpDocMissingThrowsInspection Only exceptions thrown will be exptected test exceptions.
	 */
	public function testRoute($routeMethods, string $route, string $requestMethod, string $requestPath, ?Closure $handler, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$router = new Router();
		/** @noinspection PhpUnhandledExceptionInspection Should never throw with test data. */
		$router->register($route, $routeMethods, $handler);
		$request = self::makeRequest($requestPath, $requestMethod);
		/** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exceptions. */
		$router->route($request);
	}

	/**
	 * Data provider for testRouteConflicts()
	 *
	 * @return array[] The test data.
	 */
	public function dataForTestRouteConflicts(): array
	{
		return [
			"rootPathWithGetNoParameters" => [RouterContract::GetMethod, "/", RouterContract::GetMethod, "/",],
			"rootPathWithGet1Post2NoParametersNoConflict" => [RouterContract::GetMethod, "/", RouterContract::PostMethod, "/", false, ],
			"simplePathWithGetSingleParameter" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/{slug}",],
			"simplePathWithGetParametersInDifferentPositions" => [RouterContract::GetMethod, "/edit/{type}/{id}", RouterContract::GetMethod, "/{type}/{id}/edit", false,],
			"simplePathWithAny1Get2MultipleParameters" => [RouterContract::AnyMethod, "/edit/{id}/{force}/{really}", RouterContract::GetMethod, "/edit/{slug}/{id}/{field}",],
			"simplePathWithGet1Post2SingleParameterNoConflict" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::PostMethod, "/edit/{id}", false, ],
		];
	}

	/**
	 * Test identification of conflicting routes.
	 *
	 * @dataProvider dataForTestRouteConflicts
	 *
	 * @param string|array<string> $route1Methods The HTTP methods for the first route to register.
	 * @param string $route1 The path for the first route to register.
	 * @param string|array<string> $route2Methods The HTTP methods for the second route to register.
	 * @param string $route2 The path for the second route to register.
	 * @param bool $shouldConflict Whether the two registrations should result in a conflict. Defaults to true.
	 *
	 * @noinspection PhpDocMissingThrowsInspection Only test exceptions should be thrown.
	 */
	public function testRouteConflicts($route1Methods, string $route1, $route2Methods, string $route2, bool $shouldConflict = true): void
	{
		$accumulateRoutes = fn(array $routes, int $accumulation): int => $accumulation + count($routes);

		if ($shouldConflict) {
			$this->expectException(ConflictingRouteException::class);
		}

		$router = new Router();
		/** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
		$routeCollection = new ReflectionProperty($router, "m_routes");
		$routeCollection->setAccessible(true);
		/** @noinspection PhpUnhandledExceptionInspection Should never throw with test data. */
		$router->register($route1, $route1Methods, function() {});

		// fetch the route count so that we can assert that the registration of the second route adds to it if it
		// doesn't throw
		$routeCount = accumulate($routeCollection->getValue($router), $accumulateRoutes);
		/** @noinspection PhpUnhandledExceptionInspection Should only throw an expected test exception. */
		$router->register($route2, $route2Methods, function() {});
		$this->assertGreaterThan($routeCount, accumulate($routeCollection->getValue($router), $accumulateRoutes), "The registration of the second route succeeded but didn't add to the routes colleciton in the router.");
	}
}
