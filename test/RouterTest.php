<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 */

declare(strict_types=1);

namespace BeadTests;

use Bead\Application;
use Bead\Contracts\Logger;
use Bead\Contracts\Response;
use Bead\Exceptions\ConflictingRouteException;
use Bead\Exceptions\DuplicateRouteParameterNameException;
use Bead\Exceptions\InvalidRouteParameterNameException;
use BeadTests\Framework\TestCase;
use Bead\Exceptions\UnroutableRequestException;
use Bead\Contracts\Router as RouterContract;
use Bead\Router;
use Bead\Request;
use Bead\Testing\XRay;
use Closure;
use InvalidArgumentException;
use Mockery;
use ReflectionProperty;

use function Bead\Helpers\Iterable\accumulate;

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
    private static int $nullStaticRouteHandlerCallCount = 0;

    /**
     * @var int The number of times the nullRouteHandler was called during a test.
     *
     * This is useful for asserting that the handler was called when a request was routed.
     */
    private int $nullRouteHandlerCallCount = 0;

    /**
     * Route handler that does nothing except increment a call counter.
     */
    public static function nullStaticRouteHandler(): void
    {
        ++self::$nullStaticRouteHandlerCallCount;
    }

    /**
     * Route handler that does nothing except increment a call counter.
     */
    public function nullRouteHandler(): void
    {
        ++$this->nullRouteHandlerCallCount;
    }

    /**
     * Make a Request test double with a given pathInfo and HTTP method.
     *
     * @param string $pathInfo The path_info for the request (used in route matching).
     * @param string $method The HTTP method.
     *
     * @return \Bead\Request
     */
    protected static function makeRequest(string $pathInfo, string $method = RouterContract::GetMethod): Request
    {
        return new class ($pathInfo, $method) extends Request
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
                return function () {
                };
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
            "typicalRootClosure" => ["/", function () {
            },],
            "typicalRootFunctionName" => ["/", "phpinfo",],
            "typicalRootStaticMethodString" => ["/", "self::nullStaticRouteHandler",],
            "invalidRootEmptyArray" => ["/", [], InvalidArgumentException::class,],
            "invalidRootArrayWithSingleFunctionName" => ["/", ["phpinfo"], InvalidArgumentException::class,],
            "extremeRootWithNonExistentStaticMethod" => ["/", ["foo", "bar"],],
            "extremeRootWithNonExistentStaticMethodString" => ["/", "self::fooBar",],
            "extremeRootWithNonExistentFunctionName" => ["/", "foobar",],
        ];
    }

    /**
     * @dataProvider dataForTestRegisterSingleMethod
     */
    public function testRegisterGet(string $route, callable|array|string $handler, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
        $router->registerGet($route, $handler);
        self::assertSame($route, $router->matchedRoute(self::makeRequest($route)));
    }

    /**
     * @dataProvider dataForTestRegisterSingleMethod
     */
    public function testRegisterPost(string $route, callable|array|string $handler, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
        $router->registerPost($route, $handler);
        /** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
        self::assertSame($route, $router->matchedRoute(self::makeRequest($route, RouterContract::PostMethod)));
    }

    /**
     * @dataProvider dataForTestRegisterSingleMethod
     */
    public function testRegisterPut(string $route, callable|array|string $handler, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
        $router->registerPut($route, $handler);
        /** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
        self::assertSame($route, $router->matchedRoute(self::makeRequest($route, RouterContract::PutMethod)));
    }

    /**
     * @dataProvider dataForTestRegisterSingleMethod
     */
    public function testRegisterDelete(string $route, callable|array|string $handler, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
        $router->registerDelete($route, $handler);
        /** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
        self::assertSame($route, $router->matchedRoute(self::makeRequest($route, RouterContract::DeleteMethod)));
    }

    /**
     * @dataProvider dataForTestRegisterSingleMethod
     */
    public function testRegisterOptions(string $route, callable|array|string $handler, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
        $router->registerOptions($route, $handler);
        /** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
        self::assertSame($route, $router->matchedRoute(self::makeRequest($route, RouterContract::OptionsMethod)));
    }

    /**
     * @dataProvider dataForTestRegisterSingleMethod
     */
    public function testRegisterHead(string $route, callable|array|string $handler, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
        $router->registerHead($route, $handler);
        /** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
        self::assertSame($route, $router->matchedRoute(self::makeRequest($route, RouterContract::HeadMethod)));
    }

    /**
     * @dataProvider dataForTestRegisterSingleMethod
     */
    public function testRegisterConnect(string $route, callable|array|string $handler, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
        $router->registerConnect($route, $handler);
        /** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
        self::assertSame($route, $router->matchedRoute(self::makeRequest($route, RouterContract::ConnectMethod)));
    }

    /**
     * @dataProvider dataForTestRegisterSingleMethod
     */
    public function testRegisterPatch(string $route, callable|array|string $handler, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exception. */
        $router->registerPatch($route, $handler);
        /** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
        self::assertSame($route, $router->matchedRoute(self::makeRequest($route, RouterContract::PatchMethod)));
    }

    /**
     * Data provider for testRegister.
     *
     * @return iterable The test data.
     */
    public function dataForTestRegister(): iterable
    {
        // tests for a single HTTP method, as string and as single array element
        yield "typicalRootStaticMethodGetMethodString" => ["/", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodPostMethodString" => ["/", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodPutMethodString" => ["/", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodPatchMethodString" => ["/", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodHeadMethodString" => ["/", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodDeleteMethodString" => ["/", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodOptionsMethodString" => ["/", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodConnectMethodString" => ["/", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodAnyMethodString" => ["/", RouterContract::AnyMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodGetMethodArray" => ["/", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodPostMethodArray" => ["/", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodPutMethodArray" => ["/", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodPatchMethodArray" => ["/", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodHeadMethodArray" => ["/", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodOptionsMethodArray" => ["/", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodAnyMethodArray" => ["/", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootMethodGetMethodString" => ["/", RouterContract::GetMethod, [$this, "nullRouteHandler"],];
        yield "typicalRootMethodPostMethodString" => ["/", RouterContract::PostMethod, [$this, "nullRouteHandler"],];
        yield "typicalRootMethodPutMethodString" => ["/", RouterContract::PutMethod, [$this, "nullRouteHandler"],];
        yield "typicalRootMethodPatchMethodString" => ["/", RouterContract::PatchMethod, [$this, "nullRouteHandler"],];
        yield "typicalRootMethodHeadMethodString" => ["/", RouterContract::HeadMethod, [$this, "nullRouteHandler"],];
        yield "typicalRootMethodDeleteMethodString" => ["/", RouterContract::DeleteMethod, [$this, "nullRouteHandler"],];
        yield "typicalRootMethodOptionsMethodString" => ["/", RouterContract::GetMethod, [$this, "nullRouteHandler"],];
        yield "typicalRootMethodConnectMethodString" => ["/", RouterContract::ConnectMethod, [$this, "nullRouteHandler"],];
        yield "typicalRootMethodAnyMethodString" => ["/", RouterContract::AnyMethod, [$this, "nullRouteHandler"],];
        yield "typicalRootMethodGetMethodArray" => ["/", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],];
        yield "typicalRootMethodPostMethodArray" => ["/", [RouterContract::PostMethod,], [$this, "nullRouteHandler"],];
        yield "typicalRootMethodPutMethodArray" => ["/", [RouterContract::PutMethod,], [$this, "nullRouteHandler"],];
        yield "typicalRootMethodPatchMethodArray" => ["/", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"],];
        yield "typicalRootMethodHeadMethodArray" => ["/", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"],];
        yield "typicalRootMethodDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"],];
        yield "typicalRootMethodOptionsMethodArray" => ["/", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],];
        yield "typicalRootMethodConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"],];
        yield "typicalRootMethodAnyMethodArray" => ["/", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"],];

        yield "typicalRootClosureGetMethodString" => [
            "/",
            RouterContract::GetMethod,
            function () {
            },
        ];

        yield "typicalRootClosurePostMethodString" => [
            "/",
            RouterContract::PostMethod,
            function () {
            },
        ];

        yield "typicalRootClosurePutMethodString" => [
            "/",
            RouterContract::PutMethod,
            function () {
            },
        ];

        yield "typicalRootClosurePatchMethodString" => [
            "/",
            RouterContract::PatchMethod,
            function () {
            },
        ];

        yield "typicalRootClosureHeadMethodString" => [
            "/",
            RouterContract::HeadMethod,
            function () {
            },
        ];

        yield "typicalRootClosureDeleteMethodString" => [
            "/",
            RouterContract::DeleteMethod,
            function () {
            },
        ];

        yield "typicalRootClosureOptionsMethodString" => [
            "/",
            RouterContract::GetMethod,
            function () {
            },
        ];

        yield "typicalRootClosureConnectMethodString" => [
            "/",
            RouterContract::ConnectMethod,
            function () {
            },
        ];

        yield "typicalRootClosureAnyMethodString" => [
            "/",
            RouterContract::AnyMethod,
            function () {
            },
        ];

        yield "typicalRootClosureGetMethodArray" => [
            "/",
            [RouterContract::GetMethod,],
            function () {
            },
        ];

        yield "typicalRootClosurePostMethodArray" => [
            "/",
            [RouterContract::PostMethod,],
            function () {
            },
        ];

        yield "typicalRootClosurePutMethodArray" => [
            "/",
            [RouterContract::PutMethod,],
            function () {
            },
        ];

        yield "typicalRootClosurePatchMethodArray" => [
            "/",
            [RouterContract::PatchMethod,],
            function () {
            },
        ];

        yield "typicalRootClosureHeadMethodArray" => [
            "/",
            [RouterContract::HeadMethod,],
            function () {
            },
        ];

        yield "typicalRootClosureDeleteMethodArray" => [
            "/",
            [RouterContract::DeleteMethod,],
            function () {
            },
        ];

        yield "typicalRootClosureOptionsMethodArray" => [
            "/",
            [RouterContract::GetMethod,],
            function () {
            },
        ];

        yield "typicalRootClosureConnectMethodArray" => [
            "/",
            [RouterContract::ConnectMethod,],
            function () {
            },
        ];

        yield "typicalRootClosureAnyMethodArray" => [
            "/",
            [RouterContract::AnyMethod,],
            function () {
            },
        ];

        yield "typicalRootFunctionNameGetMethodString" => [
            "/",
            RouterContract::GetMethod,
            "phpinfo",
        ];

        yield "typicalRootFunctionNamePostMethodString" => ["/", RouterContract::PostMethod, "phpinfo",];
        yield "typicalRootFunctionNamePutMethodString" => ["/", RouterContract::PutMethod, "phpinfo",];
        yield "typicalRootFunctionNamePatchMethodString" => ["/", RouterContract::PatchMethod, "phpinfo",];
        yield "typicalRootFunctionNameHeadMethodString" => ["/", RouterContract::HeadMethod, "phpinfo",];
        yield "typicalRootFunctionNameDeleteMethodString" => ["/", RouterContract::DeleteMethod, "phpinfo",];
        yield "typicalRootFunctionNameOptionsMethodString" => ["/", RouterContract::GetMethod, "phpinfo",];
        yield "typicalRootFunctionNameConnectMethodString" => ["/", RouterContract::ConnectMethod, "phpinfo",];
        yield "typicalRootFunctionNameAnyMethodString" => ["/", RouterContract::AnyMethod, "phpinfo",];
        yield "typicalRootFunctionNameGetMethodArray" => ["/", [RouterContract::GetMethod,], "phpinfo",];
        yield "typicalRootFunctionNamePostMethodArray" => ["/", [RouterContract::PostMethod,], "phpinfo",];
        yield "typicalRootFunctionNamePutMethodArray" => ["/", [RouterContract::PutMethod,], "phpinfo",];
        yield "typicalRootFunctionNamePatchMethodArray" => ["/", [RouterContract::PatchMethod,], "phpinfo",];
        yield "typicalRootFunctionNameHeadMethodArray" => ["/", [RouterContract::HeadMethod,], "phpinfo",];
        yield "typicalRootFunctionNameDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], "phpinfo",];
        yield "typicalRootFunctionNameOptionsMethodArray" => ["/", [RouterContract::GetMethod,], "phpinfo",];
        yield "typicalRootFunctionNameConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], "phpinfo",];
        yield "typicalRootFunctionNameAnyMethodArray" => ["/", [RouterContract::AnyMethod,], "phpinfo",];
        yield "typicalRootStaticMethodStringGetMethodString" => ["/", RouterContract::GetMethod, "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringPostMethodString" => ["/", RouterContract::PostMethod, "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringPutMethodString" => ["/", RouterContract::PutMethod, "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringPatchMethodString" => ["/", RouterContract::PatchMethod, "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringHeadMethodString" => ["/", RouterContract::HeadMethod, "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringDeleteMethodString" => ["/", RouterContract::DeleteMethod, "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringOptionsMethodString" => ["/", RouterContract::GetMethod, "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringConnectMethodString" => ["/", RouterContract::ConnectMethod, "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringAnyMethodString" => ["/", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringGetMethodArray" => ["/", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringPostMethodArray" => ["/", [RouterContract::PostMethod,], "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringPutMethodArray" => ["/", [RouterContract::PutMethod,], "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringPatchMethodArray" => ["/", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringHeadMethodArray" => ["/", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringOptionsMethodArray" => ["/", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringAnyMethodArray" => ["/", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];

        yield "invalidRootEmptyArrayGetMethodString" => [
            "/",
            RouterContract::GetMethod,
            [],
            InvalidArgumentException::class,
        ];

        yield "invalidRootEmptyArrayPostMethodString" => [
            "/",
            RouterContract::PostMethod,
            [],
            InvalidArgumentException::class,
        ];

        yield "invalidRootEmptyArrayPutMethodString" => ["/", RouterContract::PutMethod, [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayPatchMethodString" => ["/", RouterContract::PatchMethod, [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayHeadMethodString" => ["/", RouterContract::HeadMethod, [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayDeleteMethodString" => ["/", RouterContract::DeleteMethod, [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayOptionsMethodString" => ["/", RouterContract::GetMethod, [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayConnectMethodString" => ["/", RouterContract::ConnectMethod, [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayAnyMethodString" => ["/", RouterContract::AnyMethod, [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayGetMethodArray" => ["/", [RouterContract::GetMethod,], [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayPostMethodArray" => ["/", [RouterContract::PostMethod,], [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayPutMethodArray" => ["/", [RouterContract::PutMethod,], [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayPatchMethodArray" => ["/", [RouterContract::PatchMethod,], [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayHeadMethodArray" => ["/", [RouterContract::HeadMethod,], [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayOptionsMethodArray" => ["/", [RouterContract::GetMethod,], [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], [], InvalidArgumentException::class,];
        yield "invalidRootEmptyArrayAnyMethodArray" => ["/", [RouterContract::AnyMethod,], [], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNameGetMethodString" => ["/", RouterContract::GetMethod, ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNamePostMethodString" => ["/", RouterContract::PostMethod, ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNamePutMethodString" => ["/", RouterContract::PutMethod, ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNamePatchMethodString" => ["/", RouterContract::PatchMethod, ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNameHeadMethodString" => ["/", RouterContract::HeadMethod, ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNameDeleteMethodString" => ["/", RouterContract::DeleteMethod, ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNameOptionsMethodString" => ["/", RouterContract::GetMethod, ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNameConnectMethodString" => ["/", RouterContract::ConnectMethod, ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNameAnyMethodString" => ["/", RouterContract::AnyMethod, ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNameGetMethodArray" => ["/", [RouterContract::GetMethod,], ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNamePostMethodArray" => ["/", [RouterContract::PostMethod,], ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNamePutMethodArray" => ["/", [RouterContract::PutMethod,], ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNamePatchMethodArray" => ["/", [RouterContract::PatchMethod,], ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNameHeadMethodArray" => ["/", [RouterContract::HeadMethod,], ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNameDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNameOptionsMethodArray" => ["/", [RouterContract::GetMethod,], ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNameConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], ["phpinfo"], InvalidArgumentException::class,];
        yield "invalidRootArrayWithSingleFunctionNameAnyMethodArray" => ["/", [RouterContract::AnyMethod,], ["phpinfo"], InvalidArgumentException::class,];
        yield "extremeRootArrayWithNonExistentStaticMethodGetMethodString" => ["/", RouterContract::GetMethod, ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodPostMethodString" => ["/", RouterContract::PostMethod, ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodPutMethodString" => ["/", RouterContract::PutMethod, ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodPatchMethodString" => ["/", RouterContract::PatchMethod, ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodHeadMethodString" => ["/", RouterContract::HeadMethod, ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodDeleteMethodString" => ["/", RouterContract::DeleteMethod, ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodOptionsMethodString" => ["/", RouterContract::GetMethod, ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodConnectMethodString" => ["/", RouterContract::ConnectMethod, ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodAnyMethodString" => ["/", RouterContract::AnyMethod, ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodGetMethodArray" => ["/", [RouterContract::GetMethod,], ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodPostMethodArray" => ["/", [RouterContract::PostMethod,], ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodPutMethodArray" => ["/", [RouterContract::PutMethod,], ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodPatchMethodArray" => ["/", [RouterContract::PatchMethod,], ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodHeadMethodArray" => ["/", [RouterContract::HeadMethod,], ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodOptionsMethodArray" => ["/", [RouterContract::GetMethod,], ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodAnyMethodArray" => ["/", [RouterContract::AnyMethod,], ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodStringGetMethodString" => ["/", RouterContract::GetMethod, "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringPostMethodString" => ["/", RouterContract::PostMethod, "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringPutMethodString" => ["/", RouterContract::PutMethod, "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringPatchMethodString" => ["/", RouterContract::PatchMethod, "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringHeadMethodString" => ["/", RouterContract::HeadMethod, "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringDeleteMethodString" => ["/", RouterContract::DeleteMethod, "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringOptionsMethodString" => ["/", RouterContract::GetMethod, "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringConnectMethodString" => ["/", RouterContract::ConnectMethod, "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringAnyMethodString" => ["/", RouterContract::AnyMethod, "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringGetMethodArray" => ["/", [RouterContract::GetMethod,], "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringPostMethodArray" => ["/", [RouterContract::PostMethod,], "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringPutMethodArray" => ["/", [RouterContract::PutMethod,], "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringPatchMethodArray" => ["/", [RouterContract::PatchMethod,], "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringHeadMethodArray" => ["/", [RouterContract::HeadMethod,], "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringOptionsMethodArray" => ["/", [RouterContract::GetMethod,], "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringAnyMethodArray" => ["/", [RouterContract::AnyMethod,], "self::fooBar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringGetMethodString" => ["/", RouterContract::GetMethod, "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringPostMethodString" => ["/", RouterContract::PostMethod, "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringPutMethodString" => ["/", RouterContract::PutMethod, "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringPatchMethodString" => ["/", RouterContract::PatchMethod, "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringHeadMethodString" => ["/", RouterContract::HeadMethod, "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringDeleteMethodString" => ["/", RouterContract::DeleteMethod, "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringOptionsMethodString" => ["/", RouterContract::GetMethod, "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringConnectMethodString" => ["/", RouterContract::ConnectMethod, "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringAnyMethodString" => ["/", RouterContract::AnyMethod, "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringGetMethodArray" => ["/", [RouterContract::GetMethod,], "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringPostMethodArray" => ["/", [RouterContract::PostMethod,], "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringPutMethodArray" => ["/", [RouterContract::PutMethod,], "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringPatchMethodArray" => ["/", [RouterContract::PatchMethod,], "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringHeadMethodArray" => ["/", [RouterContract::HeadMethod,], "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringDeleteMethodArray" => ["/", [RouterContract::DeleteMethod,], "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringOptionsMethodArray" => ["/", [RouterContract::GetMethod,], "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringConnectMethodArray" => ["/", [RouterContract::ConnectMethod,], "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringAnyMethodArray" => ["/", [RouterContract::AnyMethod,], "foobar",];

        // tests for multiple HTTP methods at once
        yield "typicalRootGetAndPostStaticMethodArray" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], [self::class, "nullRouteHandler"],];
        yield "typicalRootGetAndPostStaticMethodString" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], "self::nullStaticRouteHandler",];
        yield "typicalRootGetAndPostMethodArray" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], [$this, "nullRouteHandler"],];
        yield "typicalRootGetAndPostClosure" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], function () {
        },];
        yield "typicalRootGetAndPostFunctionName" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], "phpinfo",];

        // test duplicate HTTP methods don't attempt to register a handler more than once for the same method and
        // route - the router should array_unique() the methods to avoid throwing ConflictingRouteException
        yield "extremeRootDuplicatedMethodStaticMethodArray" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], [self::class, "nullRouteHandler"],];
        yield "extremeRootDuplicatedMethodStaticMethodString" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], "self::nullStaticRouteHandler",];
        yield "extremeRootDuplicatedMethodMethodArray" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], [$this, "nullRouteHandler"],];
        yield "extremeRootDuplicatedMethodClosure" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], function () {
        },];
        yield "extremeRootDuplicatedMethodFunctionName" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], "phpinfo",];

        // tests for invalid methods
        yield "invalidRootWithInvalidMethodString" => ["/", "foo", "phpinfo", InvalidArgumentException::class];
        yield "invalidRootWithInvalidMethodArray" => ["/", ["foo"], "phpinfo", InvalidArgumentException::class];
        yield "invalidRootWithInvalidMethodInOtherwiseValidArray" => ["/", ["foo", RouterContract::GetMethod, RouterContract::PostMethod,], "phpinfo", InvalidArgumentException::class];

        // tests with other route strings
        yield "typicalHomeStaticMethodGetMethodString" => ["/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodPostMethodString" => ["/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodPutMethodString" => ["/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodPatchMethodString" => ["/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodHeadMethodString" => ["/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodDeleteMethodString" => ["/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodOptionsMethodString" => ["/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodConnectMethodString" => ["/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodGetMethodArray" => ["/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodPostMethodArray" => ["/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodPutMethodArray" => ["/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodPatchMethodArray" => ["/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodHeadMethodArray" => ["/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodDeleteMethodArray" => ["/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodOptionsMethodArray" => ["/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodConnectMethodArray" => ["/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeMethodGetMethodString" => ["/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodPostMethodString" => ["/home", RouterContract::PostMethod, [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodPutMethodString" => ["/home", RouterContract::PutMethod, [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodPatchMethodString" => ["/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodHeadMethodString" => ["/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodDeleteMethodString" => ["/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodOptionsMethodString" => ["/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodConnectMethodString" => ["/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodAnyMethodString" => ["/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodGetMethodArray" => ["/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodPostMethodArray" => ["/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodPutMethodArray" => ["/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodPatchMethodArray" => ["/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodHeadMethodArray" => ["/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodDeleteMethodArray" => ["/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodOptionsMethodArray" => ["/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodConnectMethodArray" => ["/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"],];
        yield "typicalHomeMethodAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"],];

        yield "typicalHomeClosureGetMethodString" => [
            "/home",
            RouterContract::GetMethod,
            function () {
            },
        ];

        yield "typicalHomeClosurePostMethodString" => [
            "/home",
            RouterContract::PostMethod,
            function () {
            },
        ];

        yield "typicalHomeClosurePutMethodString" => [
            "/home",
            RouterContract::PutMethod,
            function () {
            },
        ];

        yield "typicalHomeClosurePatchMethodString" => [
            "/home",
            RouterContract::PatchMethod,
            function () {
            },
        ];

        yield "typicalHomeClosureHeadMethodString" => [
            "/home",
            RouterContract::HeadMethod,
            function () {
            },
        ];

        yield "typicalHomeClosureDeleteMethodString" => [
            "/home",
            RouterContract::DeleteMethod,
            function () {
            },
        ];

        yield "typicalHomeClosureOptionsMethodString" => [
            "/home",
            RouterContract::GetMethod,
            function () {
            },
        ];

        yield "typicalHomeClosureConnectMethodString" => [
            "/home",
            RouterContract::ConnectMethod,
            function () {
            },
        ];

        yield "typicalHomeClosureAnyMethodString" => [
            "/home",
            RouterContract::AnyMethod,
            function () {
            },
        ];

        yield "typicalHomeClosureGetMethodArray" => [
            "/home",
            [RouterContract::GetMethod,],
            function () {
            },
        ];

        yield "typicalHomeClosurePostMethodArray" => [
            "/home",
            [RouterContract::PostMethod,],
            function () {
            },
        ];

        yield "typicalHomeClosurePutMethodArray" => [
            "/home",
            [RouterContract::PutMethod,],
            function () {
            },
        ];

        yield "typicalHomeClosurePatchMethodArray" => [
            "/home",
            [RouterContract::PatchMethod,],
            function () {
            },
        ];

        yield "typicalHomeClosureHeadMethodArray" => [
            "/home",
            [RouterContract::HeadMethod,],
            function () {
            },
        ];

        yield "typicalHomeClosureDeleteMethodArray" => [
            "/home",
            [RouterContract::DeleteMethod,],
            function () {
            },
        ];

        yield "typicalHomeClosureOptionsMethodArray" => [
            "/home",
            [RouterContract::GetMethod,],
            function () {
            },
        ];

        yield "typicalHomeClosureConnectMethodArray" => [
            "/home",
            [RouterContract::ConnectMethod,],
            function () {
            },
        ];

        yield "typicalHomeClosureAnyMethodArray" => [
            "/home",
            [RouterContract::AnyMethod,],
            function () {
            },
        ];

        yield "typicalHomeFunctionNameGetMethodString" => [
            "/home",
            RouterContract::GetMethod,
            "phpinfo",
        ];

        yield "typicalHomeFunctionNamePostMethodString" => ["/home", RouterContract::PostMethod, "phpinfo",];
        yield "typicalHomeFunctionNamePutMethodString" => ["/home", RouterContract::PutMethod, "phpinfo",];
        yield "typicalHomeFunctionNamePatchMethodString" => ["/home", RouterContract::PatchMethod, "phpinfo",];
        yield "typicalHomeFunctionNameHeadMethodString" => ["/home", RouterContract::HeadMethod, "phpinfo",];
        yield "typicalHomeFunctionNameDeleteMethodString" => ["/home", RouterContract::DeleteMethod, "phpinfo",];
        yield "typicalHomeFunctionNameOptionsMethodString" => ["/home", RouterContract::GetMethod, "phpinfo",];
        yield "typicalHomeFunctionNameConnectMethodString" => ["/home", RouterContract::ConnectMethod, "phpinfo",];
        yield "typicalHomeFunctionNameAnyMethodString" => ["/home", RouterContract::AnyMethod, "phpinfo",];
        yield "typicalHomeFunctionNameGetMethodArray" => ["/home", [RouterContract::GetMethod,], "phpinfo",];
        yield "typicalHomeFunctionNamePostMethodArray" => ["/home", [RouterContract::PostMethod,], "phpinfo",];
        yield "typicalHomeFunctionNamePutMethodArray" => ["/home", [RouterContract::PutMethod,], "phpinfo",];
        yield "typicalHomeFunctionNamePatchMethodArray" => ["/home", [RouterContract::PatchMethod,], "phpinfo",];
        yield "typicalHomeFunctionNameHeadMethodArray" => ["/home", [RouterContract::HeadMethod,], "phpinfo",];
        yield "typicalHomeFunctionNameDeleteMethodArray" => ["/home", [RouterContract::DeleteMethod,], "phpinfo",];
        yield "typicalHomeFunctionNameOptionsMethodArray" => ["/home", [RouterContract::GetMethod,], "phpinfo",];
        yield "typicalHomeFunctionNameConnectMethodArray" => ["/home", [RouterContract::ConnectMethod,], "phpinfo",];
        yield "typicalHomeFunctionNameAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], "phpinfo",];
        yield "typicalHomeStaticMethodStringGetMethodString" => ["/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringPostMethodString" => ["/home", RouterContract::PostMethod, "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringPutMethodString" => ["/home", RouterContract::PutMethod, "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringPatchMethodString" => ["/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringHeadMethodString" => ["/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringDeleteMethodString" => ["/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringOptionsMethodString" => ["/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringConnectMethodString" => ["/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringAnyMethodString" => ["/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringGetMethodArray" => ["/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringPostMethodArray" => ["/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringPutMethodArray" => ["/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringPatchMethodArray" => ["/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringHeadMethodArray" => ["/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringDeleteMethodArray" => ["/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringOptionsMethodArray" => ["/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringConnectMethodArray" => ["/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];

        yield "typicalMultiSegmentRouteStaticMethodGetMethodString" => ["/account/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodPostMethodString" => ["/account/user/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodPutMethodString" => ["/account/user/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodPatchMethodString" => ["/account/user/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodHeadMethodString" => ["/account/user/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodDeleteMethodString" => ["/account/user/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodOptionsMethodString" => ["/account/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodConnectMethodString" => ["/account/user/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodGetMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodPostMethodArray" => ["/account/user/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodPutMethodArray" => ["/account/user/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodPatchMethodArray" => ["/account/user/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodHeadMethodArray" => ["/account/user/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodDeleteMethodArray" => ["/account/user/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodOptionsMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodConnectMethodArray" => ["/account/user/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodGetMethodString" => ["/account/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodPostMethodString" => ["/account/user/home", RouterContract::PostMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodPutMethodString" => ["/account/user/home", RouterContract::PutMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodPatchMethodString" => ["/account/user/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodHeadMethodString" => ["/account/user/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodDeleteMethodString" => ["/account/user/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodOptionsMethodString" => ["/account/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodConnectMethodString" => ["/account/user/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodAnyMethodString" => ["/account/user/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodGetMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodPostMethodArray" => ["/account/user/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodPutMethodArray" => ["/account/user/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodPatchMethodArray" => ["/account/user/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodHeadMethodArray" => ["/account/user/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodDeleteMethodArray" => ["/account/user/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodOptionsMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodConnectMethodArray" => ["/account/user/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"],];

        yield "typicalMultiSegmentRouteClosureGetMethodString" => [
            "/account/user/home",
            RouterContract::GetMethod,
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosurePostMethodString" => [
            "/account/user/home",
            RouterContract::PostMethod,
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosurePutMethodString" => [
            "/account/user/home",
            RouterContract::PutMethod,
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosurePatchMethodString" => [
            "/account/user/home",
            RouterContract::PatchMethod,
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosureHeadMethodString" => [
            "/account/user/home",
            RouterContract::HeadMethod,
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosureDeleteMethodString" => [
            "/account/user/home",
            RouterContract::DeleteMethod,
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosureOptionsMethodString" => [
            "/account/user/home",
            RouterContract::GetMethod,
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosureConnectMethodString" => [
            "/account/user/home",
            RouterContract::ConnectMethod,
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosureAnyMethodString" => [
            "/account/user/home",
            RouterContract::AnyMethod,
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosureGetMethodArray" => [
            "/account/user/home",
            [RouterContract::GetMethod,],
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosurePostMethodArray" => [
            "/account/user/home",
            [RouterContract::PostMethod,],
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosurePutMethodArray" => [
            "/account/user/home",
            [RouterContract::PutMethod,],
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosurePatchMethodArray" => [
            "/account/user/home",
            [RouterContract::PatchMethod,],
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosureHeadMethodArray" => [
            "/account/user/home",
            [RouterContract::HeadMethod,],
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosureDeleteMethodArray" => [
            "/account/user/home",
            [RouterContract::DeleteMethod,],
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosureOptionsMethodArray" => [
            "/account/user/home",
            [RouterContract::GetMethod,],
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosureConnectMethodArray" => [
            "/account/user/home",
            [RouterContract::ConnectMethod,],
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteClosureAnyMethodArray" => [
            "/account/user/home",
            [RouterContract::AnyMethod,],
            function () {
            },
        ];

        yield "typicalMultiSegmentRouteFunctionNameGetMethodString" => [
            "/account/user/home",
            RouterContract::GetMethod,
            "phpinfo",
        ];

        yield "typicalMultiSegmentRouteFunctionNamePostMethodString" => ["/account/user/home", RouterContract::PostMethod, "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNamePutMethodString" => ["/account/user/home", RouterContract::PutMethod, "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNamePatchMethodString" => ["/account/user/home", RouterContract::PatchMethod, "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNameHeadMethodString" => ["/account/user/home", RouterContract::HeadMethod, "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNameDeleteMethodString" => ["/account/user/home", RouterContract::DeleteMethod, "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNameOptionsMethodString" => ["/account/user/home", RouterContract::GetMethod, "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNameConnectMethodString" => ["/account/user/home", RouterContract::ConnectMethod, "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNameAnyMethodString" => ["/account/user/home", RouterContract::AnyMethod, "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNameGetMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNamePostMethodArray" => ["/account/user/home", [RouterContract::PostMethod,], "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNamePutMethodArray" => ["/account/user/home", [RouterContract::PutMethod,], "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNamePatchMethodArray" => ["/account/user/home", [RouterContract::PatchMethod,], "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNameHeadMethodArray" => ["/account/user/home", [RouterContract::HeadMethod,], "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNameDeleteMethodArray" => ["/account/user/home", [RouterContract::DeleteMethod,], "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNameOptionsMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNameConnectMethodArray" => ["/account/user/home", [RouterContract::ConnectMethod,], "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNameAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], "phpinfo",];
        yield "typicalMultiSegmentRouteStaticMethodStringGetMethodString" => ["/account/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringPostMethodString" => ["/account/user/home", RouterContract::PostMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringPutMethodString" => ["/account/user/home", RouterContract::PutMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringPatchMethodString" => ["/account/user/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringHeadMethodString" => ["/account/user/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringDeleteMethodString" => ["/account/user/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringOptionsMethodString" => ["/account/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringConnectMethodString" => ["/account/user/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringAnyMethodString" => ["/account/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringGetMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringPostMethodArray" => ["/account/user/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringPutMethodArray" => ["/account/user/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringPatchMethodArray" => ["/account/user/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringHeadMethodArray" => ["/account/user/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringDeleteMethodArray" => ["/account/user/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringOptionsMethodArray" => ["/account/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringConnectMethodArray" => ["/account/user/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];

        // tests with route strings containing parameters
        yield "typicalParameterisedRouteStaticMethodGetMethodString" => ["/user/{id}/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodPostMethodString" => ["/user/{id}/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodPutMethodString" => ["/user/{id}/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodPatchMethodString" => ["/user/{id}/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodHeadMethodString" => ["/user/{id}/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodDeleteMethodString" => ["/user/{id}/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodOptionsMethodString" => ["/user/{id}/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodConnectMethodString" => ["/user/{id}/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodGetMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodPostMethodArray" => ["/user/{id}/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodPutMethodArray" => ["/user/{id}/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodPatchMethodArray" => ["/user/{id}/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodHeadMethodArray" => ["/user/{id}/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodDeleteMethodArray" => ["/user/{id}/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodOptionsMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodConnectMethodArray" => ["/user/{id}/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteMethodGetMethodString" => ["/user/{id}/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodPostMethodString" => ["/user/{id}/home", RouterContract::PostMethod, [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodPutMethodString" => ["/user/{id}/home", RouterContract::PutMethod, [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodPatchMethodString" => ["/user/{id}/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodHeadMethodString" => ["/user/{id}/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodDeleteMethodString" => ["/user/{id}/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodOptionsMethodString" => ["/user/{id}/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodConnectMethodString" => ["/user/{id}/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodAnyMethodString" => ["/user/{id}/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodGetMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodPostMethodArray" => ["/user/{id}/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodPutMethodArray" => ["/user/{id}/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodPatchMethodArray" => ["/user/{id}/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodHeadMethodArray" => ["/user/{id}/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodDeleteMethodArray" => ["/user/{id}/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodOptionsMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodConnectMethodArray" => ["/user/{id}/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"],];

        yield "typicalParameterisedRouteClosureGetMethodString" => [
            "/user/{id}/home",
            RouterContract::GetMethod,
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosurePostMethodString" => [
            "/user/{id}/home",
            RouterContract::PostMethod,
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosurePutMethodString" => [
            "/user/{id}/home",
            RouterContract::PutMethod,
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosurePatchMethodString" => [
            "/user/{id}/home",
            RouterContract::PatchMethod,
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosureHeadMethodString" => [
            "/user/{id}/home",
            RouterContract::HeadMethod,
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosureDeleteMethodString" => [
            "/user/{id}/home",
            RouterContract::DeleteMethod,
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosureOptionsMethodString" => [
            "/user/{id}/home",
            RouterContract::GetMethod,
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosureConnectMethodString" => [
            "/user/{id}/home",
            RouterContract::ConnectMethod,
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosureAnyMethodString" => [
            "/user/{id}/home",
            RouterContract::AnyMethod,
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosureGetMethodArray" => [
            "/user/{id}/home",
            [RouterContract::GetMethod,],
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosurePostMethodArray" => [
            "/user/{id}/home",
            [RouterContract::PostMethod,],
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosurePutMethodArray" => [
            "/user/{id}/home",
            [RouterContract::PutMethod,],
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosurePatchMethodArray" => [
            "/user/{id}/home",
            [RouterContract::PatchMethod,],
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosureHeadMethodArray" => [
            "/user/{id}/home",
            [RouterContract::HeadMethod,],
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosureDeleteMethodArray" => [
            "/user/{id}/home",
            [RouterContract::DeleteMethod,],
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosureOptionsMethodArray" => [
            "/user/{id}/home",
            [RouterContract::GetMethod,],
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosureConnectMethodArray" => [
            "/user/{id}/home",
            [RouterContract::ConnectMethod,],
            function () {
            },
        ];

        yield "typicalParameterisedRouteClosureAnyMethodArray" => [
            "/user/{id}/home",
            [RouterContract::AnyMethod,],
            function () {
            },
        ];

        yield "typicalParameterisedRouteFunctionNameGetMethodString" => [
            "/user/{id}/home",
            RouterContract::GetMethod,
            "phpinfo",
        ];
        yield "typicalParameterisedRouteFunctionNamePostMethodString" => ["/user/{id}/home", RouterContract::PostMethod, "phpinfo",];
        yield "typicalParameterisedRouteFunctionNamePutMethodString" => ["/user/{id}/home", RouterContract::PutMethod, "phpinfo",];
        yield "typicalParameterisedRouteFunctionNamePatchMethodString" => ["/user/{id}/home", RouterContract::PatchMethod, "phpinfo",];
        yield "typicalParameterisedRouteFunctionNameHeadMethodString" => ["/user/{id}/home", RouterContract::HeadMethod, "phpinfo",];
        yield "typicalParameterisedRouteFunctionNameDeleteMethodString" => ["/user/{id}/home", RouterContract::DeleteMethod, "phpinfo",];
        yield "typicalParameterisedRouteFunctionNameOptionsMethodString" => ["/user/{id}/home", RouterContract::GetMethod, "phpinfo",];
        yield "typicalParameterisedRouteFunctionNameConnectMethodString" => ["/user/{id}/home", RouterContract::ConnectMethod, "phpinfo",];
        yield "typicalParameterisedRouteFunctionNameAnyMethodString" => ["/user/{id}/home", RouterContract::AnyMethod, "phpinfo",];
        yield "typicalParameterisedRouteFunctionNameGetMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], "phpinfo",];
        yield "typicalParameterisedRouteFunctionNamePostMethodArray" => ["/user/{id}/home", [RouterContract::PostMethod,], "phpinfo",];
        yield "typicalParameterisedRouteFunctionNamePutMethodArray" => ["/user/{id}/home", [RouterContract::PutMethod,], "phpinfo",];
        yield "typicalParameterisedRouteFunctionNamePatchMethodArray" => ["/user/{id}/home", [RouterContract::PatchMethod,], "phpinfo",];
        yield "typicalParameterisedRouteFunctionNameHeadMethodArray" => ["/user/{id}/home", [RouterContract::HeadMethod,], "phpinfo",];
        yield "typicalParameterisedRouteFunctionNameDeleteMethodArray" => ["/user/{id}/home", [RouterContract::DeleteMethod,], "phpinfo",];
        yield "typicalParameterisedRouteFunctionNameOptionsMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], "phpinfo",];
        yield "typicalParameterisedRouteFunctionNameConnectMethodArray" => ["/user/{id}/home", [RouterContract::ConnectMethod,], "phpinfo",];
        yield "typicalParameterisedRouteFunctionNameAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], "phpinfo",];
        yield "typicalParameterisedRouteStaticMethodStringGetMethodString" => ["/user/{id}/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringPostMethodString" => ["/user/{id}/home", RouterContract::PostMethod, "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringPutMethodString" => ["/user/{id}/home", RouterContract::PutMethod, "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringPatchMethodString" => ["/user/{id}/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringHeadMethodString" => ["/user/{id}/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringDeleteMethodString" => ["/user/{id}/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringOptionsMethodString" => ["/user/{id}/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringConnectMethodString" => ["/user/{id}/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringAnyMethodString" => ["/user/{id}/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringGetMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringPostMethodArray" => ["/user/{id}/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringPutMethodArray" => ["/user/{id}/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringPatchMethodArray" => ["/user/{id}/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringHeadMethodArray" => ["/user/{id}/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringDeleteMethodArray" => ["/user/{id}/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringOptionsMethodArray" => ["/user/{id}/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringConnectMethodArray" => ["/user/{id}/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];

        yield "typicalMultiParameterRouteStaticMethodGetMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodPostMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodPutMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodPatchMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodHeadMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodDeleteMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodOptionsMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodConnectMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodGetMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodPostMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodPutMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodPatchMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodHeadMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodDeleteMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodOptionsMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodConnectMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteMethodGetMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodPostMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PostMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodPutMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PutMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodPatchMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodHeadMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodDeleteMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodOptionsMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodConnectMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodAnyMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodGetMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodPostMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodPutMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodPatchMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodHeadMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodDeleteMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodOptionsMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodConnectMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"],];

        yield "typicalMultiParameterRouteClosureGetMethodString" => [
            "/account/{account_id}/user/{user_id}/home",
            RouterContract::GetMethod,
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosurePostMethodString" => [
            "/account/{account_id}/user/{user_id}/home",
            RouterContract::PostMethod,
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosurePutMethodString" => [
            "/account/{account_id}/user/{user_id}/home",
            RouterContract::PutMethod,
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosurePatchMethodString" => [
            "/account/{account_id}/user/{user_id}/home",
            RouterContract::PatchMethod,
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosureHeadMethodString" => [
            "/account/{account_id}/user/{user_id}/home",
            RouterContract::HeadMethod,
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosureDeleteMethodString" => [
            "/account/{account_id}/user/{user_id}/home",
            RouterContract::DeleteMethod,
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosureOptionsMethodString" => [
            "/account/{account_id}/user/{user_id}/home",
            RouterContract::GetMethod,
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosureConnectMethodString" => [
            "/account/{account_id}/user/{user_id}/home",
            RouterContract::ConnectMethod,
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosureAnyMethodString" => [
            "/account/{account_id}/user/{user_id}/home",
            RouterContract::AnyMethod,
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosureGetMethodArray" => [
            "/account/{account_id}/user/{user_id}/home",
            [RouterContract::GetMethod,],
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosurePostMethodArray" => [
            "/account/{account_id}/user/{user_id}/home",
            [RouterContract::PostMethod,],
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosurePutMethodArray" => [
            "/account/{account_id}/user/{user_id}/home",
            [RouterContract::PutMethod,],
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosurePatchMethodArray" => [
            "/account/{account_id}/user/{user_id}/home",
            [RouterContract::PatchMethod,],
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosureHeadMethodArray" => [
            "/account/{account_id}/user/{user_id}/home",
            [RouterContract::HeadMethod,],
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosureDeleteMethodArray" => [
            "/account/{account_id}/user/{user_id}/home",
            [RouterContract::DeleteMethod,],
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosureOptionsMethodArray" => [
            "/account/{account_id}/user/{user_id}/home",
            [RouterContract::GetMethod,],
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosureConnectMethodArray" => [
            "/account/{account_id}/user/{user_id}/home",
            [RouterContract::ConnectMethod,],
            function () {
            },
        ];

        yield "typicalMultiParameterRouteClosureAnyMethodArray" => [
            "/account/{account_id}/user/{user_id}/home",
            [RouterContract::AnyMethod,],
            function () {
            },
        ];

        yield "typicalMultiParameterRouteFunctionNameGetMethodString" => [
            "/account/{account_id}/user/{user_id}/home",
            RouterContract::GetMethod,
            "phpinfo",
        ];

        yield "typicalMultiParameterRouteFunctionNamePostMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PostMethod, "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNamePutMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PutMethod, "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNamePatchMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PatchMethod, "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNameHeadMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::HeadMethod, "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNameDeleteMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::DeleteMethod, "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNameOptionsMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNameConnectMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::ConnectMethod, "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNameAnyMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::AnyMethod, "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNameGetMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNamePostMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PostMethod,], "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNamePutMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PutMethod,], "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNamePatchMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PatchMethod,], "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNameHeadMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::HeadMethod,], "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNameDeleteMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::DeleteMethod,], "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNameOptionsMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNameConnectMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::ConnectMethod,], "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNameAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], "phpinfo",];
        yield "typicalMultiParameterRouteStaticMethodStringGetMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringPostMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PostMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringPutMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PutMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringPatchMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringHeadMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringDeleteMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringOptionsMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::GetMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringConnectMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringAnyMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringGetMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringPostMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringPutMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringPatchMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringHeadMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringDeleteMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringOptionsMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringConnectMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];

        yield "invalidDuplicateParameterRouteStaticMethodGetMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodPostMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodPutMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodPatchMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodHeadMethodString" => ["/account/{id}/user/{id}/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodDeleteMethodString" => ["/account/{id}/user/{id}/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodOptionsMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodConnectMethodString" => ["/account/{id}/user/{id}/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodGetMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodPostMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodPutMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodPatchMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodHeadMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodDeleteMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodOptionsMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodConnectMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodGetMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodPostMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PostMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodPutMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PutMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodPatchMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodHeadMethodString" => ["/account/{id}/user/{id}/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodDeleteMethodString" => ["/account/{id}/user/{id}/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodOptionsMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodConnectMethodString" => ["/account/{id}/user/{id}/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodAnyMethodString" => ["/account/{id}/user/{id}/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodGetMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodPostMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodPutMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodPatchMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodHeadMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodDeleteMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodOptionsMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodConnectMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteMethodAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"], DuplicateRouteParameterNameException::class,];

        yield "invalidDuplicateParameterRouteClosureGetMethodString" => [
            "/account/{id}/user/{id}/home",
            RouterContract::GetMethod,
            function () {
            },
            DuplicateRouteParameterNameException::class,
         ];

        yield "invalidDuplicateParameterRouteClosurePostMethodString" => [
            "/account/{id}/user/{id}/home",
            RouterContract::PostMethod,
            function () {
            },
            DuplicateRouteParameterNameException::class,
         ];

        yield "invalidDuplicateParameterRouteClosurePutMethodString" => [
            "/account/{id}/user/{id}/home",
            RouterContract::PutMethod,
            function () {
            },
            DuplicateRouteParameterNameException::class,
         ];

        yield "invalidDuplicateParameterRouteClosurePatchMethodString" => [
            "/account/{id}/user/{id}/home",
            RouterContract::PatchMethod,
            function () {
            },
            DuplicateRouteParameterNameException::class,
         ];

        yield "invalidDuplicateParameterRouteClosureHeadMethodString" => [
            "/account/{id}/user/{id}/home",
            RouterContract::HeadMethod,
            function () {
            },
            DuplicateRouteParameterNameException::class,
         ];

        yield "invalidDuplicateParameterRouteClosureDeleteMethodString" => [
            "/account/{id}/user/{id}/home",
            RouterContract::DeleteMethod,
            function () {
            },
            DuplicateRouteParameterNameException::class,
         ];

        yield "invalidDuplicateParameterRouteClosureOptionsMethodString" => [
            "/account/{id}/user/{id}/home",
            RouterContract::GetMethod,
            function () {
            },
            DuplicateRouteParameterNameException::class,
         ];

        yield "invalidDuplicateParameterRouteClosureConnectMethodString" => [
            "/account/{id}/user/{id}/home",
            RouterContract::ConnectMethod,
            function () {
            },
            DuplicateRouteParameterNameException::class,
         ];

        yield "invalidDuplicateParameterRouteClosureAnyMethodString" => [
            "/account/{id}/user/{id}/home",
            RouterContract::AnyMethod,
            function () {
            },
            DuplicateRouteParameterNameException::class,
         ];

        yield "invalidDuplicateParameterRouteClosureGetMethodArray" => [
            "/account/{id}/user/{id}/home",
            [RouterContract::GetMethod,],
            function () {
            },
            DuplicateRouteParameterNameException::class,
        ];

        yield "invalidDuplicateParameterRouteClosurePostMethodArray" => [
            "/account/{id}/user/{id}/home",
            [RouterContract::PostMethod,],
            function () {
            },
            DuplicateRouteParameterNameException::class,
        ];

        yield "invalidDuplicateParameterRouteClosurePutMethodArray" => [
            "/account/{id}/user/{id}/home",
            [RouterContract::PutMethod,],
            function () {
            },
            DuplicateRouteParameterNameException::class,
        ];

        yield "invalidDuplicateParameterRouteClosurePatchMethodArray" => [
            "/account/{id}/user/{id}/home",
            [RouterContract::PatchMethod,],
            function () {
            },
            DuplicateRouteParameterNameException::class,
        ];

        yield "invalidDuplicateParameterRouteClosureHeadMethodArray" => [
            "/account/{id}/user/{id}/home",
            [RouterContract::HeadMethod,],
            function () {
            },
            DuplicateRouteParameterNameException::class,
        ];

        yield "invalidDuplicateParameterRouteClosureDeleteMethodArray" => [
            "/account/{id}/user/{id}/home",
            [RouterContract::DeleteMethod,],
            function () {
            },
            DuplicateRouteParameterNameException::class,
        ];

        yield "invalidDuplicateParameterRouteClosureOptionsMethodArray" => [
            "/account/{id}/user/{id}/home",
            [RouterContract::GetMethod,],
            function () {
            },
            DuplicateRouteParameterNameException::class,
        ];

        yield "invalidDuplicateParameterRouteClosureConnectMethodArray" => [
            "/account/{id}/user/{id}/home",
            [RouterContract::ConnectMethod,],
            function () {
            },
            DuplicateRouteParameterNameException::class,
        ];

        yield "invalidDuplicateParameterRouteClosureAnyMethodArray" => [
            "/account/{id}/user/{id}/home",
            [RouterContract::AnyMethod,],
            function () {
            },
            DuplicateRouteParameterNameException::class,
        ];

        yield "invalidDuplicateParameterRouteFunctionNameGetMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNamePostMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PostMethod, "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNamePutMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PutMethod, "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNamePatchMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PatchMethod, "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNameHeadMethodString" => ["/account/{id}/user/{id}/home", RouterContract::HeadMethod, "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNameDeleteMethodString" => ["/account/{id}/user/{id}/home", RouterContract::DeleteMethod, "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNameOptionsMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNameConnectMethodString" => ["/account/{id}/user/{id}/home", RouterContract::ConnectMethod, "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNameAnyMethodString" => ["/account/{id}/user/{id}/home", RouterContract::AnyMethod, "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNameGetMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNamePostMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PostMethod,], "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNamePutMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PutMethod,], "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNamePatchMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PatchMethod,], "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNameHeadMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::HeadMethod,], "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNameDeleteMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::DeleteMethod,], "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNameOptionsMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNameConnectMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::ConnectMethod,], "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteFunctionNameAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], "phpinfo", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringGetMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringPostMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PostMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringPutMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PutMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringPatchMethodString" => ["/account/{id}/user/{id}/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringHeadMethodString" => ["/account/{id}/user/{id}/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringDeleteMethodString" => ["/account/{id}/user/{id}/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringOptionsMethodString" => ["/account/{id}/user/{id}/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringConnectMethodString" => ["/account/{id}/user/{id}/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringAnyMethodString" => ["/account/{id}/user/{id}/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringGetMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringPostMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringPutMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringPatchMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringHeadMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringDeleteMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringOptionsMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringConnectMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];
        yield "invalidDuplicateParameterRouteStaticMethodStringAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler", DuplicateRouteParameterNameException::class,];

        yield "invalidBadParameterNameEmptyRouteStaticMethodGetMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodPostMethodString" => ["/account/{}/user/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodPutMethodString" => ["/account/{}/user/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodPatchMethodString" => ["/account/{}/user/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodHeadMethodString" => ["/account/{}/user/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodDeleteMethodString" => ["/account/{}/user/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodOptionsMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodConnectMethodString" => ["/account/{}/user/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodGetMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodPostMethodArray" => ["/account/{}/user/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodPutMethodArray" => ["/account/{}/user/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodPatchMethodArray" => ["/account/{}/user/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodHeadMethodArray" => ["/account/{}/user/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodDeleteMethodArray" => ["/account/{}/user/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodOptionsMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodConnectMethodArray" => ["/account/{}/user/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodGetMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodPostMethodString" => ["/account/{}/user/home", RouterContract::PostMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodPutMethodString" => ["/account/{}/user/home", RouterContract::PutMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodPatchMethodString" => ["/account/{}/user/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodHeadMethodString" => ["/account/{}/user/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodDeleteMethodString" => ["/account/{}/user/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodOptionsMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodConnectMethodString" => ["/account/{}/user/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodAnyMethodString" => ["/account/{}/user/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodGetMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodPostMethodArray" => ["/account/{}/user/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodPutMethodArray" => ["/account/{}/user/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodPatchMethodArray" => ["/account/{}/user/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodHeadMethodArray" => ["/account/{}/user/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodDeleteMethodArray" => ["/account/{}/user/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodOptionsMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodConnectMethodArray" => ["/account/{}/user/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteMethodAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];

        yield "invalidBadParameterNameEmptyRouteClosureGetMethodString" => ["/account/{}/user/home",
            RouterContract::GetMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosurePostMethodString" => ["/account/{}/user/home",
            RouterContract::PostMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosurePutMethodString" => ["/account/{}/user/home",
            RouterContract::PutMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosurePatchMethodString" => ["/account/{}/user/home",
            RouterContract::PatchMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosureHeadMethodString" => ["/account/{}/user/home",
            RouterContract::HeadMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosureDeleteMethodString" => ["/account/{}/user/home",
            RouterContract::DeleteMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosureOptionsMethodString" => ["/account/{}/user/home",
            RouterContract::GetMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosureConnectMethodString" => ["/account/{}/user/home",
            RouterContract::ConnectMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosureAnyMethodString" => ["/account/{}/user/home",
            RouterContract::AnyMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosureGetMethodArray" => ["/account/{}/user/home",
            [RouterContract::GetMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosurePostMethodArray" => ["/account/{}/user/home",
            [RouterContract::PostMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosurePutMethodArray" => ["/account/{}/user/home",
            [RouterContract::PutMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosurePatchMethodArray" => ["/account/{}/user/home",
            [RouterContract::PatchMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosureHeadMethodArray" => ["/account/{}/user/home",
            [RouterContract::HeadMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosureDeleteMethodArray" => ["/account/{}/user/home",
            [RouterContract::DeleteMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosureOptionsMethodArray" => ["/account/{}/user/home",
            [RouterContract::GetMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosureConnectMethodArray" => ["/account/{}/user/home",
            [RouterContract::ConnectMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteClosureAnyMethodArray" => ["/account/{}/user/home",
            [RouterContract::AnyMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameEmptyRouteFunctionNameGetMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNamePostMethodString" => ["/account/{}/user/home", RouterContract::PostMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNamePutMethodString" => ["/account/{}/user/home", RouterContract::PutMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNamePatchMethodString" => ["/account/{}/user/home", RouterContract::PatchMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNameHeadMethodString" => ["/account/{}/user/home", RouterContract::HeadMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNameDeleteMethodString" => ["/account/{}/user/home", RouterContract::DeleteMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNameOptionsMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNameConnectMethodString" => ["/account/{}/user/home", RouterContract::ConnectMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNameAnyMethodString" => ["/account/{}/user/home", RouterContract::AnyMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNameGetMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNamePostMethodArray" => ["/account/{}/user/home", [RouterContract::PostMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNamePutMethodArray" => ["/account/{}/user/home", [RouterContract::PutMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNamePatchMethodArray" => ["/account/{}/user/home", [RouterContract::PatchMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNameHeadMethodArray" => ["/account/{}/user/home", [RouterContract::HeadMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNameDeleteMethodArray" => ["/account/{}/user/home", [RouterContract::DeleteMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNameOptionsMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNameConnectMethodArray" => ["/account/{}/user/home", [RouterContract::ConnectMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteFunctionNameAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringGetMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringPostMethodString" => ["/account/{}/user/home", RouterContract::PostMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringPutMethodString" => ["/account/{}/user/home", RouterContract::PutMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringPatchMethodString" => ["/account/{}/user/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringHeadMethodString" => ["/account/{}/user/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringDeleteMethodString" => ["/account/{}/user/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringOptionsMethodString" => ["/account/{}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringConnectMethodString" => ["/account/{}/user/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringAnyMethodString" => ["/account/{}/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringGetMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringPostMethodArray" => ["/account/{}/user/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringPutMethodArray" => ["/account/{}/user/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringPatchMethodArray" => ["/account/{}/user/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringHeadMethodArray" => ["/account/{}/user/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringDeleteMethodArray" => ["/account/{}/user/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringOptionsMethodArray" => ["/account/{}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringConnectMethodArray" => ["/account/{}/user/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];

        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodGetMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodPostMethodString" => ["/account/{account-id}/user/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodPutMethodString" => ["/account/{account-id}/user/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodPatchMethodString" => ["/account/{account-id}/user/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodHeadMethodString" => ["/account/{account-id}/user/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodDeleteMethodString" => ["/account/{account-id}/user/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodOptionsMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodConnectMethodString" => ["/account/{account-id}/user/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodGetMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodPostMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodPutMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodPatchMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodHeadMethodArray" => ["/account/{account-id}/user/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodDeleteMethodArray" => ["/account/{account-id}/user/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodOptionsMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodConnectMethodArray" => ["/account/{account-id}/user/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodGetMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodPostMethodString" => ["/account/{account-id}/user/home", RouterContract::PostMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodPutMethodString" => ["/account/{account-id}/user/home", RouterContract::PutMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodPatchMethodString" => ["/account/{account-id}/user/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodHeadMethodString" => ["/account/{account-id}/user/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodDeleteMethodString" => ["/account/{account-id}/user/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodOptionsMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodConnectMethodString" => ["/account/{account-id}/user/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodAnyMethodString" => ["/account/{account-id}/user/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodGetMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodPostMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodPutMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodPatchMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodHeadMethodArray" => ["/account/{account-id}/user/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodDeleteMethodArray" => ["/account/{account-id}/user/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodOptionsMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodConnectMethodArray" => ["/account/{account-id}/user/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];

        yield "invalidBadParameterNameInvalidCharacterRouteClosureGetMethodString" => [
            "/account/{account-id}/user/home",
            RouterContract::GetMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosurePostMethodString" => [
            "/account/{account-id}/user/home",
            RouterContract::PostMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosurePutMethodString" => [
            "/account/{account-id}/user/home",
            RouterContract::PutMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosurePatchMethodString" => [
            "/account/{account-id}/user/home",
            RouterContract::PatchMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosureHeadMethodString" => [
            "/account/{account-id}/user/home",
            RouterContract::HeadMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosureDeleteMethodString" => [
            "/account/{account-id}/user/home",
            RouterContract::DeleteMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosureOptionsMethodString" => [
            "/account/{account-id}/user/home",
            RouterContract::GetMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosureConnectMethodString" => [
            "/account/{account-id}/user/home",
            RouterContract::ConnectMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosureAnyMethodString" => [
            "/account/{account-id}/user/home",
            RouterContract::AnyMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosureGetMethodArray" => [
            "/account/{account-id}/user/home",
            [RouterContract::GetMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosurePostMethodArray" => [
            "/account/{account-id}/user/home",
            [RouterContract::PostMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosurePutMethodArray" => [
            "/account/{account-id}/user/home",
            [RouterContract::PutMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosurePatchMethodArray" => [
            "/account/{account-id}/user/home",
            [RouterContract::PatchMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosureHeadMethodArray" => [
            "/account/{account-id}/user/home",
            [RouterContract::HeadMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosureDeleteMethodArray" => [
            "/account/{account-id}/user/home",
            [RouterContract::DeleteMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosureOptionsMethodArray" => [
            "/account/{account-id}/user/home",
            [RouterContract::GetMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosureConnectMethodArray" => [
            "/account/{account-id}/user/home",
            [RouterContract::ConnectMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteClosureAnyMethodArray" => [
            "/account/{account-id}/user/home",
            [RouterContract::AnyMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameGetMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNamePostMethodString" => ["/account/{account-id}/user/home", RouterContract::PostMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNamePutMethodString" => ["/account/{account-id}/user/home", RouterContract::PutMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNamePatchMethodString" => ["/account/{account-id}/user/home", RouterContract::PatchMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameHeadMethodString" => ["/account/{account-id}/user/home", RouterContract::HeadMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameDeleteMethodString" => ["/account/{account-id}/user/home", RouterContract::DeleteMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameOptionsMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameConnectMethodString" => ["/account/{account-id}/user/home", RouterContract::ConnectMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameAnyMethodString" => ["/account/{account-id}/user/home", RouterContract::AnyMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameGetMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNamePostMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PostMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNamePutMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PutMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNamePatchMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PatchMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameHeadMethodArray" => ["/account/{account-id}/user/home", [RouterContract::HeadMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameDeleteMethodArray" => ["/account/{account-id}/user/home", [RouterContract::DeleteMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameOptionsMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameConnectMethodArray" => ["/account/{account-id}/user/home", [RouterContract::ConnectMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringGetMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringPostMethodString" => ["/account/{account-id}/user/home", RouterContract::PostMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringPutMethodString" => ["/account/{account-id}/user/home", RouterContract::PutMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringPatchMethodString" => ["/account/{account-id}/user/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringHeadMethodString" => ["/account/{account-id}/user/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringDeleteMethodString" => ["/account/{account-id}/user/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringOptionsMethodString" => ["/account/{account-id}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringConnectMethodString" => ["/account/{account-id}/user/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringAnyMethodString" => ["/account/{account-id}/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringGetMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringPostMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringPutMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringPatchMethodArray" => ["/account/{account-id}/user/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringHeadMethodArray" => ["/account/{account-id}/user/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringDeleteMethodArray" => ["/account/{account-id}/user/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringOptionsMethodArray" => ["/account/{account-id}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringConnectMethodArray" => ["/account/{account-id}/user/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodGetMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodPostMethodString" => ["/account/{-account_id}/user/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodPutMethodString" => ["/account/{-account_id}/user/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodPatchMethodString" => ["/account/{-account_id}/user/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodHeadMethodString" => ["/account/{-account_id}/user/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodDeleteMethodString" => ["/account/{-account_id}/user/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodOptionsMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodConnectMethodString" => ["/account/{-account_id}/user/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodGetMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodPostMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodPutMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodPatchMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodHeadMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodDeleteMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodOptionsMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodConnectMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodGetMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodPostMethodString" => ["/account/{-account_id}/user/home", RouterContract::PostMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodPutMethodString" => ["/account/{-account_id}/user/home", RouterContract::PutMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodPatchMethodString" => ["/account/{-account_id}/user/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodHeadMethodString" => ["/account/{-account_id}/user/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodDeleteMethodString" => ["/account/{-account_id}/user/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodOptionsMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodConnectMethodString" => ["/account/{-account_id}/user/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodAnyMethodString" => ["/account/{-account_id}/user/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodGetMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodPostMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodPutMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodPatchMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodHeadMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodDeleteMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodOptionsMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodConnectMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureGetMethodString" => [
            "/account/{-account_id}/user/home",
            RouterContract::GetMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosurePostMethodString" => [
            "/account/{-account_id}/user/home",
            RouterContract::PostMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosurePutMethodString" => [
            "/account/{-account_id}/user/home", RouterContract::PutMethod, function () {
            }, InvalidRouteParameterNameException::class,];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosurePatchMethodString" => [
            "/accoun/{-account_id}/user/home",
            RouterContract::PatchMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureHeadMethodString" => [
            "/accoun/{-account_id}/user/home",
            RouterContract::HeadMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureDeleteMethodString" => [
            "/accoun/{-account_id}/user/home",
            RouterContract::DeleteMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureOptionsMethodString" => [
            "/accoun/{-account_id}/user/home",
            RouterContract::GetMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureConnectMethodString" => [
            "/accoun/{-account_id}/user/home",
            RouterContract::ConnectMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureAnyMethodString" => [
            "/accoun/{-account_id}/user/home",
            RouterContract::AnyMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureGetMethodArray" => [
            "/accoun/{-account_id}/user/home", [RouterContract::GetMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosurePostMethodArray" => [
            "/accoun/{-account_id}/user/home", [RouterContract::PostMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosurePutMethodArray" => [
            "/accoun/{-account_id}/user/home", [RouterContract::PutMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosurePatchMethodArray" => [
            "/accoun/{-account_id}/user/home", [RouterContract::PatchMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureHeadMethodArray" => [
            "/accoun/{-account_id}/user/home", [RouterContract::HeadMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureDeleteMethodArray" => [
            "/accoun/{-account_id}/user/home", [RouterContract::DeleteMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureOptionsMethodArray" => [
            "/accoun/{-account_id}/user/home", [RouterContract::GetMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureConnectMethodArray" => [
            "/accoun/{-account_id}/user/home", [RouterContract::ConnectMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureAnyMethodArray" => [
            "/accoun/{-account_id}/user/home", [RouterContract::AnyMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameGetMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNamePostMethodString" => ["/account/{-account_id}/user/home", RouterContract::PostMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNamePutMethodString" => ["/account/{-account_id}/user/home", RouterContract::PutMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNamePatchMethodString" => ["/account/{-account_id}/user/home", RouterContract::PatchMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameHeadMethodString" => ["/account/{-account_id}/user/home", RouterContract::HeadMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameDeleteMethodString" => ["/account/{-account_id}/user/home", RouterContract::DeleteMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameOptionsMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameConnectMethodString" => ["/account/{-account_id}/user/home", RouterContract::ConnectMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameAnyMethodString" => ["/account/{-account_id}/user/home", RouterContract::AnyMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameGetMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNamePostMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PostMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNamePutMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PutMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNamePatchMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PatchMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameHeadMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::HeadMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameDeleteMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::DeleteMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameOptionsMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameConnectMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::ConnectMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringGetMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringPostMethodString" => ["/account/{-account_id}/user/home", RouterContract::PostMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringPutMethodString" => ["/account/{-account_id}/user/home", RouterContract::PutMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringPatchMethodString" => ["/account/{-account_id}/user/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringHeadMethodString" => ["/account/{-account_id}/user/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringDeleteMethodString" => ["/account/{-account_id}/user/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringOptionsMethodString" => ["/account/{-account_id}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringConnectMethodString" => ["/account/{-account_id}/user/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringAnyMethodString" => ["/account/{-account_id}/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringGetMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringPostMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringPutMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringPatchMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringHeadMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringDeleteMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringOptionsMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringConnectMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];

        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodGetMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodPostMethodString" => ["/account/{1account_id}/user/home", RouterContract::PostMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodPutMethodString" => ["/account/{1account_id}/user/home", RouterContract::PutMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodPatchMethodString" => ["/account/{1account_id}/user/home", RouterContract::PatchMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodHeadMethodString" => ["/account/{1account_id}/user/home", RouterContract::HeadMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodDeleteMethodString" => ["/account/{1account_id}/user/home", RouterContract::DeleteMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodOptionsMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodConnectMethodString" => ["/account/{1account_id}/user/home", RouterContract::ConnectMethod, [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodGetMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodPostMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PostMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodPutMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PutMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodPatchMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PatchMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodHeadMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::HeadMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodDeleteMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::DeleteMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodOptionsMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodConnectMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::ConnectMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodGetMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodPostMethodString" => ["/account/{1account_id}/user/home", RouterContract::PostMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodPutMethodString" => ["/account/{1account_id}/user/home", RouterContract::PutMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodPatchMethodString" => ["/account/{1account_id}/user/home", RouterContract::PatchMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodHeadMethodString" => ["/account/{1account_id}/user/home", RouterContract::HeadMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodDeleteMethodString" => ["/account/{1account_id}/user/home", RouterContract::DeleteMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodOptionsMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodConnectMethodString" => ["/account/{1account_id}/user/home", RouterContract::ConnectMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodAnyMethodString" => ["/account/{1account_id}/user/home", RouterContract::AnyMethod, [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodGetMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodPostMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PostMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodPutMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PutMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodPatchMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PatchMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodHeadMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::HeadMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodDeleteMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::DeleteMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodOptionsMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodConnectMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::ConnectMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], [$this, "nullRouteHandler"], InvalidRouteParameterNameException::class,];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureGetMethodString" => [
            "/account/{1account_id}/user/home",
            RouterContract::GetMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosurePostMethodString" => [
            "/account/{1account_id}/user/home",
            RouterContract::PostMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosurePutMethodString" => [
            "/account/{1account_id}/user/home",
            RouterContract::PutMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosurePatchMethodString" => [
            "/account/{1account_id}/user/home",
            RouterContract::PatchMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureHeadMethodString" => [
            "/account/{1account_id}/user/home",
            RouterContract::HeadMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureDeleteMethodString" => [
            "/account/{1account_id}/user/home",
            RouterContract::DeleteMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureOptionsMethodString" => [
            "/account/{1account_id}/user/home",
            RouterContract::GetMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureConnectMethodString" => [
            "/account/{1account_id}/user/home",
            RouterContract::ConnectMethod, function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureAnyMethodString" => [
            "/account/{1account_id}/user/home",
            RouterContract::AnyMethod,
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureGetMethodArray" => [
            "/account/{1account_id}/user/home",
            [RouterContract::GetMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosurePostMethodArray" => [
            "/account/{1account_id}/user/home",
            [RouterContract::PostMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosurePutMethodArray" => [
            "/account/{1account_id}/user/home",
            [RouterContract::PutMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosurePatchMethodArray" => [
            "/account/{1account_id}/user/home",
            [RouterContract::PatchMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureHeadMethodArray" => [
            "/account/{1account_id}/user/home",
            [RouterContract::HeadMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureDeleteMethodArray" => [
            "/account/{1account_id}/user/home",
            [RouterContract::DeleteMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureOptionsMethodArray" => [
            "/account/{1account_id}/user/home",
            [RouterContract::GetMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];


        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureConnectMethodArray" => [
            "/account/{1account_id}/user/home",
            [RouterContract::ConnectMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureAnyMethodArray" => [
            "/account/{1account_id}/user/home",
            [RouterContract::AnyMethod,],
            function () {
            },
            InvalidRouteParameterNameException::class,
        ];

        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameGetMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNamePostMethodString" => ["/account/{1account_id}/user/home", RouterContract::PostMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNamePutMethodString" => ["/account/{1account_id}/user/home", RouterContract::PutMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNamePatchMethodString" => ["/account/{1account_id}/user/home", RouterContract::PatchMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameHeadMethodString" => ["/account/{1account_id}/user/home", RouterContract::HeadMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameDeleteMethodString" => ["/account/{1account_id}/user/home", RouterContract::DeleteMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameOptionsMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameConnectMethodString" => ["/account/{1account_id}/user/home", RouterContract::ConnectMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameAnyMethodString" => ["/account/{1account_id}/user/home", RouterContract::AnyMethod, "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameGetMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNamePostMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PostMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNamePutMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PutMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNamePatchMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PatchMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameHeadMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::HeadMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameDeleteMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::DeleteMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameOptionsMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameConnectMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::ConnectMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], "phpinfo", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringGetMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringPostMethodString" => ["/account/{1account_id}/user/home", RouterContract::PostMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringPutMethodString" => ["/account/{1account_id}/user/home", RouterContract::PutMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringPatchMethodString" => ["/account/{1account_id}/user/home", RouterContract::PatchMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringHeadMethodString" => ["/account/{1account_id}/user/home", RouterContract::HeadMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringDeleteMethodString" => ["/account/{1account_id}/user/home", RouterContract::DeleteMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringOptionsMethodString" => ["/account/{1account_id}/user/home", RouterContract::GetMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringConnectMethodString" => ["/account/{1account_id}/user/home", RouterContract::ConnectMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringAnyMethodString" => ["/account/{1account_id}/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringGetMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringPostMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PostMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringPutMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PutMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringPatchMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::PatchMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringHeadMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::HeadMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringDeleteMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::DeleteMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringOptionsMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::GetMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringConnectMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::ConnectMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler", InvalidRouteParameterNameException::class,];

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
     * @param string $route The route to register.
     * @param string|string[] $methods The HTTML method(s) to register.
     * @param mixed $handler The handler.
     * @param string|null $exceptionClass The expected exception class, if any.
     */
    public function testRegister(string $route, string|array $methods, callable|array|string $handler, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            self::expectException($exceptionClass);
        }

        $router = new Router();
        $router->register($route, $methods, $handler);

        if (is_string($methods)) {
            $methods = [$methods,];
        }

        $router = new XRay($router);

        foreach ($methods as $method) {
            if (RouterContract::AnyMethod === $method) {
                foreach (self::allHttpMethods() as $anyMethod) {
                    self::assertSame($route, $router->matchedRoute(self::makeRequest($route, $anyMethod)));
                }
            } else {
                self::assertSame($route, $router->matchedRoute(self::makeRequest($route, $method)));
            }
        }
    }

    /**
     * Data provider for testRoute().
     *
     * @return array[] The test data.
     */
    public function dataForTestRoute1(): array
    {
        return [
            "typicalGetWithNoParameters" => [RouterContract::GetMethod, "/home", RouterContract::GetMethod, "/home", function (Request $request): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/home", $request->pathInfo());
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalGetWithLongerPathAndNoParameters" => [RouterContract::GetMethod, "/admin/users/home", RouterContract::GetMethod, "/admin/users/home", function (Request $request): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/admin/users/home", $request->pathInfo());
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyGetWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::GetMethod, "/home", function (Request $request): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/home", $request->pathInfo());
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPostWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::PostMethod, "/home", function (Request $request): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/home", $request->pathInfo());
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPutWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::PutMethod, "/home", function (Request $request): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/home", $request->pathInfo());
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyDeleteWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::DeleteMethod, "/home", function (Request $request): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/home", $request->pathInfo());
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyHeadWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::HeadMethod, "/home", function (Request $request): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/home", $request->pathInfo());
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyOptionsWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::OptionsMethod, "/home", function (Request $request): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/home", $request->pathInfo());
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyConnectWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::ConnectMethod, "/home", function (Request $request): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/home", $request->pathInfo());
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPatchWithNoParameters" => [RouterContract::AnyMethod, "/home", RouterContract::PatchMethod, "/home", function (Request $request): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/home", $request->pathInfo());
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalGetWithParameterInt" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function (Request $request, int $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalGetWithParameterString" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function (Request $request, string $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame("123", $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalGetWithParameterFloat" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function (Request $request, float $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123.0, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalGetWithParameterBoolTrueInt" => [RouterContract::GetMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/1", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/1", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalGetWithParameterBoolTrueString" => [RouterContract::GetMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/true", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/true", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalGetWithParameterBoolFalseInt" => [RouterContract::GetMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/0", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/0", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalGetWithParameterBoolFalseString" => [RouterContract::GetMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/false", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/false", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyGetWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function (Request $request, int $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyGetWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function (Request $request, string $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame("123", $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyGetWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/123", function (Request $request, float $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123.0, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyGetWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/1", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/1", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyGetWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/true", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/true", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyGetWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/0", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/0", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyGetWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::GetMethod, "/edit/false", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/false", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPostWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PostMethod, "/edit/123", function (Request $request, int $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPostWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PostMethod, "/edit/123", function (Request $request, string $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame("123", $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPostWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PostMethod, "/edit/123", function (Request $request, float $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123.0, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPostWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PostMethod, "/edit/1", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/1", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPostWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PostMethod, "/edit/true", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/true", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPostWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PostMethod, "/edit/0", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/0", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPostWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PostMethod, "/edit/false", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/false", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPutWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PutMethod, "/edit/123", function (Request $request, int $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPutWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PutMethod, "/edit/123", function (Request $request, string $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame("123", $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPutWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PutMethod, "/edit/123", function (Request $request, float $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123.0, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPutWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PutMethod, "/edit/1", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/1", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPutWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PutMethod, "/edit/true", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/true", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPutWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PutMethod, "/edit/0", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/0", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPutWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PutMethod, "/edit/false", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/false", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyHeadWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::HeadMethod, "/edit/123", function (Request $request, int $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyHeadWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::HeadMethod, "/edit/123", function (Request $request, string $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame("123", $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyHeadWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::HeadMethod, "/edit/123", function (Request $request, float $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123.0, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyHeadWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::HeadMethod, "/edit/1", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/1", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyHeadWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::HeadMethod, "/edit/true", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/true", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyHeadWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::HeadMethod, "/edit/0", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/0", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyHeadWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::HeadMethod, "/edit/false", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/false", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyConnectWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::ConnectMethod, "/edit/123", function (Request $request, int $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyConnectWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::ConnectMethod, "/edit/123", function (Request $request, string $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame("123", $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyConnectWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::ConnectMethod, "/edit/123", function (Request $request, float $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123.0, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyConnectWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::ConnectMethod, "/edit/1", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/1", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyConnectWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::ConnectMethod, "/edit/true", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/true", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyConnectWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::ConnectMethod, "/edit/0", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/0", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyConnectWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::ConnectMethod, "/edit/false", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/false", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyDeleteWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::DeleteMethod, "/edit/123", function (Request $request, int $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyDeleteWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::DeleteMethod, "/edit/123", function (Request $request, string $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame("123", $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyDeleteWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::DeleteMethod, "/edit/123", function (Request $request, float $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123.0, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyDeleteWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::DeleteMethod, "/edit/1", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/1", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyDeleteWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::DeleteMethod, "/edit/true", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/true", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyDeleteWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::DeleteMethod, "/edit/0", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/0", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyDeleteWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::DeleteMethod, "/edit/false", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/false", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPatchWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PatchMethod, "/edit/123", function (Request $request, int $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPatchWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PatchMethod, "/edit/123", function (Request $request, string $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame("123", $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPatchWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::PatchMethod, "/edit/123", function (Request $request, float $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123.0, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPatchWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PatchMethod, "/edit/1", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/1", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPatchWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PatchMethod, "/edit/true", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/true", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPatchWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PatchMethod, "/edit/0", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/0", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyPatchWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::PatchMethod, "/edit/false", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/false", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyOptionsWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::OptionsMethod, "/edit/123", function (Request $request, int $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyOptionsWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::OptionsMethod, "/edit/123", function (Request $request, string $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame("123", $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyOptionsWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", RouterContract::OptionsMethod, "/edit/123", function (Request $request, float $id): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/123", $request->pathInfo());
                self::assertSame(123.0, $id);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyOptionsWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::OptionsMethod, "/edit/1", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/1", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyOptionsWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::OptionsMethod, "/edit/true", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/true", $request->pathInfo());
                self::assertSame(true, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyOptionsWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::OptionsMethod, "/edit/0", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/0", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalAnyOptionsWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", RouterContract::OptionsMethod, "/edit/false", function (Request $request, bool $confirmed): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/edit/false", $request->pathInfo());
                self::assertSame(false, $confirmed);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalGetWithParametersDifferentOrderManyTypes" => [RouterContract::GetMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::GetMethod, "/object/article/9563/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(9563, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalGetWithAllParametersDifferentOrderManyTypes" => [RouterContract::GetMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::GetMethod, "/article/123456789/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/article/123456789/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(123456789, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalPostWithParametersDifferentOrderManyTypes" => [RouterContract::PostMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::PostMethod, "/object/article/9563/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(9563, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalPostWithAllParametersDifferentOrderManyTypes" => [RouterContract::PostMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::PostMethod, "/article/123456789/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/article/123456789/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(123456789, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalPutWithParametersDifferentOrderManyTypes" => [RouterContract::PutMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::PutMethod, "/object/article/9563/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(9563, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalPutWithAllParametersDifferentOrderManyTypes" => [RouterContract::PutMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::PutMethod, "/article/123456789/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/article/123456789/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(123456789, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalHeadWithParametersDifferentOrderManyTypes" => [RouterContract::HeadMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::HeadMethod, "/object/article/9563/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(9563, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalHeadWithAllParametersDifferentOrderManyTypes" => [RouterContract::HeadMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::HeadMethod, "/article/123456789/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/article/123456789/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(123456789, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalOptionsWithParametersDifferentOrderManyTypes" => [RouterContract::OptionsMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::OptionsMethod, "/object/article/9563/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(9563, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalOptionsWithAllParametersDifferentOrderManyTypes" => [RouterContract::OptionsMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::OptionsMethod, "/article/123456789/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/article/123456789/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(123456789, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalDeleteWithParametersDifferentOrderManyTypes" => [RouterContract::DeleteMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::DeleteMethod, "/object/article/9563/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(9563, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalDeleteWithAllParametersDifferentOrderManyTypes" => [RouterContract::DeleteMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::DeleteMethod, "/article/123456789/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/article/123456789/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(123456789, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalPatchWithParametersDifferentOrderManyTypes" => [RouterContract::PatchMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::PatchMethod, "/object/article/9563/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(9563, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalPatchWithAllParametersDifferentOrderManyTypes" => [RouterContract::PatchMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::PatchMethod, "/article/123456789/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/article/123456789/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(123456789, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalConnectWithParametersDifferentOrderManyTypes" => [RouterContract::ConnectMethod, "/object/{type}/{id}/{action}/{property}/{value}", RouterContract::ConnectMethod, "/object/article/9563/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/object/article/9563/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(9563, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalConnectWithAllParametersDifferentOrderManyTypes" => [RouterContract::ConnectMethod, "/{type}/{id}/{action}/{property}/{value}", RouterContract::ConnectMethod, "/article/123456789/set/status/draft", function (Request $request, int $id, string $type, string $action, string $property, string $value): Response {
                self::assertInstanceOf(Request::class, $request);
                self::assertSame("/article/123456789/set/status/draft", $request->pathInfo());
                self::assertSame("article", $type);
                self::assertSame(123456789, $id);
                self::assertSame("set", $action);
                self::assertSame("status", $property);
                self::assertSame("draft", $value);
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }],
            "typicalUnroutableIncorrectMethodOneRegisteredMethod" => [RouterContract::GetMethod, "/", RouterContract::PostMethod, "/", function (Request $request, bool $confirmed): Response {
                $this->fail("Handler should not be called: Request method '{$request->method()}' should not match registered method '" . RouterContract::GetMethod . "'.");
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }, UnroutableRequestException::class,],
            "typicalUnroutableIncorrectMethodManyRegisteredMethods" => [[RouterContract::GetMethod, RouterContract::PostMethod,], "/", RouterContract::PutMethod, "/", function (Request $request, bool $confirmed): Response {
                $this->fail("Handler should not be called: Request method '{$request->method()}' should not match registered methods '" . implode("', '", [RouterContract::GetMethod, RouterContract::PostMethod,]) . "'.");
                return new class extends \Bead\Responses\AbstractResponse {
                    public function content(): string
                    {
                        return "";
                    }
                };
            }, UnroutableRequestException::class,],
            "typicalUnroutableNoMatchedRoute" => [RouterContract::GetMethod, "/", RouterContract::PostMethod, "/home", function (Request $request, bool $confirmed): Response {
                $this->fail("Handler should not be called: Request path '{$request->pathInfo()}' should not match registered route '/'.");
                return new class extends \Bead\Responses\AbstractResponse {
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
     * @dataProvider dataForTestRoute1
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
    public function testRoute1($routeMethods, string $route, string $requestMethod, string $requestPath, ?Closure $handler, ?string $exceptionClass = null): void
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

    /** Ensure dependencies can be injected into route parameters from the service container. */
    public function testRoute2(): void
    {
        $log = Mockery::mock(Logger::class);
        $app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $app);

        $app->shouldReceive("has")
            ->once()
            ->with(Logger::class)
            ->andReturn(true);

        $app->shouldReceive("get")
            ->once()
            ->with(Logger::class)
            ->andReturn($log);


        $expectedResponse = Mockery::mock(Response::class);

        $handler = function (Logger $injectedLog) use ($log, $expectedResponse): Response {
            RouterTest::assertSame($log, $injectedLog);
            return $expectedResponse;
        };

        $router = new Router();
        $router->registerGet("/", $handler);
        $actualResponse = $router->route(self::makeRequest("/"));
        self::assertSame($expectedResponse, $actualResponse);
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
        $accumulateRoutes = fn (array $routes, int $accumulation): int => $accumulation + count($routes);

        if ($shouldConflict) {
            $this->expectException(ConflictingRouteException::class);
        }

        $router = new Router();
        /** @noinspection PhpUnhandledExceptionInspection Guaranteed not to throw with these arguments. */
        $routeCollection = new ReflectionProperty($router, "m_routes");
        $routeCollection->setAccessible(true);
        /** @noinspection PhpUnhandledExceptionInspection Should never throw with test data. */
        $router->register($route1, $route1Methods, function () {
        });

        // fetch the route count so that we can assert that the registration of the second route adds to it if it
        // doesn't throw
        $routeCount = accumulate($routeCollection->getValue($router), $accumulateRoutes);
        /** @noinspection PhpUnhandledExceptionInspection Should only throw an expected test exception. */
        $router->register($route2, $route2Methods, function () {
        });
        self::assertGreaterThan($routeCount, accumulate($routeCollection->getValue($router), $accumulateRoutes), "The registration of the second route succeeded but didn't add to the routes colleciton in the router.");
    }
}
