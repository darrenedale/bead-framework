<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 */

declare(strict_types=1);

namespace BeadTests\Web;

use Bead\Contracts\Logger;
use Bead\Contracts\Web\Request as RequestContract;
use Bead\Contracts\Web\Response as ResponseContract;
use Bead\Contracts\Web\Router as RouterContract;
use Bead\Core\Application;
use Bead\Exceptions\ConflictingRouteException;
use Bead\Exceptions\DuplicateRouteParameterNameException;
use Bead\Exceptions\InvalidRouteParameterNameException;
use Bead\Exceptions\UnroutableRequestException;
use Bead\Web\HttpMethod;
use Bead\Web\Request;
use Bead\Web\Responses\AbstractResponse;
use Bead\Web\Router;
use BeadTests\Framework\TestCase;
use Closure;
use Equit\XRay\XRay;
use InvalidArgumentException;
use Mockery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use function Bead\Helpers\Iterable\accumulate;
use function count;
use function implode;
use function is_string;

#[CoversClass(Router::class)]
class RouterTest extends TestCase
{
    /** Route handler that does nothing. */
    public static function nullStaticRouteHandler(): void
    {
    }

    /**
     * Make a Request test double with a given path and HTTP method.
     *
     * @param string $path The path for the request (used in route matching).
     * @param HttpMethod $method The HTTP method.
     *
     * @return RequestContract
     */
    protected static function makeRequest(string $path, HttpMethod $method = HttpMethod::Get): RequestContract
    {
        return new class ($path, $method) extends Request
        {
            private string $path;

            private HttpMethod $method;

            public function __construct(string $path, HttpMethod $method)
            {
                $this->path = $path;
                $this->method = $method;
            }

            public function path(): string
            {
                return $this->path;
            }

            public function method(): HttpMethod
            {
                return $this->method;
            }
        };
    }

    /** Provides valid routes and handlers for tests of single-HTTP-method convenience registration methods. */
    public static function providerRoutesAndHandlers(): iterable
    {
        $routeHandler = new class
        {
            public function nullRouteHandler(): void
            {
            }
        };

        yield "typicalRootStaticMethod" => ["/", [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootMethod" => ["/", [$routeHandler, "nullRouteHandler"],];
        yield "typicalRootFunctionName" => ["/", "phpinfo",];
        yield "typicalRootStaticMethodString" => ["/", "self::nullStaticRouteHandler",];
        yield "extremeRootWithNonExistentStaticMethod" => ["/", ["foo", "bar"],];
        yield "extremeRootWithNonExistentStaticMethodString" => ["/", "self::fooBar",];
        yield "extremeRootWithNonExistentFunctionName" => ["/", "foobar",];

        yield "typicalRootClosure" => ["/", function () {
            },];
    }

    /** Provides valid routes and invalid handlers for tests of single-HTTP-method convenience registration methods. */
    public static function providerRoutesAndInvalidHandlers(): iterable
    {
        yield "invalidRootEmptyArray" => ["/", [],];
        yield "invalidRootArrayWithSingleFunctionName" => ["/", ["phpinfo"],];
    }

    /** Provides valid arguments for registering route handlers. */
    public static function providerValidRegistrationArguments(): iterable
    {
        $handlerObject = new class
        {
            public function nullRouteHandler(): void
            {
            }
        };

        $handlerClosure = function() {
        };

        // single HTTP method, as string and as single array element
        foreach (HttpMethod::cases() as $httpMethod) {
            yield "typicalRootStaticMethod{$httpMethod->value}MethodString" => ["/", $httpMethod->value, [self::class, "nullStaticRouteHandler"],];
            yield "typicalRootStaticMethod{$httpMethod->value}tMethodArray" => ["/", [$httpMethod->value,], [self::class, "nullStaticRouteHandler"],];
            yield "typicalRootMethod{$httpMethod->value}MethodString" => ["/", $httpMethod->value, [$handlerObject, "nullRouteHandler"],];
            yield "typicalRootMethod{$httpMethod->value}MethodArray" => ["/", [$httpMethod->value,], [$handlerObject, "nullRouteHandler"],];
            yield "typicalRootClosure{$httpMethod->value}MethodString" => ["/", $httpMethod->value, $handlerClosure,];
            yield "typicalRootClosure{$httpMethod->value}MethodArray" => ["/", [$httpMethod->value], $handlerClosure,];
            yield "typicalRootFunctionName{$httpMethod->value}MethodString" => ["/", $httpMethod->value, "phpinfo",];
            yield "typicalRootFunctionName{$httpMethod->value}MethodArray" => ["/", [$httpMethod->value,], "phpinfo",];
            yield "typicalRootStaticMethodString{$httpMethod->value}MethodString" => ["/", $httpMethod->value, "self::nullStaticRouteHandler",];
            yield "typicalRootStaticMethodString{$httpMethod->value}MethodArray" => ["/", [$httpMethod->value,], "self::nullStaticRouteHandler",];
            yield "extremeRootArrayWithNonExistentStaticMethod{$httpMethod->value}MethodString" => ["/", $httpMethod->value, ["foo", "bar"],];
            yield "extremeRootArrayWithNonExistentStaticMethod{$httpMethod->value}MethodArray" => ["/", [$httpMethod->value,], ["foo", "bar"],];
            yield "extremeRootArrayWithNonExistentStaticMethodString{$httpMethod->value}MethodString" => ["/", $httpMethod->value, "self::fooBar",];
            yield "extremeRootArrayWithNonExistentStaticMethodString{$httpMethod->value}MethodArray" => ["/", [$httpMethod->value,], "self::fooBar",];
            yield "extremeRootArrayWithNonExistentFunctionNameString{$httpMethod->value}MethodString" => ["/", $httpMethod->value, "foobar",];
            yield "extremeRootArrayWithNonExistentFunctionNameString{$httpMethod->value}MethodArray" => ["/", [$httpMethod->value,], "foobar",];

            yield "typicalHomeStaticMethod{$httpMethod->value}MethodString" => ["/home", $httpMethod->value, [self::class, "nullStaticRouteHandler"],];
            yield "typicalHomeStaticMethod{$httpMethod->value}MethodArray" => ["/home", [$httpMethod->value,], [self::class, "nullStaticRouteHandler"],];
            yield "typicalHomeMethod{$httpMethod->value}MethodString" => ["/home", $httpMethod->value, [$handlerObject, "nullRouteHandler"],];
            yield "typicalHomeMethod{$httpMethod->value}MethodArray" => ["/home", [$httpMethod->value,], [$handlerObject, "nullRouteHandler"],];
            yield "typicalHomeClosure{$httpMethod->value}MethodString" => ["/home", $httpMethod->value, $handlerClosure,];
            yield "typicalHomeClosure{$httpMethod->value}MethodArray" => ["/home", [$httpMethod->value,], $handlerClosure,];
            yield "typicalHomeFunctionName{$httpMethod->value}MethodString" => ["/home", $httpMethod->value, "phpinfo",];
            yield "typicalHomeFunctionName{$httpMethod->value}MethodArray" => ["/home", [$httpMethod->value,], "phpinfo",];
            yield "typicalHomeStaticMethodString{$httpMethod->value}MethodString" => ["/home", $httpMethod->value, "self::nullStaticRouteHandler",];
            yield "typicalHomeStaticMethodString{$httpMethod->value}MethodArray" => ["/home", [$httpMethod->value,], "self::nullStaticRouteHandler",];
            yield "typicalMultiSegmentRouteStaticMethod{$httpMethod->value}MethodString" => ["/account/user/home", $httpMethod->value, [self::class, "nullStaticRouteHandler"],];
            yield "typicalMultiSegmentRouteStaticMethod{$httpMethod->value}MethodArray" => ["/account/user/home", [$httpMethod->value,], [self::class, "nullStaticRouteHandler"],];
            yield "typicalMultiSegmentRouteMethod{$httpMethod->value}MethodString" => ["/account/user/home", $httpMethod->value, [$handlerObject, "nullRouteHandler"],];
            yield "typicalMultiSegmentRouteMethod{$httpMethod->value}MethodArray" => ["/account/user/home", [$httpMethod->value,], [$handlerObject, "nullRouteHandler"],];
            yield "typicalMultiSegmentRouteClosure{$httpMethod->value}MethodString" => ["/account/user/home", $httpMethod->value, $handlerClosure,];
            yield "typicalMultiSegmentRouteClosure{$httpMethod->value}MethodArray" => ["/account/user/home", [$httpMethod->value,], $handlerClosure,];
            yield "typicalMultiSegmentRouteFunctionName{$httpMethod->value}MethodString" => ["/account/user/home", $httpMethod->value, "phpinfo",];
            yield "typicalMultiSegmentRouteFunctionName{$httpMethod->value}MethodArray" => ["/account/user/home", [$httpMethod->value,], "phpinfo",];
            yield "typicalMultiSegmentRouteStaticMethodString{$httpMethod->value}MethodString" => ["/account/user/home", $httpMethod->value, "self::nullStaticRouteHandler",];
            yield "typicalMultiSegmentRouteStaticMethodString{$httpMethod->value}MethodArray" => ["/account/user/home", [$httpMethod->value,], "self::nullStaticRouteHandler",];

            yield "typicalParameterisedRouteStaticMethod{$httpMethod->value}MethodString" => ["/user/{id}/home", $httpMethod->value, [self::class, "nullStaticRouteHandler"],];
            yield "typicalParameterisedRouteStaticMethod{$httpMethod->value}MethodArray" => ["/user/{id}/home", [$httpMethod->value,], [self::class, "nullStaticRouteHandler"],];
            yield "typicalParameterisedRouteMethod{$httpMethod->value}MethodString" => ["/user/{id}/home", $httpMethod->value, [$handlerObject, "nullRouteHandler"],];
            yield "typicalParameterisedRouteMethod{$httpMethod->value}MethodArray" => ["/user/{id}/home", [$httpMethod->value,], [$handlerObject, "nullRouteHandler"],];
            yield "typicalParameterisedRouteClosure{$httpMethod->value}MethodString" => ["/user/{id}/home", $httpMethod->value, $handlerClosure,];
            yield "typicalParameterisedRouteClosure{$httpMethod->value}MethodArray" => ["/user/{id}/home", [$httpMethod->value,], $handlerClosure,];
            yield "typicalParameterisedRouteFunctionName{$httpMethod->value}MethodString" => ["/user/{id}/home", $httpMethod->value, "phpinfo",];
            yield "typicalParameterisedRouteFunctionName{$httpMethod->value}MethodArray" => ["/user/{id}/home", [$httpMethod->value,], "phpinfo",];
            yield "typicalParameterisedRouteStaticMethodString{$httpMethod->value}MethodString" => ["/user/{id}/home", $httpMethod->value, "self::nullStaticRouteHandler",];
            yield "typicalParameterisedRouteStaticMethodString{$httpMethod->value}MethodArray" => ["/user/{id}/home", [$httpMethod->value,], "self::nullStaticRouteHandler",];
            yield "typicalMultiParameterRouteStaticMethod{$httpMethod->value}MethodString" => ["/account/{account_id}/user/{user_id}/home", $httpMethod->value, [self::class, "nullStaticRouteHandler"],];
            yield "typicalMultiParameterRouteStaticMethod{$httpMethod->value}MethodArray" => ["/account/{account_id}/user/{user_id}/home", [$httpMethod->value,], [self::class, "nullStaticRouteHandler"],];
            yield "typicalMultiParameterRouteMethod{$httpMethod->value}MethodString" => ["/account/{account_id}/user/{user_id}/home", $httpMethod->value, [$handlerObject, "nullRouteHandler"],];
            yield "typicalMultiParameterRouteMethod{$httpMethod->value}MethodArray" => ["/account/{account_id}/user/{user_id}/home", [$httpMethod->value,], [$handlerObject, "nullRouteHandler"],];
            yield "typicalMultiParameterRouteClosure{$httpMethod->value}MethodString" => ["/account/{account_id}/user/{user_id}/home", $httpMethod->value, $handlerClosure,];
            yield "typicalMultiParameterRouteClosure{$httpMethod->value}MethodArray" => ["/account/{account_id}/user/{user_id}/home", [$httpMethod->value,], $handlerClosure,];
            yield "typicalMultiParameterRouteFunctionName{$httpMethod->value}MethodString" => ["/account/{account_id}/user/{user_id}/home", $httpMethod->value, "phpinfo",];
            yield "typicalMultiParameterRouteFunctionName{$httpMethod->value}MethodArray" => ["/account/{account_id}/user/{user_id}/home", [$httpMethod->value,], "phpinfo",];
            yield "typicalMultiParameterRouteStaticMethodString{$httpMethod->value}MethodString" => ["/account/{account_id}/user/{user_id}/home", $httpMethod->value, "self::nullStaticRouteHandler",];
            yield "typicalMultiParameterRouteStaticMethodString{$httpMethod->value}MethodArray" => ["/account/{account_id}/user/{user_id}/home", [$httpMethod->value,], "self::nullStaticRouteHandler",];
        }

        yield "typicalRootStaticMethodAnyMethodString" => ["/", RouterContract::AnyMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootStaticMethodAnyMethodArray" => ["/", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalRootMethodAnyMethodString" => ["/", RouterContract::AnyMethod, [$handlerObject, "nullRouteHandler"],];
        yield "typicalRootMethodAnyMethodArray" => ["/", [RouterContract::AnyMethod,], [$handlerObject, "nullRouteHandler"],];
        yield "typicalRootClosureAnyMethodString" => ["/", RouterContract::AnyMethod, $handlerClosure,];
        yield "typicalRootClosureAnyMethodArray" => ["/", [RouterContract::AnyMethod,], $handlerClosure,];
        yield "typicalRootFunctionNameAnyMethodString" => ["/", RouterContract::AnyMethod, "phpinfo",];
        yield "typicalRootFunctionNameAnyMethodArray" => ["/", [RouterContract::AnyMethod,], "phpinfo",];
        yield "typicalRootStaticMethodStringAnyMethodString" => ["/", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "typicalRootStaticMethodStringAnyMethodArray" => ["/", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];

        yield "extremeRootArrayWithNonExistentStaticMethodAnyMethodString" => ["/", RouterContract::AnyMethod, ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodAnyMethodArray" => ["/", [RouterContract::AnyMethod,], ["foo", "bar"],];
        yield "extremeRootArrayWithNonExistentStaticMethodStringAnyMethodString" => ["/", RouterContract::AnyMethod, "self::fooBar",];
        yield "extremeRootArrayWithNonExistentStaticMethodStringAnyMethodArray" => ["/", [RouterContract::AnyMethod,], "self::fooBar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringAnyMethodString" => ["/", RouterContract::AnyMethod, "foobar",];
        yield "extremeRootArrayWithNonExistentFunctionNameStringAnyMethodArray" => ["/", [RouterContract::AnyMethod,], "foobar",];

        // routes matching multiple HTTP methods
        yield "typicalRootGetAndPostStaticMethodArray" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], [self::class, "nullRouteHandler"],];
        yield "typicalRootGetAndPostStaticMethodString" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], "self::nullStaticRouteHandler",];
        yield "typicalRootGetAndPostMethodArray" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], [$handlerObject, "nullRouteHandler"],];
        yield "typicalRootGetAndPostClosure" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], $handlerClosure,];
        yield "typicalRootGetAndPostFunctionName" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, ], "phpinfo",];

        // duplicate HTTP methods shouldn't attempt to register a handler more than once for the same method and route
        yield "extremeRootDuplicatedMethodStaticMethodArray" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], [self::class, "nullRouteHandler"],];
        yield "extremeRootDuplicatedMethodStaticMethodString" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], "self::nullStaticRouteHandler",];
        yield "extremeRootDuplicatedMethodMethodArray" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], [$handlerObject, "nullRouteHandler"],];
        yield "extremeRootDuplicatedMethodClosure" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], $handlerClosure,];
        yield "extremeRootDuplicatedMethodFunctionName" => ["/", [RouterContract::GetMethod, RouterContract::PostMethod, RouterContract::GetMethod, ], "phpinfo",];

        // route paths other than root
        yield "typicalHomeStaticMethodAnyMethodString" => ["/home", RouterContract::AnyMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeStaticMethodAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalHomeMethodAnyMethodString" => ["/home", RouterContract::AnyMethod, [$handlerObject, "nullRouteHandler"],];
        yield "typicalHomeMethodAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], [$handlerObject, "nullRouteHandler"],];
        yield "typicalHomeClosureAnyMethodString" => ["/home", RouterContract::AnyMethod, $handlerClosure,];
        yield "typicalHomeClosureAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], $handlerClosure,];
        yield "typicalHomeFunctionNameAnyMethodString" => ["/home", RouterContract::AnyMethod, "phpinfo",];
        yield "typicalHomeFunctionNameAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], "phpinfo",];
        yield "typicalHomeStaticMethodStringAnyMethodString" => ["/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "typicalHomeStaticMethodStringAnyMethodArray" => ["/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];

        yield "typicalMultiSegmentRouteStaticMethodAnyMethodString" => ["/account/user/home", RouterContract::AnyMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteStaticMethodAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodAnyMethodString" => ["/account/user/home", RouterContract::AnyMethod, [$handlerObject, "nullRouteHandler"],];
        yield "typicalMultiSegmentRouteMethodAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], [$handlerObject, "nullRouteHandler"],];

        yield "typicalMultiSegmentRouteClosureAnyMethodString" => ["/account/user/home", RouterContract::AnyMethod, $handlerClosure,];
        yield "typicalMultiSegmentRouteClosureAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], $handlerClosure,];

        yield "typicalMultiSegmentRouteFunctionNameAnyMethodString" => ["/account/user/home", RouterContract::AnyMethod, "phpinfo",];
        yield "typicalMultiSegmentRouteFunctionNameAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], "phpinfo",];

        yield "typicalMultiSegmentRouteStaticMethodStringAnyMethodString" => ["/account/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiSegmentRouteStaticMethodStringAnyMethodArray" => ["/account/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];

        yield "typicalParameterisedRouteStaticMethodAnyMethodString" => ["/user/{id}/home", RouterContract::AnyMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteStaticMethodAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalParameterisedRouteMethodAnyMethodString" => ["/user/{id}/home", RouterContract::AnyMethod, [$handlerObject, "nullRouteHandler"],];
        yield "typicalParameterisedRouteMethodAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], [$handlerObject, "nullRouteHandler"],];

        yield "typicalParameterisedRouteClosureAnyMethodString" => ["/user/{id}/home", RouterContract::AnyMethod, $handlerClosure,];
        yield "typicalParameterisedRouteClosureAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], $handlerClosure,];

        yield "typicalParameterisedRouteFunctionNameAnyMethodString" => ["/user/{id}/home", RouterContract::AnyMethod, "phpinfo",];
        yield "typicalParameterisedRouteFunctionNameAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], "phpinfo",];
        yield "typicalParameterisedRouteStaticMethodStringAnyMethodString" => ["/user/{id}/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "typicalParameterisedRouteStaticMethodStringAnyMethodArray" => ["/user/{id}/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];

        yield "typicalMultiParameterRouteStaticMethodAnyMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::AnyMethod, [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteStaticMethodAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "typicalMultiParameterRouteMethodAnyMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::AnyMethod, [$handlerObject, "nullRouteHandler"],];
        yield "typicalMultiParameterRouteMethodAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], [$handlerObject, "nullRouteHandler"],];

        yield "typicalMultiParameterRouteClosureAnyMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::AnyMethod, $handlerClosure,];
        yield "typicalMultiParameterRouteClosureAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], $handlerClosure,];

        yield "typicalMultiParameterRouteFunctionNameAnyMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::AnyMethod, "phpinfo",];
        yield "typicalMultiParameterRouteFunctionNameAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], "phpinfo",];
        yield "typicalMultiParameterRouteStaticMethodStringAnyMethodString" => ["/account/{account_id}/user/{user_id}/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "typicalMultiParameterRouteStaticMethodStringAnyMethodArray" => ["/account/{account_id}/user/{user_id}/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];
    }

    /** Provides route definitions that duplicate path segment parameter names. */
    public static function providerRegistrationsWithDuplicateRouteParameters(): iterable
    {
        $handlerObject = new class
        {
            public function nullRouteHandler(): void
            {
            }
        };

        $handlerClosure = function() {
        };

        foreach (HttpMethod::cases() as $httpMethod) {
            yield "invalidDuplicateParameterRouteStaticMethod{$httpMethod->value}MethodString" => ["/account/{id}/user/{id}/home", $httpMethod->value, [self::class, "nullStaticRouteHandler"],];
            yield "invalidDuplicateParameterRouteStaticMethod{$httpMethod->value}MethodArray" => ["/account/{id}/user/{id}/home", [$httpMethod->value,], [self::class, "nullStaticRouteHandler"],];
            yield "invalidDuplicateParameterRouteMethod{$httpMethod->value}MethodString" => ["/account/{id}/user/{id}/home", $httpMethod->value, [$handlerObject, "nullRouteHandler"],];
            yield "invalidDuplicateParameterRouteMethod{$httpMethod->value}MethodArray" => ["/account/{id}/user/{id}/home", [$httpMethod->value,], [$handlerObject, "nullRouteHandler"],];
            yield "invalidDuplicateParameterRouteClosure{$httpMethod->value}MethodString" => ["/account/{id}/user/{id}/home", $httpMethod->value, $handlerClosure,];
            yield "invalidDuplicateParameterRouteClosure{$httpMethod->value}MethodArray" => ["/account/{id}/user/{id}/home", [$httpMethod->value,], $handlerClosure,];
            yield "invalidDuplicateParameterRouteFunctionName{$httpMethod->value}MethodString" => ["/account/{id}/user/{id}/home", $httpMethod->value, "phpinfo",];
            yield "invalidDuplicateParameterRouteFunctionName{$httpMethod->value}MethodArray" => ["/account/{id}/user/{id}/home", [$httpMethod->value,], "phpinfo",];
            yield "invalidDuplicateParameterRouteStaticMethodString{$httpMethod->value}MethodString" => ["/account/{id}/user/{id}/home", $httpMethod->value, "self::nullStaticRouteHandler",];
            yield "invalidDuplicateParameterRouteStaticMethodString{$httpMethod->value}MethodArray" => ["/account/{id}/user/{id}/home", [$httpMethod->value,], "self::nullStaticRouteHandler",];
        }

        yield "invalidDuplicateParameterRouteStaticMethodAnyMethodString" => ["/account/{id}/user/{id}/home", RouterContract::AnyMethod, [self::class, "nullStaticRouteHandler"],];
        yield "invalidDuplicateParameterRouteStaticMethodAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "invalidDuplicateParameterRouteMethodAnyMethodString" => ["/account/{id}/user/{id}/home", RouterContract::AnyMethod, [$handlerObject, "nullRouteHandler"],];
        yield "invalidDuplicateParameterRouteMethodAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], [$handlerObject, "nullRouteHandler"],];
        yield "invalidDuplicateParameterRouteClosureAnyMethodString" => ["/account/{id}/user/{id}/home", RouterContract::AnyMethod, $handlerClosure,];
        yield "invalidDuplicateParameterRouteClosureAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], $handlerClosure,];
        yield "invalidDuplicateParameterRouteFunctionNameAnyMethodString" => ["/account/{id}/user/{id}/home", RouterContract::AnyMethod, "phpinfo",];
        yield "invalidDuplicateParameterRouteFunctionNameAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], "phpinfo",];
        yield "invalidDuplicateParameterRouteStaticMethodStringAnyMethodString" => ["/account/{id}/user/{id}/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "invalidDuplicateParameterRouteStaticMethodStringAnyMethodArray" => ["/account/{id}/user/{id}/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];
    }

    /** Provides route registrations with invalid parameter names in path segments. */
    public static function providerRegistrationsWithInvalidRouteParameters(): iterable
    {
        $handlerObject = new class
        {
            public function nullRouteHandler(): void
            {
            }
        };

        $handlerClosure = function() {
        };

        foreach (HttpMethod::cases() as $httpMethod) {
            yield "invalidBadParameterNameEmptyRouteStaticMethod{$httpMethod->value}MethodString" => ["/account/{}/user/home", $httpMethod->value, [self::class, "nullStaticRouteHandler"],];
            yield "invalidBadParameterNameEmptyRouteStaticMethod{$httpMethod->value}MethodArray" => ["/account/{}/user/home", [$httpMethod->value,], [self::class, "nullStaticRouteHandler"],];
            yield "invalidBadParameterNameEmptyRouteMethod{$httpMethod->value}MethodString" => ["/account/{}/user/home", $httpMethod->value, [$handlerObject, "nullRouteHandler"],];
            yield "invalidBadParameterNameEmptyRouteMethod{$httpMethod->value}MethodArray" => ["/account/{}/user/home", [$httpMethod->value,], [$handlerObject, "nullRouteHandler"],];
            yield "invalidBadParameterNameEmptyRouteClosure{$httpMethod->value}MethodString" => ["/account/{}/user/home", $httpMethod->value, $handlerClosure,];
            yield "invalidBadParameterNameEmptyRouteClosure{$httpMethod->value}MethodArray" => ["/account/{}/user/home", [$httpMethod->value,], $handlerClosure,];
            yield "invalidBadParameterNameEmptyRouteFunctionName{$httpMethod->value}MethodString" => ["/account/{}/user/home", $httpMethod->value, "phpinfo",];
            yield "invalidBadParameterNameEmptyRouteFunctionName{$httpMethod->value}MethodArray" => ["/account/{}/user/home", [$httpMethod->value,], "phpinfo",];
            yield "invalidBadParameterNameEmptyRouteStaticMethodString{$httpMethod->value}MethodString" => ["/account/{}/user/home", $httpMethod->value, "self::nullStaticRouteHandler",];
            yield "invalidBadParameterNameEmptyRouteStaticMethodString{$httpMethod->value}MethodArray" => ["/account/{}/user/home", [$httpMethod->value,], "self::nullStaticRouteHandler",];
            yield "invalidBadParameterNameInvalidCharacterRouteStaticMethod{$httpMethod->value}MethodString" => ["/account/{account-id}/user/home", $httpMethod->value, [self::class, "nullStaticRouteHandler"],];
            yield "invalidBadParameterNameInvalidCharacterRouteStaticMethod{$httpMethod->value}MethodArray" => ["/account/{account-id}/user/home", [$httpMethod->value,], [self::class, "nullStaticRouteHandler"],];
            yield "invalidBadParameterNameInvalidCharacterRouteMethod{$httpMethod->value}MethodString" => ["/account/{account-id}/user/home", $httpMethod->value, [$handlerObject, "nullRouteHandler"],];
            yield "invalidBadParameterNameInvalidCharacterRouteMethod{$httpMethod->value}MethodArray" => ["/account/{account-id}/user/home", [$httpMethod->value,], [$handlerObject, "nullRouteHandler"],];
            yield "invalidBadParameterNameInvalidCharacterRouteClosure{$httpMethod->value}MethodString" => ["/account/{account-id}/user/home", $httpMethod->value, $handlerClosure,];
            yield "invalidBadParameterNameInvalidCharacterRouteClosure{$httpMethod->value}MethodArray" => ["/account/{account-id}/user/home", [$httpMethod->value,], $handlerClosure,];
            yield "invalidBadParameterNameInvalidCharacterRouteFunctionName{$httpMethod->value}MethodString" => ["/account/{account-id}/user/home", $httpMethod->value, "phpinfo",];
            yield "invalidBadParameterNameInvalidCharacterRouteFunctionName{$httpMethod->value}MethodArray" => ["/account/{account-id}/user/home", [$httpMethod->value,], "phpinfo",];
            yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodString{$httpMethod->value}MethodString" => ["/account/{account-id}/user/home", $httpMethod->value, "self::nullStaticRouteHandler",];
            yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodString{$httpMethod->value}MethodAny" => ["/account/{account-id}/user/home", [$httpMethod->value,], "self::nullStaticRouteHandler",];
            yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethod{$httpMethod->value}MethodString" => ["/account/{-account_id}/user/home", $httpMethod->value, [self::class, "nullStaticRouteHandler"],];
            yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethod{$httpMethod->value}MethodArray" => ["/account/{-account_id}/user/home", [$httpMethod->value,], [self::class, "nullStaticRouteHandler"],];
            yield "invalidBadParameterNameInvalidFirstCharacterRouteMethod{$httpMethod->value}MethodString" => ["/account/{-account_id}/user/home", $httpMethod->value, [$handlerObject, "nullRouteHandler"],];
            yield "invalidBadParameterNameInvalidFirstCharacterRouteMethod{$httpMethod->value}MethodArray" => ["/account/{-account_id}/user/home", [$httpMethod->value,], [$handlerObject, "nullRouteHandler"],];
            yield "invalidBadParameterNameInvalidFirstCharacterRouteClosure{$httpMethod->value}MethodString" => ["/account/{-account_id}/user/home", $httpMethod->value, $handlerClosure,];
            yield "invalidBadParameterNameInvalidFirstCharacterRouteClosure{$httpMethod->value}MethodArray" => ["/account/{-account_id}/user/home", [$httpMethod->value,], $handlerClosure,];
            yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionName{$httpMethod->value}MethodString" => ["/account/{-account_id}/user/home", $httpMethod->value, "phpinfo",];
            yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionName{$httpMethod->value}MethodArray" => ["/account/{-account_id}/user/home", [$httpMethod->value,], "phpinfo",];
            yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodString{$httpMethod->value}MethodString" => ["/account/{-account_id}/user/home", $httpMethod->value, "self::nullStaticRouteHandler",];
            yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodString{$httpMethod->value}MethodArray" => ["/account/{-account_id}/user/home", [$httpMethod->value,], "self::nullStaticRouteHandler",];
            yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethod{$httpMethod->value}MethodString" => ["/account/{1account_id}/user/home", $httpMethod->value, [self::class, "nullStaticRouteHandler"],];
            yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethod{$httpMethod->value}MethodArray" => ["/account/{1account_id}/user/home", [$httpMethod->value,], [self::class, "nullStaticRouteHandler"],];
            yield "invalidBadParameterNameNumericFirstCharacterRouteMethod{$httpMethod->value}MethodString" => ["/account/{1account_id}/user/home", $httpMethod->value, [$handlerObject, "nullRouteHandler"],];
            yield "invalidBadParameterNameNumericFirstCharacterRouteMethod{$httpMethod->value}MethodArray" => ["/account/{1account_id}/user/home", [$httpMethod->value,], [$handlerObject, "nullRouteHandler"],];
            yield "invalidBadParameterNameNumericFirstCharacterRouteClosure{$httpMethod->value}MethodString" => ["/account/{1account_id}/user/home", $httpMethod->value, $handlerClosure,];
            yield "invalidBadParameterNameNumericFirstCharacterRouteClosure{$httpMethod->value}MethodArray" => ["/account/{1account_id}/user/home", $httpMethod->value, $handlerClosure,];
            yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionName{$httpMethod->value}MethodString" => ["/account/{1account_id}/user/home", $httpMethod->value, "phpinfo",];
            yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionName{$httpMethod->value}MethodArray" => ["/account/{1account_id}/user/home", [$httpMethod->value,], "phpinfo",];
            yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodString{$httpMethod->value}MethodString" => ["/account/{1account_id}/user/home", $httpMethod->value, "self::nullStaticRouteHandler",];
            yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodString{$httpMethod->value}MethodArray" => ["/account/{1account_id}/user/home", [$httpMethod->value], "self::nullStaticRouteHandler",];
        }

        yield "invalidBadParameterNameEmptyRouteStaticMethodAnyMethodString" => ["/account/{}/user/home", RouterContract::AnyMethod, [self::class, "nullStaticRouteHandler"],];
        yield "invalidBadParameterNameEmptyRouteStaticMethodAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "invalidBadParameterNameEmptyRouteMethodAnyMethodString" => ["/account/{}/user/home", RouterContract::AnyMethod, [$handlerObject, "nullRouteHandler"],];
        yield "invalidBadParameterNameEmptyRouteMethodAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], [$handlerObject, "nullRouteHandler"],];
        yield "invalidBadParameterNameEmptyRouteClosureAnyMethodString" => ["/account/{}/user/home", RouterContract::AnyMethod, $handlerClosure,];
        yield "invalidBadParameterNameEmptyRouteClosureAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], $handlerClosure,];
        yield "invalidBadParameterNameEmptyRouteFunctionNameAnyMethodString" => ["/account/{}/user/home", RouterContract::AnyMethod, "phpinfo",];
        yield "invalidBadParameterNameEmptyRouteFunctionNameAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], "phpinfo",];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringAnyMethodString" => ["/account/{}/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "invalidBadParameterNameEmptyRouteStaticMethodStringAnyMethodArray" => ["/account/{}/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodAnyMethodString" => ["/account/{account-id}/user/home", RouterContract::AnyMethod, [self::class, "nullStaticRouteHandler"],];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodAnyMethodString" => ["/account/{account-id}/user/home", RouterContract::AnyMethod, [$handlerObject, "nullRouteHandler"],];
        yield "invalidBadParameterNameInvalidCharacterRouteMethodAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], [$handlerObject, "nullRouteHandler"],];
        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureAnyMethodString" => ["/account/{1account_id}/user/home", RouterContract::AnyMethod, $handlerClosure,];
        yield "invalidBadParameterNameNumericFirstCharacterRouteClosureAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], $handlerClosure,];
        yield "invalidBadParameterNameInvalidCharacterRouteClosureAnyMethodString" => ["/account/{account-id}/user/home", RouterContract::AnyMethod, $handlerClosure,];
        yield "invalidBadParameterNameInvalidCharacterRouteClosureAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], $handlerClosure,];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameAnyMethodString" => ["/account/{account-id}/user/home", RouterContract::AnyMethod, "phpinfo",];
        yield "invalidBadParameterNameInvalidCharacterRouteFunctionNameAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], "phpinfo",];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringAnyMethodString" => ["/account/{account-id}/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "invalidBadParameterNameInvalidCharacterRouteStaticMethodStringAnyMethodArray" => ["/account/{account-id}/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodAnyMethodString" => ["/account/{-account_id}/user/home", RouterContract::AnyMethod, [self::class, "nullStaticRouteHandler"],];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodAnyMethodString" => ["/account/{-account_id}/user/home", RouterContract::AnyMethod, [$handlerObject, "nullRouteHandler"],];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteMethodAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], [$handlerObject, "nullRouteHandler"],];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureAnyMethodString" => ["/accoun/{-account_id}/user/home", RouterContract::AnyMethod, $handlerClosure,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteClosureAnyMethodArray" => ["/accoun/{-account_id}/user/home", [RouterContract::AnyMethod,], $handlerClosure,];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameAnyMethodString" => ["/account/{-account_id}/user/home", RouterContract::AnyMethod, "phpinfo",];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteFunctionNameAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], "phpinfo",];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringAnyMethodString" => ["/account/{-account_id}/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "invalidBadParameterNameInvalidFirstCharacterRouteStaticMethodStringAnyMethodArray" => ["/account/{-account_id}/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodAnyMethodString" => ["/account/{1account_id}/user/home", RouterContract::AnyMethod, [self::class, "nullStaticRouteHandler"],];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], [self::class, "nullStaticRouteHandler"],];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodAnyMethodString" => ["/account/{1account_id}/user/home", RouterContract::AnyMethod, [$handlerObject, "nullRouteHandler"],];
        yield "invalidBadParameterNameNumericFirstCharacterRouteMethodAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], [$handlerObject, "nullRouteHandler"],];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameAnyMethodString" => ["/account/{1account_id}/user/home", RouterContract::AnyMethod, "phpinfo",];
        yield "invalidBadParameterNameNumericFirstCharacterRouteFunctionNameAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], "phpinfo",];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringAnyMethodString" => ["/account/{1account_id}/user/home", RouterContract::AnyMethod, "self::nullStaticRouteHandler",];
        yield "invalidBadParameterNameNumericFirstCharacterRouteStaticMethodStringAnyMethodArray" => ["/account/{1account_id}/user/home", [RouterContract::AnyMethod,], "self::nullStaticRouteHandler",];
    }

    /** Provides registration arguments with invalid route handlers. */
    public static function providerRegistrationsWithInvalidRouteHandlers(): iterable
    {
        foreach (HttpMethod::cases() as $httpMethod) {
            yield "invalidRootEmptyArray{$httpMethod->value}MethodString" => ["/", $httpMethod->value, [],];
            yield "invalidRootEmptyArray{$httpMethod->value}MethodArray" => ["/", [$httpMethod->value,], [],];
            yield "invalidRootArrayWithSingleFunctionName{$httpMethod->value}MethodString" => ["/", $httpMethod->value, ["phpinfo"],];
            yield "invalidRootArrayWithSingleFunctionName{$httpMethod->value}MethodArray" => ["/", [$httpMethod->value,], ["phpinfo"],];
        }

        yield "invalidRootEmptyArrayAnyMethodString" => ["/", RouterContract::AnyMethod, [],];
        yield "invalidRootEmptyArrayAnyMethodArray" => ["/", [RouterContract::AnyMethod,], [],];
        yield "invalidRootArrayWithSingleFunctionNameAnyMethodString" => ["/", RouterContract::AnyMethod, ["phpinfo"],];
        yield "invalidRootArrayWithSingleFunctionNameAnyMethodArray" => ["/", [RouterContract::AnyMethod,], ["phpinfo"],];
    }

    /** Provides route registration arguments with invalid HTTP methods. */
    public static function providerRegistrationsWithInvalidMethods(): iterable
    {
        yield "invalidRootWithInvalidMethodString" => ["/", "foo", "phpinfo",];
        yield "invalidRootWithInvalidMethodArray" => ["/", ["foo"], "phpinfo",];
        yield "invalidRootWithInvalidMethodInOtherwiseValidArray" => ["/", ["foo", RouterContract::GetMethod, RouterContract::PostMethod,], "phpinfo",];
    }

    /** Provides route registrations that conflict. */
    public static function providerConflictingRoutes(): iterable
    {
        yield "rootPathWithGetNoParameters" => [RouterContract::GetMethod, "/", RouterContract::GetMethod, "/",];
        yield "simplePathWithGetSingleParameter" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::GetMethod, "/edit/{slug}",];
        yield "simplePathWithAny1Get2MultipleParameters" => [RouterContract::AnyMethod, "/edit/{id}/{force}/{really}", RouterContract::GetMethod, "/edit/{slug}/{id}/{field}",];
    }

    /** Provides route registrations that don't conflict. */
    public static function providerNonConflictingRoutes(): iterable
    {
        yield "rootPathWithGet1Post2NoParametersNoConflict" => [RouterContract::GetMethod, "/", RouterContract::PostMethod, "/", false, ];
        yield "simplePathWithGetParametersInDifferentPositions" => [RouterContract::GetMethod, "/edit/{type}/{id}", RouterContract::GetMethod, "/{type}/{id}/edit", false,];
        yield "simplePathWithGet1Post2SingleParameterNoConflict" => [RouterContract::GetMethod, "/edit/{id}", RouterContract::PostMethod, "/edit/{id}", false, ];
    }

    /** Provides route registrations and unroutable Requests. */
    public static function providerUnroutableRequests(): iterable
    {
        yield "typicalUnroutableIncorrectMethodOneRegisteredMethod" => [RouterContract::GetMethod, "/", self::makeRequest("/", HttpMethod::Post)];
        yield "typicalUnroutableIncorrectMethodManyRegisteredMethods" => [[RouterContract::GetMethod, RouterContract::PostMethod,], "/", self::makeRequest("/", HttpMethod::Put),];
        yield "typicalUnroutableNoMatchedRoute" => [RouterContract::GetMethod, "/", self::makeRequest("/home", HttpMethod::Post),];
    }

    /** Provides route registrations and requests that should be routed by them. */
    public static function providerRouteRegistrationsAndRoutableRequests(): iterable
    {
        yield "typicalGetWithNoParameters" => [RouterContract::GetMethod, "/home", self::makeRequest("/home", HttpMethod::Get), function (RequestContract $request): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/home", $request->path());
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalGetWithLongerPathAndNoParameters" => [RouterContract::GetMethod, "/admin/users/home", self::makeRequest("/admin/users/home", HttpMethod::Get), function (RequestContract $request): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/admin/users/home", $request->path());
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyGetWithNoParameters" => [RouterContract::AnyMethod, "/home", self::makeRequest("/home", HttpMethod::Get), function (RequestContract $request): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/home", $request->path());
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPostWithNoParameters" => [RouterContract::AnyMethod, "/home", self::makeRequest("/home", HttpMethod::Post), function (RequestContract $request): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/home", $request->path());
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPutWithNoParameters" => [RouterContract::AnyMethod, "/home", self::makeRequest("/home", HttpMethod::Put), function (RequestContract $request): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/home", $request->path());
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyDeleteWithNoParameters" => [RouterContract::AnyMethod, "/home", self::makeRequest("/home", HttpMethod::Delete), function (RequestContract $request): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/home", $request->path());
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyHeadWithNoParameters" => [RouterContract::AnyMethod, "/home", self::makeRequest("/home", HttpMethod::Head), function (RequestContract $request): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/home", $request->path());
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyOptionsWithNoParameters" => [RouterContract::AnyMethod, "/home", self::makeRequest("/home", HttpMethod::Options), function (RequestContract $request): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/home", $request->path());
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyConnectWithNoParameters" => [RouterContract::AnyMethod, "/home", self::makeRequest("/home", HttpMethod::Connect), function (RequestContract $request): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/home", $request->path());
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPatchWithNoParameters" => [RouterContract::AnyMethod, "/home", self::makeRequest("/home", HttpMethod::Patch), function (RequestContract $request): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/home", $request->path());
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalGetWithParameterInt" => [RouterContract::GetMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Get), function (RequestContract $request, int $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalGetWithParameterString" => [RouterContract::GetMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Get), function (RequestContract $request, string $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame("123", $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalGetWithParameterFloat" => [RouterContract::GetMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Get), function (RequestContract $request, float $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123.0, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalGetWithParameterBoolTrueInt" => [RouterContract::GetMethod, "/edit/{confirmed}", self::makeRequest("/edit/1", HttpMethod::Get), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/1", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalGetWithParameterBoolTrueString" => [RouterContract::GetMethod, "/edit/{confirmed}", self::makeRequest("/edit/true", HttpMethod::Get), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/true", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalGetWithParameterBoolFalseInt" => [RouterContract::GetMethod, "/edit/{confirmed}", self::makeRequest("/edit/0", HttpMethod::Get), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/0", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalGetWithParameterBoolFalseString" => [RouterContract::GetMethod, "/edit/{confirmed}", self::makeRequest("/edit/false", HttpMethod::Get), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/false", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyGetWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Get), function (RequestContract $request, int $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyGetWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Get), function (RequestContract $request, string $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame("123", $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyGetWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Get), function (RequestContract $request, float $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123.0, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyGetWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/1", HttpMethod::Get), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/1", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyGetWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/true", HttpMethod::Get), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/true", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyGetWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/0", HttpMethod::Get), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/0", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyGetWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/false", HttpMethod::Get), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/false", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPostWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Post), function (RequestContract $request, int $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPostWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Post), function (RequestContract $request, string $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame("123", $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPostWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Post), function (RequestContract $request, float $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123.0, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPostWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/1", HttpMethod::Post), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/1", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPostWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/true", HttpMethod::Post), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/true", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPostWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/0", HttpMethod::Post), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/0", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPostWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/false", HttpMethod::Post), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/false", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPutWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Put), function (RequestContract $request, int $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPutWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Put), function (RequestContract $request, string $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame("123", $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPutWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Put), function (RequestContract $request, float $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123.0, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPutWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/1", HttpMethod::Put), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/1", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPutWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/true", HttpMethod::Put), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/true", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPutWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/0", HttpMethod::Put), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/0", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPutWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/false", HttpMethod::Put), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/false", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyHeadWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Head), function (RequestContract $request, int $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyHeadWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Head), function (RequestContract $request, string $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame("123", $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyHeadWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Head), function (RequestContract $request, float $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123.0, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyHeadWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/1", HttpMethod::Head), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/1", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyHeadWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/true", HttpMethod::Head), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/true", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyHeadWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/0", HttpMethod::Head), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/0", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyHeadWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/false", HttpMethod::Head), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/false", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyConnectWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Connect), function (RequestContract $request, int $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyConnectWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Connect), function (RequestContract $request, string $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame("123", $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyConnectWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Connect), function (RequestContract $request, float $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123.0, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyConnectWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/1", HttpMethod::Connect), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/1", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyConnectWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/true", HttpMethod::Connect), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/true", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyConnectWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/0", HttpMethod::Connect), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/0", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyConnectWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/false", HttpMethod::Connect), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/false", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyDeleteWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Delete), function (RequestContract $request, int $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyDeleteWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Delete), function (RequestContract $request, string $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame("123", $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyDeleteWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Delete), function (RequestContract $request, float $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123.0, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyDeleteWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/1", HttpMethod::Delete), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/1", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyDeleteWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/true", HttpMethod::Delete), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/true", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyDeleteWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/0", HttpMethod::Delete), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/0", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyDeleteWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/false", HttpMethod::Delete), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/false", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPatchWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Patch), function (RequestContract $request, int $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPatchWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Patch), function (RequestContract $request, string $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame("123", $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPatchWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Patch), function (RequestContract $request, float $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123.0, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPatchWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/1", HttpMethod::Patch), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/1", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPatchWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/true", HttpMethod::Patch), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/true", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPatchWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/0", HttpMethod::Patch), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/0", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyPatchWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/false", HttpMethod::Patch), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/false", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyOptionsWithParameterInt" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Options), function (RequestContract $request, int $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyOptionsWithParameterString" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Options), function (RequestContract $request, string $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame("123", $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyOptionsWithParameterFloat" => [RouterContract::AnyMethod, "/edit/{id}", self::makeRequest("/edit/123", HttpMethod::Options), function (RequestContract $request, float $id): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/123", $request->path());
            self::assertSame(123.0, $id);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyOptionsWithParameterBoolTrueInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/1", HttpMethod::Options), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/1", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyOptionsWithParameterBoolTrueString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/true", HttpMethod::Options), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/true", $request->path());
            self::assertSame(true, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyOptionsWithParameterBoolFalseInt" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/0", HttpMethod::Options), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/0", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalAnyOptionsWithParameterBoolFalseString" => [RouterContract::AnyMethod, "/edit/{confirmed}", self::makeRequest("/edit/false", HttpMethod::Options), function (RequestContract $request, bool $confirmed): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/edit/false", $request->path());
            self::assertSame(false, $confirmed);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalGetWithParametersDifferentOrderManyTypes" => [RouterContract::GetMethod, "/object/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/object/article/9563/set/status/draft", HttpMethod::Get), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/object/article/9563/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(9563, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalGetWithAllParametersDifferentOrderManyTypes" => [RouterContract::GetMethod, "/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/article/123456789/set/status/draft", HttpMethod::Get), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/article/123456789/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(123456789, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalPostWithParametersDifferentOrderManyTypes" => [RouterContract::PostMethod, "/object/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/object/article/9563/set/status/draft", HttpMethod::Post), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/object/article/9563/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(9563, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalPostWithAllParametersDifferentOrderManyTypes" => [RouterContract::PostMethod, "/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/article/123456789/set/status/draft", HttpMethod::Post), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/article/123456789/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(123456789, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalPutWithParametersDifferentOrderManyTypes" => [RouterContract::PutMethod, "/object/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/object/article/9563/set/status/draft", HttpMethod::Put), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/object/article/9563/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(9563, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalPutWithAllParametersDifferentOrderManyTypes" => [RouterContract::PutMethod, "/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/article/123456789/set/status/draft", HttpMethod::Put), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/article/123456789/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(123456789, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalHeadWithParametersDifferentOrderManyTypes" => [RouterContract::HeadMethod, "/object/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/object/article/9563/set/status/draft", HttpMethod::Head), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/object/article/9563/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(9563, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalHeadWithAllParametersDifferentOrderManyTypes" => [RouterContract::HeadMethod, "/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/article/123456789/set/status/draft", HttpMethod::Head), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/article/123456789/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(123456789, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalOptionsWithParametersDifferentOrderManyTypes" => [RouterContract::OptionsMethod, "/object/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/object/article/9563/set/status/draft", HttpMethod::Options), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/object/article/9563/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(9563, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalOptionsWithAllParametersDifferentOrderManyTypes" => [RouterContract::OptionsMethod, "/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/article/123456789/set/status/draft", HttpMethod::Options), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/article/123456789/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(123456789, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalDeleteWithParametersDifferentOrderManyTypes" => [RouterContract::DeleteMethod, "/object/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/object/article/9563/set/status/draft", HttpMethod::Delete), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/object/article/9563/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(9563, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalDeleteWithAllParametersDifferentOrderManyTypes" => [RouterContract::DeleteMethod, "/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/article/123456789/set/status/draft", HttpMethod::Delete), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/article/123456789/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(123456789, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalPatchWithParametersDifferentOrderManyTypes" => [RouterContract::PatchMethod, "/object/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/object/article/9563/set/status/draft", HttpMethod::Patch), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/object/article/9563/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(9563, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalPatchWithAllParametersDifferentOrderManyTypes" => [RouterContract::PatchMethod, "/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/article/123456789/set/status/draft", HttpMethod::Patch), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/article/123456789/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(123456789, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalConnectWithParametersDifferentOrderManyTypes" => [RouterContract::ConnectMethod, "/object/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/object/article/9563/set/status/draft", HttpMethod::Connect), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/object/article/9563/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(9563, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
        yield "typicalConnectWithAllParametersDifferentOrderManyTypes" => [RouterContract::ConnectMethod, "/{type}/{id}/{action}/{property}/{value}", self::makeRequest("/article/123456789/set/status/draft", HttpMethod::Connect), function (RequestContract $request, int $id, string $type, string $action, string $property, string $value): ResponseContract {
            self::assertInstanceOf(RequestContract::class, $request);
            self::assertSame("/article/123456789/set/status/draft", $request->path());
            self::assertSame("article", $type);
            self::assertSame(123456789, $id);
            self::assertSame("set", $action);
            self::assertSame("status", $property);
            self::assertSame("draft", $value);
            return new class extends AbstractResponse {
                public function content(): string
                {
                    return "";
                }
            };
        }];
    }
    
    /** Ensure registerGet() successfully registers valid route handlers. */
    #[DataProvider("providerRoutesAndHandlers")]
    public function testRegisterGet1(string $route, callable | array | string $handler): void
    {
        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should not throw with test data. */
        $router->registerGet($route, $handler);
        $request = self::makeRequest($route);
        self::assertSame($route, $router->matchedRoute($request));
        self::assertSame($handler, $router->routeHandler($request->method(), $route));
    }

    /** Ensure registerGet() rejects invalid route handlers. */
    #[DataProvider("providerRoutesAndInvalidHandlers")]
    public function testRegisterGet2(string $route, array | string $handler): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Argument for parameter \$handler must be a callable or a tuple of class and method name");
        (new Router())->registerGet($route, $handler);
    }

    /** Ensure registerPost() successfully registers valid route handlers. */
    #[DataProvider("providerRoutesAndHandlers")]
    public function testRegisterPost1(string $route, callable | array | string $handler): void
    {
        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should not throw with test data. */
        $router->registerPost($route, $handler);
        $request = self::makeRequest($route, HttpMethod::Post);
        self::assertSame($route, $router->matchedRoute($request));
        self::assertSame($handler, $router->routeHandler($request->method(), $route));
    }

    /** Ensure registerPost() rejects invalid route handlers. */
    #[DataProvider("providerRoutesAndInvalidHandlers")]
    public function testRegisterPost2(string $route, array | string $handler): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Argument for parameter \$handler must be a callable or a tuple of class and method name");
        (new Router())->registerPost($route, $handler);
    }

    /** Ensure registerPut() successfully registers valid route handlers. */
    #[DataProvider("providerRoutesAndHandlers")]
    public function testRegisterPut1(string $route, callable | array | string $handler): void
    {
        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should not throw with test data. */
        $router->registerPut($route, $handler);
        $request = self::makeRequest($route, HttpMethod::Put);
        self::assertSame($route, $router->matchedRoute($request));
        self::assertSame($handler, $router->routeHandler($request->method(), $route));
    }

    /** Ensure registerPut() rejects invalid route handlers. */
    #[DataProvider("providerRoutesAndInvalidHandlers")]
    public function testRegisterPut2(string $route, array | string $handler): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Argument for parameter \$handler must be a callable or a tuple of class and method name");
        (new Router())->registerPut($route, $handler);
    }

    /** Ensure registerDelete() successfully registers valid route handlers. */
    #[DataProvider("providerRoutesAndHandlers")]
    public function testRegisterDelete1(string $route, callable | array | string $handler): void
    {
        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should not throw with test data. */
        $router->registerDelete($route, $handler);
        $request = self::makeRequest($route, HttpMethod::Delete);
        self::assertSame($route, $router->matchedRoute($request));
        self::assertSame($handler, $router->routeHandler($request->method(), $route));
    }

    /** Ensure registerDelete() rejects invalid route handlers. */
    #[DataProvider("providerRoutesAndInvalidHandlers")]
    public function testRegisterDelete2(string $route, array | string $handler): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Argument for parameter \$handler must be a callable or a tuple of class and method name");
        (new Router())->registerDelete($route, $handler);
    }

    /** Ensure registerOptions() successfully registers valid route handlers. */
    #[DataProvider("providerRoutesAndHandlers")]
    public function testRegisterOptions1(string $route, callable | array | string $handler): void
    {
        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should not throw with test data. */
        $router->registerOptions($route, $handler);
        $request = self::makeRequest($route, HttpMethod::Options);
        self::assertSame($route, $router->matchedRoute($request));
        self::assertSame($handler, $router->routeHandler($request->method(), $route));
    }

    /** Ensure registerOptions() rejects invalid route handlers. */
    #[DataProvider("providerRoutesAndInvalidHandlers")]
    public function testRegisterOptions2(string $route, array | string $handler): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Argument for parameter \$handler must be a callable or a tuple of class and method name");
        (new Router())->registerOptions($route, $handler);
    }

    /** Ensure registerHead() successfully registers valid route handlers. */
    #[DataProvider("providerRoutesAndHandlers")]
    public function testRegisterHead1(string $route, callable | array | string $handler): void
    {
        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should not throw with test data. */
        $router->registerHead($route, $handler);
        $request = self::makeRequest($route, HttpMethod::Head);
        self::assertSame($route, $router->matchedRoute($request));
        self::assertSame($handler, $router->routeHandler($request->method(), $route));
    }

    /** Ensure registerHead() rejects invalid route handlers. */
    #[DataProvider("providerRoutesAndInvalidHandlers")]
    public function testRegisterHead2(string $route, array | string $handler): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Argument for parameter \$handler must be a callable or a tuple of class and method name");
        (new Router())->registerHead($route, $handler);
    }

    /** Ensure registerConnect() successfully registers valid route handlers. */
    #[DataProvider("providerRoutesAndHandlers")]
    public function testRegisterConnect1(string $route, callable | array | string $handler): void
    {
        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should not throw with test data. */
        $router->registerConnect($route, $handler);
        $request = self::makeRequest($route, HttpMethod::Connect);
        self::assertSame($route, $router->matchedRoute($request));
        self::assertSame($handler, $router->routeHandler($request->method(), $route));
    }

    /** Ensure registerConnect() rejects invalid route handlers. */
    #[DataProvider("providerRoutesAndInvalidHandlers")]
    public function testRegisterConnect2(string $route, array | string $handler): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Argument for parameter \$handler must be a callable or a tuple of class and method name");
        (new Router())->registerConnect($route, $handler);
    }

    /** Ensure registerPatch() successfully registers valid route handlers. */
    #[DataProvider("providerRoutesAndHandlers")]
    public function testRegisterPatch1(string $route, callable | array | string $handler): void
    {
        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should not throw with test data. */
        $router->registerPatch($route, $handler);
        $request = self::makeRequest($route, HttpMethod::Patch);
        self::assertSame($route, $router->matchedRoute($request));
        self::assertSame($handler, $router->routeHandler($request->method(), $route));
    }

    /** Ensure registerPatch() rejects invalid route handlers. */
    #[DataProvider("providerRoutesAndInvalidHandlers")]
    public function testRegisterPatch2(string $route, array | string $handler): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Argument for parameter \$handler must be a callable or a tuple of class and method name");
        (new Router())->registerPatch($route, $handler);
    }

    /** Ensure registerTrace() successfully registers valid route handlers. */
    #[DataProvider("providerRoutesAndHandlers")]
    public function testRegisterTrace1(string $route, callable | array | string $handler): void
    {
        $router = new XRay(new Router());
        /** @noinspection PhpUnhandledExceptionInspection Should not throw with test data. */
        $router->registerTrace($route, $handler);
        $request = self::makeRequest($route, HttpMethod::Trace);
        self::assertSame($route, $router->matchedRoute($request));
        self::assertSame($handler, $router->routeHandler($request->method(), $route));
    }

    /** Ensure registerTrace() rejects invalid route handlers. */
    #[DataProvider("providerRoutesAndInvalidHandlers")]
    public function testRegisterTrace2(string $route, array | string $handler): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Argument for parameter \$handler must be a callable or a tuple of class and method name");
        (new Router())->registerTrace($route, $handler);
    }

    /**
     * Ensure register() accepts valid route definitions and handlers.
     *
     * @param string $route The route to register.
     * @param string | string[] $methods The HTTP method(s) to register.
     * @param callable | array | string $handler The handler.
     */
    #[DataProvider("providerValidRegistrationArguments")]
    public function testRegister1(string $route, string | array $methods, callable | array | string $handler): void
    {
        $router = new Router();
        $router->register($route, $methods, $handler);

        if (is_string($methods)) {
            $methods = [$methods,];
        }

        $router = new XRay($router);

        foreach ($methods as $method) {
            if (RouterContract::AnyMethod === $method) {
                foreach (HttpMethod::cases() as $anyMethod) {
                    self::assertSame($route, $router->matchedRoute(self::makeRequest($route, $anyMethod)));
                }
            } else {
                self::assertSame($route, $router->matchedRoute(self::makeRequest($route, HttpMethod::from($method))));
            }
        }
    }

    /**
     * Ensure register() detects duplicate route parameters.
     *
     * @param string $route The route to register.
     * @param string | string[] $methods The HTTP method(s) to register.
     * @param callable | array | string $handler The handler.
     */
    #[DataProvider("providerRegistrationsWithDuplicateRouteParameters")]
    public function testRegister2(string $route, string | array $methods, callable | array | string $handler): void
    {
        $this->expectException(DuplicateRouteParameterNameException::class);
        $router = new Router();
        $router->register($route, $methods, $handler);
    }

    /**
     * Ensure register() detects invalid route parameters.
     *
     * @param string $route The route to register.
     * @param string | string[] $methods The HTTP method(s) to register.
     * @param callable | array | string $handler The handler.
     */
    #[DataProvider("providerRegistrationsWithInvalidRouteParameters")]
    public function testRegister3(string $route, string | array $methods, callable | array | string $handler): void
    {
        $this->expectException(InvalidRouteParameterNameException::class);
        $router = new Router();
        $router->register($route, $methods, $handler);
    }

    /**
     * Ensure register() detects invalid route handlers.
     *
     * @param string $route The route to register.
     * @param string | string[] $methods The HTTP method(s) to register.
     * @param callable | array | string $handler The handler.
     */
    #[DataProvider("providerRegistrationsWithInvalidRouteHandlers")]
    public function testRegister4(string $route, string | array $methods, callable | array | string $handler): void
    {
        $this->expectException(InvalidArgumentException::class);
        $router = new Router();
        $router->register($route, $methods, $handler);
    }

    /**
     * Ensure register() detects invalid HTTP methods.
     *
     * @param string $route The route to register.
     * @param string | string[] $methods The HTTP method(s) to register.
     * @param callable | array | string $handler The handler.
     */
    #[DataProvider("providerRegistrationsWithInvalidMethods")]
    public function testRegister5(string $route, string | array $methods, callable | array | string $handler): void
    {
        $this->expectException(InvalidArgumentException::class);
        $router = new Router();
        $router->register($route, $methods, $handler);
    }

    /**
     * Ensure register() detects conflicting routes.
     *
     * @param string|array<string> $route1Methods The HTTP method(s) for the first route to register.
     * @param string $route1 The path for the first route to register.
     * @param string|array<string> $route2Methods The HTTP method(s) for the second route to register.
     * @param string $route2 The path for the second route to register.
     *
     * @noinspection PhpDocMissingThrowsInspection Only the expected test exception should be thrown.
     */
    #[DataProvider("providerConflictingRoutes")]
    public function testRegister6(string | array $route1Methods, string $route1, string | array $route2Methods, string $route2): void
    {
        $handler = static function(): void {
        };

        $router = new Router();
        /** @noinspection PhpUnhandledExceptionInspection Should never throw with test data. */
        $router->register($route1, $route1Methods, $handler);
        $this->expectException(ConflictingRouteException::class);//, function (RequestContract $request, bool $confirmed): Response {
        /** @noinspection PhpUnhandledExceptionInspection Should only throw the expected test exception. */
        $router->register($route2, $route2Methods, $handler);
    }

    /**
     * Ensure register() works as expected with similar routes that don't conflict..
     *
     * @param string|array<string> $route1Methods The HTTP method(s) for the first route to register.
     * @param string $route1 The path for the first route to register.
     * @param string|array<string> $route2Methods The HTTP method(s) for the second route to register.
     * @param string $route2 The path for the second route to register.
     *
     * @noinspection PhpDocMissingThrowsInspection No exceptions should be thrown.
     */
    #[DataProvider("providerNonConflictingRoutes")]
    public function testRegister7(string | array $route1Methods, string $route1, string | array $route2Methods, string $route2): void
    {
        $accumulateRoutes = static fn (array $routes, int $accumulation): int => $accumulation + count($routes);

        $handler = static function(): void {
        };

        $router = new Router();
        $routerXRay = new XRay($router);

        /** @noinspection PhpUnhandledExceptionInspection Should never throw with test data. */
        $router->register($route1, $route1Methods, $handler);

        // fetch the route count so that we can assert that the registration of the second route adds to it
        $routeCount = accumulate($routerXRay->m_routes, $accumulateRoutes);
        /** @noinspection PhpUnhandledExceptionInspection Should never throw with test data. */
        $router->register($route2, $route2Methods, $handler);
        self::assertGreaterThan($routeCount, accumulate($routerXRay->m_routes, $accumulateRoutes), "The registration of the second route succeeded but didn't add to the routes colleciton in the router");
    }

    /**
     * Ensure route() correctly routes requests to the registered handler.
     *
     * @param string|array<string> $routeMethods The HTTP methods to define for the test route.
     * @param string $route The test route.
     * @param string $requestMethod The HTTP method for the request to test with.
     * @param string $requestPath The path for the request to test with.
     * @param callable $handler The handler to register for the route.
     *
     * @noinspection PhpDocMissingThrowsInspection Only exceptions thrown will be exptected test exceptions.
     */
    #[DataProvider("providerRouteRegistrationsAndRoutableRequests")]
    public function testRoute1(string | array $routeMethods, string $route, RequestContract $request, callable $handler): void
    {
        $router = new Router();
        /** @noinspection PhpUnhandledExceptionInspection Should never throw with test data. */
        $router->register($route, $routeMethods, $handler);
        /** @noinspection PhpUnhandledExceptionInspection Should only throw expected test exceptions. */
        $router->route($request);
        
        // all the handlers in the test data perform at least one assertion
        self::assertGreaterThan(0, $this->getCount());
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

        $expectedResponse = Mockery::mock(ResponseContract::class);

        $handler = function (Logger $injectedLog) use ($log, $expectedResponse): ResponseContract {
            RouterTest::assertSame($log, $injectedLog);
            return $expectedResponse;
        };

        $router = new Router();
        $router->registerGet("/", $handler);
        $actualResponse = $router->route(self::makeRequest("/"));
        self::assertSame($expectedResponse, $actualResponse);
    }

    /** Ensure route() correctly deals with unroutable requests. */
    #[DataProvider("providerUnroutableRequests")]
    public function testRoute3(string | array $routeMethods, string $route, RequestContract $request): void
    {
        $router = new Router();
        $router->register($route, $routeMethods, static fn () => TestCase::fail("route handler should not be called"));
        $this->expectException(UnroutableRequestException::class);
        $router->route($request);
    }
}
