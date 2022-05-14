<?php

declare(strict_types=1);

use Equit\Test\Framework\TestCase;
use Equit\Exceptions\UnroutableRequestException;
use Equit\Contracts\Router as RouterContract;
use Equit\Router;
use Equit\Request;

/**
 * Test case for the Router class.
 *
 * @todo tests for route collision exceptions
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
	private static function makeRequest(string $pathInfo, string $method = RouterContract::GetMethod): Request
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
		$router->registerGet($route, $handler);
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
		$router->registerPost($route, $handler);
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
		$router->registerPut($route, $handler);
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
		$router->registerDelete($route, $handler);
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
		$router->registerOptions($route, $handler);
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
		$router->registerHead($route, $handler);
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
		$router->registerConnect($route, $handler);
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
		$router->registerPatch($route, $handler);
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

			// TODO tests for invalid method strings
			// TODO tests with other route strings
			// TODO tests with route strings containing parameters
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

		$route = "";

		for ($idx = 0; $idx < $components; ++$idx) {
			$route .= "/";

			if (20 > mt_rand(0, 100)) {
				$route .= $paramNames[mt_rand(0, count($paramNames) - 1)];
			} else {
				$route .= $componentNames[mt_rand(0, count($componentNames) - 1)];
			}
		}

		return $route;
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
	 * @dataProvider dataForTestRegister
	 *
	 * @param mixed $route The route to register.
	 * @param mixed $methods The HTTML method to register.
	 * @param mixed $handler The handler.
	 * @param string|null $exceptionClass The expected exception class, if any.
	 */
	public function testRegister($route, $methods, $handler, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$router = new Router();
		$router->register($route, $methods, $handler);
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

	public function dataForTestRoute(): array
	{
		return [
			"typicalGetWithNoParameters" => [RouterContract::GetMethod, "/home", RouterContract::GetMethod, "/home", function(Request $request): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
			}],
			"typicalGetWithLongerPathAndNoParameters" => [RouterContract::GetMethod, "/admin/users/home", RouterContract::GetMethod, "/admin/users/home", function(Request $request): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/admin/users/home", $request->pathInfo());
			}],
			"typicalAnyGetWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::GetMethod, "/home", function(Request $request): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
			}],
			"typicalAnyPostWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::PostMethod, "/home", function(Request $request): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
			}],
			"typicalAnyPutWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::PutMethod, "/home", function(Request $request): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
			}],
			"typicalAnyDeleteWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::DeleteMethod, "/home", function(Request $request): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
			}],
			"typicalAnyHeadWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::HeadMethod, "/home", function(Request $request): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
			}],
			"typicalAnyOptionsWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::OptionsMethod, "/home", function(Request $request): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
			}],
			"typicalAnyConnectWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::ConnectMethod, "/home", function(Request $request): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
			}],
			"typicalAnyPatchWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::PatchMethod, "/home", function(Request $request): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/home", $request->pathInfo());
			}],
			"typicalGetWithParameterInt" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function(Request $request, int $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
			}],
			"typicalGetWithParameterString" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function(Request $request, string $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
			}],
			"typicalGetWithParameterFloat" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function(Request $request, float $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
			}],
			"typicalGetWithParameterBoolTrueInt" => [RouterContract::GetMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/1", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalGetWithParameterBoolTrueString" => [RouterContract::GetMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/true", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalGetWithParameterBoolFalseInt" => [RouterContract::GetMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/0", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalGetWithParameterBoolFalseString" => [RouterContract::GetMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/false", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyGetWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function(Request $request, int $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
			}],
			"typicalAnyGetWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function(Request $request, string $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
			}],
			"typicalAnyGetWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function(Request $request, float $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
			}],
			"typicalAnyGetWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/1", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyGetWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/true", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyGetWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/0", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyGetWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/false", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyPostWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PostMethod, "/edit/123", function(Request $request, int $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
			}],
			"typicalAnyPostWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PostMethod, "/edit/123", function(Request $request, string $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
			}],
			"typicalAnyPostWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PostMethod, "/edit/123", function(Request $request, float $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
			}],
			"typicalAnyPostWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PostMethod, "/edit/1", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyPostWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PostMethod, "/edit/true", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyPostWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PostMethod, "/edit/0", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyPostWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PostMethod, "/edit/false", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyPutWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PutMethod, "/edit/123", function(Request $request, int $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
			}],
			"typicalAnyPutWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PutMethod, "/edit/123", function(Request $request, string $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
			}],
			"typicalAnyPutWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PutMethod, "/edit/123", function(Request $request, float $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
			}],
			"typicalAnyPutWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PutMethod, "/edit/1", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyPutWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PutMethod, "/edit/true", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyPutWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PutMethod, "/edit/0", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyPutWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PutMethod, "/edit/false", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyHeadWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::HeadMethod, "/edit/123", function(Request $request, int $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
			}],
			"typicalAnyHeadWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::HeadMethod, "/edit/123", function(Request $request, string $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
			}],
			"typicalAnyHeadWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::HeadMethod, "/edit/123", function(Request $request, float $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
			}],
			"typicalAnyHeadWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::HeadMethod, "/edit/1", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyHeadWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::HeadMethod, "/edit/true", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyHeadWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::HeadMethod, "/edit/0", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyHeadWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::HeadMethod, "/edit/false", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyConnectWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::ConnectMethod, "/edit/123", function(Request $request, int $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
			}],
			"typicalAnyConnectWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::ConnectMethod, "/edit/123", function(Request $request, string $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
			}],
			"typicalAnyConnectWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::ConnectMethod, "/edit/123", function(Request $request, float $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
			}],
			"typicalAnyConnectWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::ConnectMethod, "/edit/1", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyConnectWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::ConnectMethod, "/edit/true", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyConnectWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::ConnectMethod, "/edit/0", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyConnectWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::ConnectMethod, "/edit/false", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyDeleteWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::DeleteMethod, "/edit/123", function(Request $request, int $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
			}],
			"typicalAnyDeleteWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::DeleteMethod, "/edit/123", function(Request $request, string $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
			}],
			"typicalAnyDeleteWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::DeleteMethod, "/edit/123", function(Request $request, float $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
			}],
			"typicalAnyDeleteWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::DeleteMethod, "/edit/1", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyDeleteWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::DeleteMethod, "/edit/true", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyDeleteWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::DeleteMethod, "/edit/0", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyDeleteWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::DeleteMethod, "/edit/false", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyPatchWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PatchMethod, "/edit/123", function(Request $request, int $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
			}],
			"typicalAnyPatchWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PatchMethod, "/edit/123", function(Request $request, string $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
			}],
			"typicalAnyPatchWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PatchMethod, "/edit/123", function(Request $request, float $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
			}],
			"typicalAnyPatchWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PatchMethod, "/edit/1", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyPatchWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PatchMethod, "/edit/true", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyPatchWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PatchMethod, "/edit/0", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyPatchWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PatchMethod, "/edit/false", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyOptionsWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::OptionsMethod, "/edit/123", function(Request $request, int $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123, $id);
			}],
			"typicalAnyOptionsWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::OptionsMethod, "/edit/123", function(Request $request, string $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame("123", $id);
			}],
			"typicalAnyOptionsWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::OptionsMethod, "/edit/123", function(Request $request, float $id): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/123", $request->pathInfo());
				$this->assertSame(123.0, $id);
			}],
			"typicalAnyOptionsWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::OptionsMethod, "/edit/1", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/1", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyOptionsWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::OptionsMethod, "/edit/true", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/true", $request->pathInfo());
				$this->assertSame(true, $confirmed);
			}],
			"typicalAnyOptionsWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::OptionsMethod, "/edit/0", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/0", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalAnyOptionsWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::OptionsMethod, "/edit/false", function(Request $request, bool $confirmed): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/edit/false", $request->pathInfo());
				$this->assertSame(false, $confirmed);
			}],
			"typicalGetWithParametersDifferentOrderManyTypes" => [RouterContract::GetMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::GetMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalGetWithAllParametersDifferentOrderManyTypes" => [RouterContract::GetMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::GetMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalPostWithParametersDifferentOrderManyTypes" => [RouterContract::PostMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::PostMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalPostWithAllParametersDifferentOrderManyTypes" => [RouterContract::PostMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::PostMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalPutWithParametersDifferentOrderManyTypes" => [RouterContract::PutMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::PutMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalPutWithAllParametersDifferentOrderManyTypes" => [RouterContract::PutMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::PutMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalHeadWithParametersDifferentOrderManyTypes" => [RouterContract::HeadMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::HeadMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalHeadWithAllParametersDifferentOrderManyTypes" => [RouterContract::HeadMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::HeadMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalOptionsWithParametersDifferentOrderManyTypes" => [RouterContract::OptionsMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::OptionsMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalOptionsWithAllParametersDifferentOrderManyTypes" => [RouterContract::OptionsMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::OptionsMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalDeleteWithParametersDifferentOrderManyTypes" => [RouterContract::DeleteMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::DeleteMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalDeleteWithAllParametersDifferentOrderManyTypes" => [RouterContract::DeleteMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::DeleteMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalPatchWithParametersDifferentOrderManyTypes" => [RouterContract::PatchMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::PatchMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalPatchWithAllParametersDifferentOrderManyTypes" => [RouterContract::PatchMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::PatchMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalConnectWithParametersDifferentOrderManyTypes" => [RouterContract::ConnectMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::ConnectMethod, "/object/article/9563/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(9563, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalConnectWithAllParametersDifferentOrderManyTypes" => [RouterContract::ConnectMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::ConnectMethod, "/article/123456789/set/status/draft", function(Request $request, int $id, string $type, string $action, string $property, string $value): void {
				$this->assertInstanceOf(Request::class, $request);
				$this->assertSame("/article/123456789/set/status/draft", $request->pathInfo());
				$this->assertSame("article", $type);
				$this->assertSame(123456789, $id);
				$this->assertSame("set", $action);
				$this->assertSame("status", $property);
				$this->assertSame("draft", $value);
			}],
			"typicalUnroutableIncorrectMethodOneRegisteredMethod" => [RouterContract::GetMethod, "/", RouterContract::PostMethod, "/", function(Request $request, bool $confirmed): void {
				$this->fail("Handler should not be called: Request method '{$request->method()}' should not match registered method '" . RouterContract::GetMethod . "'.");
			}, UnroutableRequestException::class,],
			"typicalUnroutableIncorrectMethodManyRegisteredMethods" => [[RouterContract::GetMethod, RouterContract::PostMethod,], "/", RouterContract::PutMethod, "/", function(Request $request, bool $confirmed): void {
				$this->fail("Handler should not be called: Request method '{$request->method()}' should not match registered methods '" . implode("', '", [RouterContract::GetMethod, RouterContract::PostMethod,]) . "'.");
			}, UnroutableRequestException::class,],
			"typicalUnroutableNoMatchedRoute" => [RouterContract::GetMethod, "/", RouterContract::PostMethod, "/home", function(Request $request, bool $confirmed): void {
				$this->fail("Handler should not be called: Request path '{$request->pathInfo()}' should not match registered route '/'.");
			}, UnroutableRequestException::class,],
		];
	}

	/**
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
		$router->register($route, $routeMethods, $handler);
		$request = self::makeRequest($requestPath, $requestMethod);
		$router->route($request);
	}
}
