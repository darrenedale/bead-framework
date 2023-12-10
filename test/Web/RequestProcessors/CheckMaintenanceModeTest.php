<?php

declare(strict_types=1);

namespace BeadTests\Web\RequestProcessors;

use Bead\Core\Application as CoreApplication;
use Bead\Web\Application as WebApplication;
use Bead\Exceptions\Http\ServiceUnavailableException;
use Bead\Testing\XRay;
use Bead\View;
use Bead\Web\Request;
use Bead\Web\RequestProcessors\CheckMaintenanceMode;
use Mockery;
use BeadTests\Framework\TestCase;

class CheckMaintenanceModeTest extends TestCase
{
    private CheckMaintenanceMode $processor;

    public function setUp(): void
    {
        $this->processor = new CheckMaintenanceMode();
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->processor);
        parent::tearDown();
    }

    public function testViewName1(): void
    {
        $processor = new XRay($this->processor);
        self::assertEquals("system.maintenance-mode", $processor->viewName());
    }

    public static function dataForTestIsInMaintenanceMode1(): iterable
    {
        yield "bool true" => [true, true,];
        yield "bool false" => [false, false,];
        yield "int 1" => [1, true,];
        yield "int -1" => [-1, true,];
        yield "int 99" => [99, true,];
        yield "int -99" => [-99, true,];
        yield "int max" => [PHP_INT_MAX, true,];
        yield "int min" => [PHP_INT_MIN, true,];
        yield "int 0" => [0, false,];
        yield "float 0.5" => [0.5, true,];
        yield "float -0.5" => [-0.5, true,];
        yield "float 99.5" => [99.5, true,];
        yield "float -99.5" => [-99.5, true,];
        yield "float max" => [PHP_FLOAT_MAX, true,];
        yield "float min" => [PHP_FLOAT_MIN, true,];
        yield "float 0.0" => [0.0, false,];
        yield "string true" => ["true", true,];
        yield "string false" => ["false", true,];
        yield "string empty" => ["", false,];
        yield "string 1" => ["1", true,];
        yield "string -1" => ["-1", true,];
        yield "string 99" => ["99", true,];
        yield "string -99" => ["-99", true,];
        yield "string 0" => ["0", false,];
        yield "null" => [null, false,];
    }

    /**
     * @dataProvider dataForTestIsInMaintenanceMode1
     * @param string $config The value for the config item app.maintenance
     * @param bool $expected Whether the preprocessor should report that the app is in maintenance mode.
     */
    public function testIsInMaintenanceMode1(mixed $config, bool $expected): void
    {
        $app = Mockery::mock(WebApplication::class);
        $this->mockMethod(CoreApplication::class, "instance", $app);

        $app->shouldReceive("config")
            ->with("app.maintenance", false)
            ->once()
            ->andReturn($config);

        $processor = new XRay($this->processor);
        self::assertEquals($expected, $processor->isInMaintenanceMode());
    }

    /** Ensure preprocessRequest() returns null when app is not in maintenance mode. */
    public function testPreprocessRequest1(): void
    {
        $request = Mockery::mock(Request::class);
        $app = Mockery::mock(WebApplication::class);
        $this->mockMethod(CoreApplication::class, "instance", $app);

        $app->shouldReceive("config")
            ->with("app.maintenance", false)
            ->once()
            ->andReturn(false);

        self::assertNull($this->processor->preprocessRequest($request));
    }

    /** Ensure preprocessRequest() uses the correct view when in maintenance mode. */
    public function testPreprocessRequest2(): void
    {
        $request = Mockery::mock(Request::class);
        $app = Mockery::mock(WebApplication::class);
        $this->mockMethod(CoreApplication::class, "instance", $app);

        $app->shouldReceive("rootDir")->once()->andReturn(__DIR__ . "/files");

        $app->shouldReceive("config")
            ->with("app.maintenance", false)
            ->once()
            ->andReturn(true);

        $app->shouldReceive("config")
            ->with("view.directory", "views")
            ->once()
            ->andReturn("views");

        $actual = $this->processor->preprocessRequest($request);
        self::assertInstanceOf(View::class, $actual);
        self::assertStringContainsString("<p>This is the maintenance mode view.</p>", $actual->content());
    }

    /** Ensure preprocessRequest() throws ServiceUnavailableException when in maintenance mode and viewName() is null. */
    public function testPreprocessRequest3(): void
    {
        $this->mockMethod(CheckMaintenanceMode::class, "viewName", null);
        $request = Mockery::mock(Request::class);
        $app = Mockery::mock(WebApplication::class);
        $this->mockMethod(CoreApplication::class, "instance", $app);

        $app->shouldReceive("config")
            ->with("app.maintenance", false)
            ->once()
            ->andReturn(true);

        self::expectException(ServiceUnavailableException::class);
        self::expectExceptionMessage("Application is currently down for maintenance");
        $actual = $this->processor->preprocessRequest($request);
    }

    /**
     * Ensure preprocessRequest() throws ServiceUnavailableException when in maintenance mode and the view does not
     * exist.
     */
    public function testPreprocessRequest4(): void
    {
        $this->mockMethod(CheckMaintenanceMode::class, "viewName", "this-view-does-not-exist");
        $request = Mockery::mock(Request::class);
        $app = Mockery::mock(WebApplication::class);
        $this->mockMethod(CoreApplication::class, "instance", $app);

        $app->shouldReceive("rootDir")->once()->andReturn(__DIR__ . "/files");

        $app->shouldReceive("config")
            ->with("app.maintenance", false)
            ->once()
            ->andReturn(true);

        $app->shouldReceive("config")
            ->with("view.directory", "views")
            ->once()
            ->andReturn("views");

        self::expectException(ServiceUnavailableException::class);
        self::expectExceptionMessage("Application is currently down for maintenance");
        $actual = $this->processor->preprocessRequest($request);
        self::assertInstanceOf(View::class, $actual);
        self::assertStringContainsString("<p>This is the maintenance mode view.</p>", $actual->content());
    }
}
