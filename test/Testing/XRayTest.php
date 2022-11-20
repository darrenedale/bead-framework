<?php

namespace BeadTests\Testing;

use BadMethodCallException;
use BeadTests\Framework\CallTracker;
use BeadTests\Framework\TestCase;
use Bead\Testing\XRay;
use LogicException;

class XRayTest extends TestCase
{
    public const StringArg = "hitch-hiker";
    public const IntArg = 42;

    private XRay $m_xRay;
    private CallTracker $m_tracker;

    public function setUp(): void
    {
        $this->m_tracker = new CallTracker();

        $testObject = new class($this->m_tracker)
        {
            private static CallTracker $m_tracker;
            private static string $m_privateStaticProperty = "private-static-property";
            public static string $publicStaticProperty = "public-static-property";

            private string $m_privateProperty = "private-property";
            public string $publicProperty = "public-property";

            private array $m_magicProperties = [
                "m_magicProperty" => "magic-property",
            ];

            public function __construct(CallTracker $tracker)
            {
                self::$m_tracker = $tracker;
            }

            private static function privateStaticMethod(): string
            {
                XRayTest::fail("privateStaticMethod() should not be called.");
            }

            public static function publicStaticMethod(): string
            {
                XRayTest::fail("publicStaticMethod() should not be called.");
            }

            private static function privateStaticMethodWithArgs(string $arg1, int $arg2): string
            {
                XRayTest::fail("privateStaticMethodWithArgs() should not be called.");
            }

            public static function publicStaticMethodWithArgs(string $arg1, int $arg2): string
            {
                XRayTest::fail("publicStaticMethodWithArgs() should not be called.");
            }

            private function privateMethod(): string
            {
                self::$m_tracker->increment();
                return "private-method";
            }

            public function publicMethod(): string
            {
                self::$m_tracker->increment();
                return "public-method";
            }

            private function privateMethodWithArgs(string $arg1, int $arg2): string
            {
                self::$m_tracker->increment();
                XRayTest::assertEquals(XRayTest::StringArg, $arg1);
                XRayTest::assertEquals(XRayTest::IntArg, $arg2);
                return "{$arg1} {$arg2}";
            }

            public function publicMethodWithArgs(string $arg1, int $arg2): string
            {
                self::$m_tracker->increment();
                XRayTest::assertEquals(XRayTest::StringArg, $arg1);
                XRayTest::assertEquals(XRayTest::IntArg, $arg2);
                return "{$arg1} {$arg2}";
            }

            public function __get(string $property): string
            {
                if ("m_magicProperty" === $property) {
                    return $this->m_magicProperties[$property];
                }

                throw new LogicException("Property {$property} does not exist.");
            }

            public function __set(string $property, string $value): void
            {
                if ("m_magicProperty" === $property) {
                    $this->m_magicProperties[$property] = $value;
                    return;
                }

                throw new LogicException("Property {$property} does not exist.");
            }

            public function __call(string $method, array $args): string
            {
                if ("magicMethod" === $method) {
                    self::$m_tracker->increment();
                    return "magic-method";
                }

                if ("magicMethodWithArgs" === $method) {
                    self::$m_tracker->increment();
                    return "{$args[0]} {$args[1]}";
                }

                throw new BadMethodCallException("Non-existent magic method.");
            }

            public static function __callStatic(string $method, array $args): void
            {
                XRayTest::fail("__callStatic() should not be called.");
            }
        };

        $this->m_xRay = new XRay($testObject);
    }

    public function testPublicProperty(): void
    {
        self::assertTrue($this->m_xRay->isPublicProperty("publicProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("publicProperty"));
        self::assertEquals("public-property", $this->m_xRay->publicProperty);
    }

    public function testSetPublicProperty(): void
    {
        self::assertTrue($this->m_xRay->isPublicProperty("publicProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("publicProperty"));
        self::assertEquals("public-property", $this->m_xRay->publicProperty);
        $this->m_xRay->publicProperty = self::StringArg;
        self::assertEquals(self::StringArg, $this->m_xRay->publicProperty);
    }

    public function testXRayedProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicProperty("m_privateProperty"));
        self::assertTrue($this->m_xRay->isXRayedProperty("m_privateProperty"));
        self::assertEquals("private-property", $this->m_xRay->m_privateProperty);
    }

    public function testSetXRayedProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicProperty("m_privateProperty"));
        self::assertTrue($this->m_xRay->isXRayedProperty("m_privateProperty"));
        self::assertEquals("private-property", $this->m_xRay->m_privateProperty);
        $this->m_xRay->m_privateProperty = self::StringArg;
        self::assertEquals(self::StringArg, $this->m_xRay->m_privateProperty);
    }

    public function testMagicProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicProperty("m_magicProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("m_magicProperty"));
        self::assertEquals("magic-property", $this->m_xRay->m_magicProperty);
    }

    public function testSetMagicProperty(): void
    {
        self::assertFalse($this->m_xRay->isPublicProperty("m_magicProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("m_magicProperty"));
        self::assertEquals("magic-property", $this->m_xRay->m_magicProperty);
        $this->m_xRay->m_magicProperty = self::StringArg;
        self::assertEquals(self::StringArg, $this->m_xRay->m_magicProperty);
    }

    public function testPublicStaticProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicProperty("publicStaticProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("publicStaticProperty"));
        self::assertEquals("", $this->m_xRay->publicStaticProperty);
    }

    public function testPrivateStaticProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicProperty("m_privateStaticProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("m_privateStaticProperty"));
        self::assertEquals("", $this->m_xRay->m_privateStaticProperty);
    }

    public function testNonExistentProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicProperty("nonExistentProperty"));
        self::assertFalse($this->m_xRay->isXRayedProperty("nonExistentProperty"));
        self::assertEquals("", $this->m_xRay->nonExistentProperty);
    }

    public function testEmptyProperty(): void
    {
        self::expectException(LogicException::class);
        self::assertFalse($this->m_xRay->isPublicProperty(""));
        self::assertFalse($this->m_xRay->isXRayedProperty(""));
        self::assertEquals("", $this->m_xRay->{""});
    }

    public function testPublicMethod(): void
    {
        $this->m_tracker->reset();
        self::assertTrue($this->m_xRay->isPublicMethod("publicMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("publicMethod"));
        self::assertEquals("public-method", $this->m_xRay->publicMethod());
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    public function testPublicMethodWithArgs(): void
    {
        self::assertTrue($this->m_xRay->isPublicMethod("publicMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("publicMethodWithArgs"));
        $this->m_tracker->reset();
        $expected = self::StringArg . " " . self::IntArg;
        self::assertEquals($expected, $this->m_xRay->publicMethodWithArgs(self::StringArg, self::IntArg));
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    public function testXRayedMethod(): void
    {
        $this->m_tracker->reset();
        self::assertFalse($this->m_xRay->isPublicMethod("privateMethod"));
        self::assertTrue($this->m_xRay->isXRayedMethod("privateMethod"));
        self::assertEquals("private-method", $this->m_xRay->privateMethod());
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    public function testXRayedMethodWithArgs(): void
    {
        self::assertFalse($this->m_xRay->isPublicMethod("privateMethodWithArgs"));
        self::assertTrue($this->m_xRay->isXRayedMethod("privateMethodWithArgs"));
        $this->m_tracker->reset();
        $expected = self::StringArg . " " . self::IntArg;
        self::assertEquals($expected, $this->m_xRay->privateMethodWithArgs(self::StringArg, self::IntArg));
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    public function testMagicMethod(): void
    {
        $this->m_tracker->reset();
        self::assertFalse($this->m_xRay->isPublicMethod("magicMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("magicMethod"));
        self::assertEquals("magic-method", $this->m_xRay->magicMethod());
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    public function testMagicMethodWithArgs(): void
    {
        $this->m_tracker->reset();
        self::assertFalse($this->m_xRay->isPublicMethod("magicMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("magicMethodWithArgs"));
        self::assertEquals(self::StringArg . " " . self::IntArg, $this->m_xRay->magicMethodWithArgs(self::StringArg, self::IntArg));
        self::assertEquals(1, $this->m_tracker->callCount());
    }

    public function testPublicStaticMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("publicStaticMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("publicStaticMethod"));
        self::assertEquals("", $this->m_xRay->publicStaticMethod());
    }

    public function testPublicStaticMethodWithArgs(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("publicStaticMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("publicStaticMethodWithArgs"));
        self::assertEquals("", $this->m_xRay->publicStaticMethodWithArgs(self::StringArg, self::IntArg));
    }

    public function testPrivateStaticMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("privateStaticMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("privateStaticMethod"));
        self::assertEquals("", $this->m_xRay->privateStaticMethod());
    }

    public function testPrivateStaticMethodWithArgs(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("privateStaticMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("privateStaticMethodWithArgs"));
        self::assertEquals("", $this->m_xRay->privateStaticMethodWithArgs());
    }

    public function testStaticMagicMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("staticMagicMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("staticMagicMethod"));
        self::assertEquals("", $this->m_xRay->staticMagicMethod());
    }

    public function testStaticMagicMethodWithArgs(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("staticMagicMethodWithArgs"));
        self::assertFalse($this->m_xRay->isXRayedMethod("staticMagicMethodWithArgs"));
        self::assertEquals("", $this->m_xRay->staticMagicMethodWithArgs(self::StringArg, self::IntArg));
    }

    public function testNonExistentMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod("nonExistentMethod"));
        self::assertFalse($this->m_xRay->isXRayedMethod("nonExistentMethod"));
        self::assertEquals("", $this->m_xRay->nonExistentMethod());
    }

    public function testEmptyMethod(): void
    {
        self::expectException(BadMethodCallException::class);
        self::assertFalse($this->m_xRay->isPublicMethod(""));
        self::assertFalse($this->m_xRay->isXRayedMethod(""));
        self::assertEquals("", $this->m_xRay->{""}());
    }
}
