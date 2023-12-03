<?php

declare(strict_types=1);

namespace BeadTests\Environment\Sources;

use Bead\Environment\Sources\StaticArray;
use Bead\Exceptions\EnvironmentException;
use BeadTests\Framework\TestCase;

final class StaticArrayTest extends TestCase
{
    private const TEST_ARRAY = [
        "KEY_1" => "value-1",
        "KEY_2" => "value-2",
        "KEY_4" => "value-4",
        " KEY_5 " => "value-5",
    ];

    /** @var StaticArray The provider under test. */
    private StaticArray $envArray;

    public function setUp(): void
    {
        $this->envArray = new StaticArray(self::TEST_ARRAY);
    }

    public function tearDown(): void
    {
        unset($this->envArray);
        parent::tearDown();
    }

    /** Ensure has() returns true for variables set, false for variables not set. */
    public function testHas1(): void
    {
        self::assertFalse($this->envArray->has("KEY"));
        self::assertFalse($this->envArray->has("KEY_0"));
        self::assertTrue($this->envArray->has("KEY_1"));
        self::assertTrue($this->envArray->has("KEY_2"));
        self::assertFalse($this->envArray->has("KEY_3"));
        self::assertTrue($this->envArray->has("KEY_4"));
        self::assertTrue($this->envArray->has("KEY_5"));
        self::assertFalse($this->envArray->has("KEY_6"));
    }

    /** Ensure get() returns variable values, and an empty string for variables not set. */
    public function testGet(): void
    {
        self::assertEquals("", $this->envArray->get("KEY"));
        self::assertEquals("", $this->envArray->get("KEY_0"));
        self::assertEquals("value-1", $this->envArray->get("KEY_1"));
        self::assertEquals("value-2", $this->envArray->get("KEY_2"));
        self::assertEquals("", $this->envArray->get("KEY_3"));
        self::assertEquals("value-4", $this->envArray->get("KEY_4"));
        self::assertEquals("value-5", $this->envArray->get("KEY_5"));
        self::assertEquals("", $this->envArray->get("KEY_6"));
    }

    /**
     * Test data for testConstructor1()
     *
     * @return iterable The test data.
     */
    public function dataForTestConstructor1(): iterable
    {
        yield "bool" => [["key_1" => "value_1", "key_2" => true, "key_3" => "value_3",]];
        yield "array" => [["key_1" => "value_1", "key_2" => ["key_2" => "value_2",], "key_3" => "value_3",]];
        yield "object" => [["key_1" => "value_1", "key_2" => (object) ["key_2" => "value_2",], "key_3" => "value_3",]];
    }

    /**
     * Ensure constructor throws when given an array with values that aren't valid for environment variables.
     *
     * @dataProvider dataForTestConstructor1
     *
     * @param array $data The test data
     */
    public function testConstructor1(array $data): void
    {
        self::expectException(EnvironmentException::class);
        self::expectExceptionMessage("Values for environment variable arrays must be ints, floats or strings.");
        $env = new StaticArray($data);
    }

    /**
     * Test data for testConstructor2()
     *
     * @return iterable The test data.
     */
    public function dataForTestConstructor2(): iterable
    {
        yield "empty" => [["" => "value_1", "key_2" => "value_2", "key_3" => "value_3",]];
        yield "whitespace" => [["   " => "value_1", "key_2" => "value_2", "key_3" => "value_3",]];
        yield "int" => [["key_1" => "value_1", 2 => "value_2", "key_3" => "value_3",]];
    }


    /**
     * Ensure constructor throws when given an array with keys that aren't valid for environment variables names.
     *
     * @dataProvider dataForTestConstructor2
     *
     * @param array $data The test data
     */
    public function testConstructor2(array $data): void
    {
        self::expectException(EnvironmentException::class);
        self::expectExceptionMessageMatches("/^'.*' is not a valid environment variable name.\$/");
        $env = new StaticArray($data);
    }

    /** Ensure names() returns the expected variable names. */
    public function testNames1(): void
    {
        self::assertEquals(
            [
                "KEY_1",
                "KEY_2",
                "KEY_4",
                "KEY_5",
            ],
            $this->envArray->names()
        );
    }

    /** Ensure all() returns the expected variables. */
    public function testAll1(): void
    {
        self::assertEquals(
            [
                "KEY_1" => "value-1",
                "KEY_2" => "value-2",
                "KEY_4" => "value-4",
                "KEY_5" => "value-5",
            ],
            $this->envArray->all()
        );
    }
}
