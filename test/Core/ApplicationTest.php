<?php

namespace BeadTests\Core;

use Bead\Core\Application;
use Bead\Exceptions\ServiceAlreadyBoundException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function is_array;

class ApplicationTest extends TestCase
{
    private const TestConfig = [
        "mail" => [
            "transport" => "mailgun",
            "transports" => [
                "php" => [
                    "driver" => "php",
                ],

                "mailgun" => [
                    "driver" => "mailgun",
                    "endpoint" => "https://api.eu.mailgun.net",
                    "key" => "some key",
                ],

                "fake" => [
                    "driver" => "fake",
                    "values" => [
                        "embedded" => true,
                        "descend" => [
                            "key" => "value",
                        ],
                    ],
                ],
            ],
        ],
        "dotted" => [
            "with" => [
                "dot" => "not this",
            ],
            "with.dot" => "but this",
        ],
    ];

    private Application $m_app;

    public function setUp(): void
    {
        $this->m_app = new class extends Application
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

        self::assertSame($original, $this->m_app->service("foo"));
        $acutal = $this->m_app->replaceService("foo", $replacement);
        self::assertSame($original, $acutal);
        self::assertSame($replacement, $this->m_app->service("foo"));
    }

    public function testBindService(): void
    {
        $original = (object) ["foo" => "bar",];
        $replacement = (object) ["fox" => "bax",];
        self::assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        self::assertTrue($this->m_app->serviceIsBound("foo"));
        self::assertSame($original, $this->m_app->service("foo"));

        $this->expectException(ServiceAlreadyBoundException::class);
        $this->m_app->bindService("foo", $replacement);
    }

    public function testService(): void
    {
        $original = (object) ["foo" => "bar",];
        self::assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        self::assertTrue($this->m_app->serviceIsBound("foo"));
        self::assertSame($original, $this->m_app->service("foo"));
    }

    public function testServiceIsBound(): void
    {
        $original = (object) ["foo" => "bar",];
        self::assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        self::assertTrue($this->m_app->serviceIsBound("foo"));
        self::assertFalse($this->m_app->serviceIsBound("fox"));
    }

    public function testGet(): void
    {
        $original = (object) ["foo" => "bar",];
        self::assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        self::assertTrue($this->m_app->serviceIsBound("foo"));
        self::assertSame($original, $this->m_app->get("foo"));
    }

    public function testHas(): void
    {
        $original = (object) ["foo" => "bar",];
        self::assertFalse($this->m_app->serviceIsBound("foo"));
        $this->m_app->bindService("foo", $original);
        self::assertTrue($this->m_app->has("foo"));
        self::assertFalse($this->m_app->has("fox"));
    }

    public static function dataForTestConfig1(): iterable
    {
        yield "whole-file-config" => ["mail", null, self::TestConfig["mail"],];
        yield "non-existent-file" => ["foo", null, null,];
        yield "non-existent-file-default" => ["foo", "bar", "bar",];
        yield "top-level-from-file" => ["mail.transport", null, "mailgun",];
        yield "nested-in-file" => ["mail.transports.mailgun.key", null, "some key",];
        yield "nested-doesn't-exist" => ["mail.transports.fake.key", null, null,];
        yield "nested-doesn't-exist-default" => ["mail.transports.fake.key", "foo", "foo",];
        yield "nested-array" => ["mail.transports.fake.values", null, self::TestConfig["mail"]["transports"]["fake"]["values"],];
        yield "prefers-actual-key-to-array-descent" => ["dotted.with.dot", null, "but this",];
    }

    /**
     * Ensure the config is traversed correctly.
     *
     * @dataProvider dataForTestConfig1
     */
    public function testConfig1(string $key, mixed $default, mixed $expected): void
    {
        $config = new ReflectionProperty(Application::class, "m_config");
        $config->setAccessible(true);
        $config->setValue($this->m_app, self::TestConfig);

        if (null === $default) {
            $actual = $this->m_app->config($key);
        } else {
            $actual = $this->m_app->config($key, $default);
        }

        if (is_array($expected)) {
            self::assertEqualsCanonicalizing($expected, $actual);
        } else {
            self::assertEquals($expected, $actual);
        }
    }
}
