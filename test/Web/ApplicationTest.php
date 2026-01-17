<?php

declare(strict_types=1);

namespace BeadTests\Web;

use Bead\Contracts\Web\Request as RequestContract;
use Bead\Contracts\Web\RequestPostprocessor;
use Bead\Contracts\Web\RequestPreprocessor;
use Bead\Contracts\Web\Response as ResponseContract;
use Bead\Contracts\Web\Router as RouterContract;
use Bead\Exceptions\Http\NotFoundException;
use Bead\Exceptions\UnroutableRequestException;
use Bead\Facades\Session;
use Bead\Core\Application as CoreApplication;
use Bead\Web\Application as WebApplication;
use BeadTests\Framework\TestCase;
use Equit\XRay\StaticXRay;
use Equit\XRay\XRay;
use Mockery;

class ApplicationTest extends TestCase
{
    public function setUp(): void
    {
        $this->mockFunction("setcookie", true);
        $this->mockMethod(WebApplication::class, "csrf", "csrf-token");
    }

    public function tearDown(): void
    {
        $xRay = new StaticXRay(CoreApplication::class);
        $xRay->s_instance = null;

        $xRay = new StaticXRay(Session::class);
        $xRay->session = null;

        parent::tearDown();
    }

    private static function makeTestWebApplication(): WebApplication
    {
        return new class extends WebApplication {
            /** @noinspection PhpMissingParentConstructorInspection we're creating a test double, we don't want to call the parent constructor. */
            public function __construct()
            {
            }
        };
    }

    /** Ensure plugins are not loaded when the config has disabled them. */
    public function testLoadPlugins1(): void
    {
        $loadPluginCalled = false;

        $this->mockMethod(WebApplication::class, "loadPlugin", function () use (&$loadPluginCalled): void {
            $loadPluginCalled = true;
            TestCase::fail("loadPlugin() should not be called.");
        });

        $app = new XRay($this->makeTestWebApplication());
        $app->loadPlugins();
        self::assertFalse($loadPluginCalled);
    }

    /** Ensure plugins are not loaded by default. */
    public function testLoadPlugins2(): void
    {
        $loadPluginCalled = false;

        $this->mockMethod(WebApplication::class, "loadPlugin", function () use (&$loadPluginCalled): void {
            $loadPluginCalled = true;
            TestCase::fail("loadPlugin() should not be called.");
        });

        $app = new XRay($this->makeTestWebApplication());
        $app->loadPlugins();
        self::assertFalse($loadPluginCalled);
    }

    /** Ensure handleRequest() emits the expected events. */
    public function testHandleRequest1(): void
    {
        $expectedEvents = [
            "application.handlerequest.requestreceived",
            "application.handlerequest.preprocessing",
            "application.handlerequest.preprocessed",
            "application.handlerequest.routing",
            "application.handlerequest.routed",
            "application.handlerequest.postprocessing",
            "application.handlerequest.postprocessed",
        ];

        $actualEvents = [];
        $app = self::makeTestWebApplication();
        $router = Mockery::mock(RouterContract::class);
        $expectedRequest = Mockery::mock(RequestContract::class);
        $expectedResponse = Mockery::mock(ResponseContract::class);
        $router->expects("route")->with($expectedRequest)->andReturn($expectedResponse);
        $app->setRouter($router);

        $handler = static function (string $event, RequestContract $request) use (&$actualEvents, $expectedRequest): void {
            ApplicationTest::assertSame($expectedRequest, $request);
            $actualEvents[] = $event;
        };

        foreach ($expectedEvents as $event) {
            $app->connect($event, $handler);
        }

        $app->handleRequest($expectedRequest);
        self::assertSame($expectedEvents, $actualEvents);
    }

    /** Ensure handleRequest() routes the request and returns the response from the router. */
    public function testHandleRequest2(): void
    {
        $app = self::makeTestWebApplication();
        $router = Mockery::mock(RouterContract::class);
        $expectedRequest = Mockery::mock(RequestContract::class);
        $expectedResponse = Mockery::mock(ResponseContract::class);
        $router->expects("route")->with($expectedRequest)->andReturn($expectedResponse);
        $app->setRouter($router);

        $actualReponse = $app->handleRequest($expectedRequest);
        self::assertSame($expectedResponse, $actualReponse);
    }

    /** Ensure handleRequest() returns the response from a pre-processor. */
    public function testHandleRequest3(): void
    {
        $app = self::makeTestWebApplication();
        $router = Mockery::mock(RouterContract::class);
        $expectedRequest = Mockery::mock(RequestContract::class);
        $expectedResponse = Mockery::mock(ResponseContract::class);
        $postProcessor = Mockery::mock(RequestPreprocessor::class);
        $router->shouldNotReceive("route");
        $app->setRouter($router);

        $postProcessor->expects("preprocessRequest")
            ->with($expectedRequest)
            ->andReturn($expectedResponse);

        $app = new XRay($app);
        $app->m_requestProcessors = [$postProcessor];

        $actualReponse = $app->handleRequest($expectedRequest);
        self::assertSame($expectedResponse, $actualReponse);
    }

    /** Ensure handleRequest() returns the response from the router if a pre-processor doesn't return a response. */
    public function testHandleRequest4(): void
    {
        $app = self::makeTestWebApplication();
        $router = Mockery::mock(RouterContract::class);
        $expectedRequest = Mockery::mock(RequestContract::class);
        $expectedResponse = Mockery::mock(ResponseContract::class);
        $preProcessor = Mockery::mock(RequestPreprocessor::class);
        $app->setRouter($router);

        $preProcessor->expects("preprocessRequest")
            ->with($expectedRequest)
            ->andReturn(null);

        $router->expects("route")
            ->with($expectedRequest)
            ->andReturn($expectedResponse);

        $app = new XRay($app);
        $app->m_requestProcessors = [$preProcessor];

        $actualReponse = $app->handleRequest($expectedRequest);
        self::assertSame($expectedResponse, $actualReponse);
    }

    /** Ensure handleRequest() returns the response from a post-processor. */
    public function testHandleRequest5(): void
    {
        $app = self::makeTestWebApplication();
        $router = Mockery::mock(RouterContract::class);
        $expectedRequest = Mockery::mock(RequestContract::class);
        $routedResponse = Mockery::mock(ResponseContract::class);
        $postProcessedResponse = Mockery::mock(ResponseContract::class);
        $postProcessor = Mockery::mock(RequestPostprocessor::class);
        $app->setRouter($router);

        $router->expects("route")
            ->with($expectedRequest)
            ->andReturn($routedResponse);

        $postProcessor->expects("postProcessRequest")
            ->with($expectedRequest, $routedResponse)
            ->andReturn($postProcessedResponse);

        $app = new XRay($app);
        $app->m_requestProcessors = [$postProcessor];

        $actualReponse = $app->handleRequest($expectedRequest);
        self::assertSame($postProcessedResponse, $actualReponse);
    }

    /** Ensure handleRequest() returns the response from the router if a post-processor doesn't return a response. */
    public function testHandleRequest6(): void
    {
        $app = self::makeTestWebApplication();
        $router = Mockery::mock(RouterContract::class);
        $expectedRequest = Mockery::mock(RequestContract::class);
        $expectedResponse = Mockery::mock(ResponseContract::class);
        $postProcessor = Mockery::mock(RequestPostprocessor::class);
        $app->setRouter($router);

        $router->expects("route")
            ->with($expectedRequest)
            ->andReturn($expectedResponse);

        $postProcessor->expects("postprocessRequest")
            ->with($expectedRequest, $expectedResponse)
            ->andReturn(null);

        $app = new XRay($app);
        $app->m_requestProcessors = [$postProcessor];

        $actualReponse = $app->handleRequest($expectedRequest);
        self::assertSame($expectedResponse, $actualReponse);
    }

    /** Ensure handleRequest() throws the expected NotFoundException when the router throws. */
    public function testHandleRequest7(): void
    {
        $app = self::makeTestWebApplication();
        $router = Mockery::mock(RouterContract::class);
        $expectedRequest = Mockery::mock(RequestContract::class);
        $app->setRouter($router);

        $router->expects("route")
            ->with($expectedRequest)
            ->andThrow(new UnroutableRequestException($expectedRequest));

        $app = new XRay($app);

        self::expectException(NotFoundException::class);
        self::expectExceptionMessage("The requested page could not be found");
        $actualReponse = $app->handleRequest($expectedRequest);
    }
}
