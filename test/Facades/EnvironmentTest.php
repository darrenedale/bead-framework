<?php

declare(strict_types=1);

namespace BeadTests\Facades;

use Bead\Contracts\Environment as EnvironmentContract;
use Bead\Core\Application;
use Bead\Environment\Environment;
use Bead\Environment\Sources\StaticArray;
use Bead\Facades\Environment as EnvironmentFacade;
use BeadTests\Framework\TestCase;
use Error;
use LogicException;
use Mockery;
use Mockery\MockInterface;

final class EnvironmentTest extends TestCase
{
    /** @var Application&MockInterface The app that provides the facade with access to the environment. */
    private Application $app;

    public function setUp(): void
    {
        $environment = new Environment();
        $environment->addSource(new StaticArray([
            "KEY_1" => "value-1",
        ]));

        $this->app = Mockery::mock(Application::class);
        $this->app->shouldReceive("get")
            ->with(EnvironmentContract::class)
            ->andReturn($environment);

        $this->mockMethod(Application::class, "instance", $this->app);
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset ($this->app);
        parent::tearDown();
    }

    /** Ensure the facade's has() method returns the expected results. */
    public function testHas1(): void
    {
        self::assertTrue(EnvironmentFacade::has("KEY_1"));
        self::assertFalse(EnvironmentFacade::has("KEY_2"));
    }

    /** Ensure the facade's get() method provides the expected values. */
    public function testGet1(): void
    {
        self::assertEquals("value-1", EnvironmentFacade::get("KEY_1"));
        self::assertEquals("", EnvironmentFacade::get("KEY_2"));
    }
}
