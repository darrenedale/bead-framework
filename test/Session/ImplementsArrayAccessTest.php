<?php

namespace BeadTests\Session;

use Bead\Session\ImplementsArrayAccess;
use BeadTests\Framework\TestCase;
use StdClass;

/** @covers \Bead\Session\ImplementsArrayAccess */
class ImplementsArrayAccessTest extends TestCase
{
    private static function createArrayAccessor(array $data = []): object
    {
        return new class ($data)
        {
            use ImplementsArrayAccess;

            private array $data;

            private array $methodCalls = [];

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function has(string $key): bool
            {
                $this->methodCalls[] = "has";
                return array_key_exists($key, $this->data);
            }

            public function get(string $key): mixed
            {
                $this->methodCalls[] = "get";
                return $this->data[$key] ?? null;
            }

            public function set(string|array $keyOrData, mixed $data = null): void
            {
                $this->methodCalls[] = "set";

                if (is_string($keyOrData)) {
                    $this->data[$keyOrData] = $data;
                } else {
                    $this->data = array_merge($this->data, $keyOrData);
                }
            }

            public function remove(array|string $keys): void
            {
                $this->methodCalls[] = "remove";

                if (is_string($keys)) {
                    unset($this->data[$keys]);
                } else {
                    foreach ($keys as $key) {
                        unset($this->data[$key]);
                    }
                }
            }

            public function wasCalled(string $method): bool
            {
                return in_array($method, $this->methodCalls);
            }
        };
    }

    public static function providerNonStrings(): iterable
    {
        yield "int" => [42];
        yield "float" => [3.14];
        yield "bool" => [true];
        yield "array" => [["key"]];
        yield "object" => [new StdClass()];
        yield "null" => [null];
        yield "resource" => [fopen("php://memory", "r")];
    }

    /**
     * Ensure non-string offsets don't exist.
     *
     * @dataProvider providerNonStrings
     */
    public function testOffsetExists1(mixed $offset): void
    {
        self::assertFalse(self::createArrayAccessor()->offsetExists($offset));
    }

    /** Ensure a string offset that doesn't exist is correctly reported. */
    public function testOffsetExists2(): void
    {
        self::assertFalse(self::createArrayAccessor(["framework" => "bead",])->offsetExists("library"));
    }

    /** Ensure an offset that exists is correctly reported. */
    public function testOffsetExists3(): void
    {
        self::assertTrue(self::createArrayAccessor(["framework" => "bead",])->offsetExists("framework"));
    }

    /**
     * Ensure non-string offsets return null.
     *
     * @dataProvider providerNonStrings
     */
    public function testOffsetGet1(mixed $offset): void
    {
        self::assertNull(self::createArrayAccessor()->offsetGet($offset));
    }

    /** Ensure a string offset that doesn't exist returns null. */
    public function testOffsetGet2(): void
    {
        self::assertNull(self::createArrayAccessor(["framework" => "bead",])->offsetGet("library"));
    }

    /** Ensure an offset that exists is correctly fetched. */
    public function testOffsetGet3(): void
    {
        self::assertSame("bead", self::createArrayAccessor(["framework" => "bead",])->offsetGet("framework"));
    }

    /**
     * Ensure non-string offsets can't be set.
     *
     * @dataProvider providerNonStrings
     */
    public function testOffsetSet1(mixed $offset): void
    {
        $actual = self::createArrayAccessor();
        $actual->offsetSet($offset, "bead");
        self::assertFalse($actual->offsetExists($offset));
        self::assertFalse($actual->wasCalled("set"));
    }

    /** Ensure a new offset can be set successfully. */
    public function testOffsetSet2(): void
    {
        $actual = self::createArrayAccessor();
        $actual->offsetSet("framework", "bead");
        self::assertSame("bead", $actual->get("framework"));
    }

    /** Ensure an existing offset can be set modified. */
    public function testOffsetSet3(): void
    {
        $actual = self::createArrayAccessor(["bead" => "library"]);
        $actual->offsetSet("bead", "framework");
        self::assertSame("framework", $actual->get("bead"));
    }

    /**
     * Ensure non-string offsets can't be unset.
     *
     * @dataProvider providerNonStrings
     */
    public function testOffsetUnset1(mixed $offset): void
    {
        $actual = self::createArrayAccessor();
        $actual->offsetUnset($offset);
        self::assertFalse($actual->wasCalled("remove"));
    }

    /** Ensure unsetting an offset that doesn't exist is a no-op. */
    public function testOffsetUnset2(): void
    {
        $actual = self::createArrayAccessor(["framework" => "bead",]);
        self::assertTrue($actual->has("framework"));
        $actual->offsetUnset("library");
        self::assertTrue($actual->has("framework"));
    }

    /** Ensure an offset can be unset. */
    public function testOffsetUnset3(): void
    {
        $actual = self::createArrayAccessor(["bead" => "framework",]);
        self::assertTrue($actual->has("bead"));
        $actual->offsetUnset("bead");
        self::assertFalse($actual->has("bead"));
    }
}
