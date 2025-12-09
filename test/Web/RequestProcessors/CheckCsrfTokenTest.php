<?php

declare(strict_types=1);

namespace BeadTests\Web\RequestProcessors;

use Bead\Contracts\Web\Request as RequestContract;
use Bead\Exceptions\Http\CsrfTokenVerificationException;
use Bead\Testing\XRay;
use Bead\Core\Application as CoreApplication;
use Bead\Web\Application as WebApplication;
use Bead\Web\Header;
use Bead\Web\RequestProcessors\CheckCsrfToken;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

class CheckCsrfTokenTest extends TestCase
{
    private CheckCsrfToken $processor;

    public function setUp(): void
    {
        $this->processor = new CheckCsrfToken();
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->processor);
        parent::tearDown();
    }

    /** @return RequestContract&MockInterface */
    private static function createRequest(string $method = "GET", string $path = "/"): RequestContract
    {
        $request = Mockery::mock(RequestContract::class);
        $request->shouldReceive("method")->andReturn($method)->byDefault();
        $request->shouldReceive("path")->andReturn($path)->byDefault();
        return $request;
    }

    /** @return iterable<array{RequestContract,bool}> */
    public static function dataForTestRequiresCsrf1(): iterable
    {
        yield "get" => [self::createRequest("GET"), false,];
        yield "head" => [self::createRequest("HEAD"), false,];
        yield "options" => [self::createRequest("OPTIONS"), false,];
        yield "post" => [self::createRequest("POST"), true,];
        yield "put" => [self::createRequest("PUT"), true,];
        yield "delete" => [self::createRequest("DELETE"), true,];
        yield "connect" => [self::createRequest("CONNECT"), true,];
        yield "trace" => [self::createRequest("TRACE"), true,];
        yield "patch" => [self::createRequest("PATCH"), true,];
    }

    /**
     * Ensure only the expected HTTP methods require CSRF verification.
     *
     * @dataProvider dataForTestRequiresCsrf1
     */
    public function testRequiresCsrf1(RequestContract $request, bool $expected): void
    {
        $processor = new XRay($this->processor);
        self::assertEquals($expected, $processor->requiresCsrf($request));
    }

    /** Ensure CRSF token is in POST data is preferred. */
    public function testRetrieveCsrfToken1(): void
    {
        $request = self::createRequest("POST");

        $request->shouldReceive("hasFormField")
            ->with("_token")
            ->once()
            ->andReturn(true);

        $request->shouldReceive("formField")
            ->with("_token")
            ->once()
            ->andReturn("the-test-token");

        $request->shouldNotReceive("header");

        $processor = new XRay($this->processor);
        self::assertEquals("the-test-token", $processor->retrieveCsrfToken($request));
    }

    /** Ensure CRSF token is taken from request header if _token is not in POST data. */
    public function testRetrieveCsrfToken2(): void
    {
        $request = self::createRequest("POST");

        $request->shouldReceive("hasFormField")
            ->with("_token")
            ->once()
            ->ordered()
            ->andReturn(false);

        $request->shouldReceive("header")
            ->with("X-CSRF-TOKEN")
            ->once()
            ->ordered()
            ->andReturn([new Header("X-CSRF-TOKEN", "the-header-test-token")]);

        $processor = new XRay($this->processor);
        self::assertEquals("the-header-test-token", $processor->retrieveCsrfToken($request));
    }

    /** Ensure CRSF token is null if not in the POST data or headers. */
    public function testRetrieveCsrfToken3(): void
    {
        $request = self::createRequest("POST");

        $request->shouldReceive("hasFormField")
            ->with("_token")
            ->once()
            ->ordered()
            ->andReturn(false);

        $request->shouldReceive("header")
            ->with("X-CSRF-TOKEN")
            ->once()
            ->ordered()
            ->andReturn([]);

        $processor = new XRay($this->processor);
        self::assertNull($processor->retrieveCsrfToken($request));
    }

    public static function dataForTestPreprocessRequest1(): iterable
    {
        foreach (self::dataForTestRequiresCsrf1() as $key => $args) {
            [$originalRequest, $requiresCsrf,] = $args;
            $request = clone $originalRequest;

            if (!$requiresCsrf) {
                $request->shouldNotReceive("hasFormField");
                $request->shouldNotReceive("header");
                yield "{$key}" => [$request, $requiresCsrf, false, "the-test-token", true,];
                continue;
            }

            $request->shouldReceive("hasFormField")
                ->with("_token")
                ->once()
                ->ordered()
                ->andReturn(true);

            $request->shouldReceive("formField")
                ->with("_token")
                ->once()
                ->ordered()
                ->andReturn("the-test-token");

            $request->shouldNotReceive("header");
            yield "{$key} token in POST" => [$request, $requiresCsrf, true, "the-test-token", true,];

            $request = clone $args[0];

            $request->shouldReceive("hasFormField")
                ->with("_token")
                ->once()
                ->ordered()
                ->andReturn(false);

            $request->shouldReceive("header")
                ->with("X-CSRF-TOKEN")
                ->once()
                ->ordered()
                ->andReturn([new Header("X-CSRF-TOKEN", "the-header-test-token")]);

            yield "{$key} token in header" => [$request, $requiresCsrf, true, "the-header-test-token", true,];

            $request = clone $args[0];

            $request->shouldReceive("hasFormField")
                ->with("_token")
                ->once()
                ->ordered()
                ->andReturn(false);

            $request->shouldReceive("header")
                ->with("X-CSRF-TOKEN")
                ->once()
                ->ordered()
                ->andReturn([]);

            yield "{$key} no token" => [$request, $requiresCsrf, false, "the-non-existent-test-token", false,];

            $request = clone $args[0];

            $request->shouldReceive("hasFormField")
                ->with("_token")
                ->once()
                ->ordered()
                ->andReturn(true);

            $request->shouldReceive("formField")
                ->with("_token")
                ->once()
                ->ordered()
                ->andReturn("the-wrong-test-token");

            $request->shouldNotReceive("header");

            yield "{$key} wrong POST token" => [$request, $requiresCsrf, true, "the-test-token", false,];

            $request = clone $args[0];

            $request->shouldReceive("hasFormField")
                ->with("_token")
                ->once()
                ->ordered()
                ->andReturn(false);

            $request->shouldReceive("header")
                ->with("X-CSRF-TOKEN")
                ->once()
                ->ordered()
                ->andReturn([new Header("X-CSRF-TOKEN", "the wrong-header-test-token")]);

            yield "{$key} wrong header token" => [$request, $requiresCsrf, true, "the-test-token", false,];
        }
    }

    /**
     * @dataProvider dataForTestPreprocessRequest1
     *
     * @param RequestContract $request The request to test with
     * @param bool $verificationRequired Whether verification should be detected as required.
     * @param string $csrf
     * @param bool $expected
     */
    public function testPreprocessRequest1(RequestContract $request, bool $verificationRequired, bool $requestHasToken, string $csrf, bool $expected): void
    {
        if ($verificationRequired && $requestHasToken) {
            $app = Mockery::mock(WebApplication::class);
            $app->shouldReceive("csrf")->once()->andReturn($csrf);
            $this->mockMethod(CoreApplication::class, "instance", $app);
        }

        if (!$expected) {
            self::expectException(CsrfTokenVerificationException::class);
            self::expectExceptionMessage("The CSRF token is missing from the request or is invalid.");
        } else {
            self::markTestAsExternallyVerified();
        }

        $this->processor->preprocessRequest($request);
    }
}
