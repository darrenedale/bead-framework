<?php

declare(strict_types=1);

namespace BeadTests\Web;

use Bead\Facades\Session;
use Bead\Testing\XRay;
use Bead\Web\Application as WebApplication;
use BeadTests\Framework\TestCase;
use ReflectionProperty;

class ApplicationTest extends TestCase
{
    public function setUp(): void
    {
        $this->mockFunction("setcookie", true);
        $this->mockMethod(WebApplication::class, "csrf", "csrf-token");
    }

    public function tearDown(): void
    {
        $instance = new ReflectionProperty(WebApplication::class, "s_instance");
        $instance->setAccessible(true);
        $instance->setValue(null);

        $session = new ReflectionProperty(Session::class, "session");
        $session->setAccessible(true);
        $session->setValue(null);

        parent::tearDown();
    }

    private static function makeTestWebApplication(): WebApplication
    {
        return new class extends WebApplication {
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
}
