<?php

namespace BeadTests;

use Bead\Application;
use Bead\Exceptions\ServiceAlreadyBoundException;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    private Application $m_app;

    public function setUp(): void
    {
        $this->m_app = new class() extends Application
        {
            public function __construct()
            {
            }

            public function exec(): int
            {
                return self::ExitOk;
            }
        };
    }

    public function tearDown(): void
    {
        unset($this->m_app);
    }

    public function testReplaceService(): void
    {
        $original = (object) ["foo" => "bar",];
        $replacement = (object) ["fox" => "bax",];
        $this->m_app->bindService("foo", $original);

        $this->assertSame($original, $this->m_app->service("foo"));
        $acutal = $this->m_app->replaceService("foo", $replacement);
        $this->assertSame($original, $acutal);
        $this->assertSame($replacement, $this->m_app->service("foo"));
    }

    public function testBindService(): void
    {
        $original = (object) ["foo" => "bar",];
        $replacement = (object) ["fox" => "bax",];
        $this->assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        $this->assertTrue($this->m_app->serviceIsBound("foo"));
        $this->assertSame($original, $this->m_app->service("foo"));

        $this->expectException(ServiceAlreadyBoundException::class);
        $this->m_app->bindService("foo", $replacement);
    }

    public function testService(): void
    {
        $original = (object) ["foo" => "bar",];
        $this->assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        $this->assertTrue($this->m_app->serviceIsBound("foo"));
        $this->assertSame($original, $this->m_app->service("foo"));
    }

    public function testServiceIsBound(): void
    {
        $original = (object) ["foo" => "bar",];
        $this->assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        $this->assertTrue($this->m_app->serviceIsBound("foo"));
        $this->assertFalse($this->m_app->serviceIsBound("fox"));
    }

    public function testGet(): void
    {
        $original = (object) ["foo" => "bar",];
        $this->assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        $this->assertTrue($this->m_app->serviceIsBound("foo"));
        $this->assertSame($original, $this->m_app->get("foo"));
    }

    public function testHas(): void
    {
        $original = (object) ["foo" => "bar",];
        $this->assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        $this->assertTrue($this->m_app->has("foo"));
        $this->assertFalse($this->m_app->has("fox"));
    }
}
