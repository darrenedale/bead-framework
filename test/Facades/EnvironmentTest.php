<?php

declare(strict_types=1);

namespace BeadTests\Facades;

use BadMethodCallException;
use Bead\Application;
use Bead\Contracts\Environment as EnvironmentContract;
use Bead\Environment\Environment;
use Bead\Environment\Providers\StaticArray;
use Bead\Facades\Environment as EnvironmentFacade;
use LogicException;
use BeadTests\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    /** @var Application The app that provides the facade with access to the environment. */
    private Application $app;

    public function setUp(): void
    {
        $this->app = new class extends Application
        {
            public function __construct()
            {
                self::$s_instance = $this;
                $environment = new Environment();
                $environment->addProvider(new StaticArray([
                    "KEY_1" => "value-1",
                ]));

                $this->bindService(EnvironmentContract::class, $environment);
            }

            public function exec(): int
            {
                return 0;
            }
        };
    }

    public function tearDown(): void
    {
        unset ($this->app);
        parent::tearDown();
    }

    /** Ensure the facade's has() method returns the expected results. */
    public function testHas()
    {
        self::assertTrue(EnvironmentFacade::has("KEY_1"));
        self::assertFalse(EnvironmentFacade::has("KEY_2"));
    }

    /** Ensure the facade's get() method provides the expected values. */
    public function testGet()
    {
        self::assertEquals("value-1", EnvironmentFacade::get("KEY_1"));
        self::assertEquals("", EnvironmentFacade::get("KEY_2"));
    }

    /** Ensure facade throws a BadMethodCall exception with methods that don't exist. */
    public function testMissingMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::expectExceptionMessage("The method 'nonExistentMethod' does not exist on the instance bound to " . EnvironmentContract::class . ".");
        EnvironmentFacade::nonExistentMethod();
    }

    /** Ensure facade throws when no environment is bound into the app. */
    public function testNoEnvironment(): void
    {
        $this->app->replaceService(EnvironmentContract::class, null);
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Application environment has not been set up.");
        EnvironmentFacade::has("KEY_1");
    }
}
