<?php

declare(strict_types=1);

use Equit\Request;
use Equit\Router;
use Equit\Test\Framework\TestCase;

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
	private static function makeRequest(string $pathInfo, string $method = Router::GetMethod): Request
	{
		return new class($pathInfo, $method) extends Request
		{
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
		$this->assertSame($route, $match(self::makeRequest($route, Router::PostMethod)));
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
		$this->assertSame($route, $match(self::makeRequest($route, Router::PutMethod)));
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
		$this->assertSame($route, $match(self::makeRequest($route, Router::DeleteMethod)));
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
		$this->assertSame($route, $match(self::makeRequest($route, Router::OptionsMethod)));
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
		$this->assertSame($route, $match(self::makeRequest($route, Router::HeadMethod)));
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
		$this->assertSame($route, $match(self::makeRequest($route, Router::ConnectMethod)));
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
		$this->assertSame($route, $match(self::makeRequest($route, Router::PatchMethod)));
	}
}
