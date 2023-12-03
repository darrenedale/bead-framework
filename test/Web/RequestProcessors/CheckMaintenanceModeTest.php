<?php
declare(strict_types=1);

namespace BeadTests\Web\RequestProcessors;

use Bead\Core\Application;
use Bead\Testing\XRay;
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
        $app = Mockery::mock(Application::class);

        $app->shouldReceive("config")
            ->with("app.maintenance", false)
            ->once()
            ->andReturn($config);

        $this->mockMethod(Application::class, "instance", $app);
        $processor = new XRay($this->processor);
        self::assertEquals($expected, $processor->isInMaintenanceMode());
    }

    /** Ensure isInMaintenanceMode() returns false when the config is not set.*/
    public function testIsInMaintenanceMode2(): void
    {
        $app = Mockery::mock(Application::class);

        $app->shouldReceive("config")
            ->with("app.maintenance", false)
            ->once()
            ->andReturn(null);

        $this->mockMethod(Application::class, "instance", $app);
        $processor = new XRay($this->processor);
        self::assertFalse($processor->isInMaintenanceMode());
    }
}
