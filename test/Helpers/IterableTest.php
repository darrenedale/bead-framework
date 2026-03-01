<?php

declare(strict_types=1);

namespace BeadTests\Helpers;

use ArrayIterator;
use BeadTests\Framework\TestCase;
use Error;
use Generator;
use Iterator;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use Traversable;
use TypeError;

use function Bead\Helpers\Iterable\accumulate;
use function Bead\Helpers\Iterable\all;
use function Bead\Helpers\Iterable\filter;
use function Bead\Helpers\Iterable\flatten;
use function Bead\Helpers\Iterable\grammaticalImplode;
use function Bead\Helpers\Iterable\implode;
use function Bead\Helpers\Iterable\isSubsetOf;
use function Bead\Helpers\Iterable\map;
use function Bead\Helpers\Iterable\none;
use function Bead\Helpers\Iterable\partition;
use function Bead\Helpers\Iterable\recursiveCount;
use function Bead\Helpers\Iterable\reduce;
use function Bead\Helpers\Iterable\some;
use function Bead\Helpers\Iterable\toArray;
use function Bead\Helpers\Iterable\transform;

#[CoversFunction("Bead\\Helpers\\Iterable\\accumulate")]
#[CoversFunction("Bead\\Helpers\\Iterable\\all")]
#[CoversFunction("Bead\\Helpers\\Iterable\\filter")]
#[CoversFunction("Bead\\Helpers\\Iterable\\flatten")]
#[CoversFunction("Bead\\Helpers\\Iterable\\grammaticalImplode")]
#[CoversFunction("Bead\\Helpers\\Iterable\\implode")]
#[CoversFunction("Bead\\Helpers\\Iterable\\isSubsetOf")]
#[CoversFunction("Bead\\Helpers\\Iterable\\map")]
#[CoversFunction("Bead\\Helpers\\Iterable\\none")]
#[CoversFunction("Bead\\Helpers\\Iterable\\partition")]
#[CoversFunction("Bead\\Helpers\\Iterable\\recursiveCount")]
#[CoversFunction("Bead\\Helpers\\Iterable\\reduce")]
#[CoversFunction("Bead\\Helpers\\Iterable\\some")]
#[CoversFunction("Bead\\Helpers\\Iterable\\toArray")]
#[CoversFunction("Bead\\Helpers\\Iterable\\transform")]
final class IterableTest extends TestCase
{
    /**
     * Helper function for use with testing map().
     *
     * @param float $value The value to square.
     *
     * @return float The square of the value.
     */
    public static function sqrt(float $value): float
    {
        return sqrt($value);
    }

    /**
     * Helper function for use with testing reduce().
     *
     * @param int $value The value from the iterable.
     * @param int $carry The current carry from the reduction.
     *
     * @return int The new carry, the product of the old carry and the value.
     */
    public static function product(int $value, int $carry): int
    {
        return $value * $carry;
    }

    /**
     * Helper function for use with testing reduce().
     *
     * @param int $value The value from the iterable.
     * @param int $carry The current carry from the reduction.
     *
     * @return int The new carry, the maximum of the old carry and the value.
     */
    public static function max(int $value, int $carry): int
    {
        return max($value, $carry);
    }

    /**
     * Helper function for use with testing all()/some()/none().
     *
     * @param int $value The value from the iterable.
     *
     * @return bool `true` if the value is an int type,`false` otherwise.
     */
    public static function isInt($value): bool
    {
        return is_int($value);
    }

    /**
     * Helper function for use with testing all()/some()/none().
     *
     * @param int $value The value from the iterable.
     *
     * @return false
     */
    public static function alwaysFalse($value)
    {
        return false;
    }

    /**
     * Helper function for use with testing all()/some()/none().
     *
     * @param int $value The value from the iterable.
     *
     * @return true
     */
    public static function alwaysTrue($value)
    {
        return true;
    }

    /**
     * Helper to create a Iterable instance for testing.
     *
     * @param array $data The data the Iterable will traverse.
     *
     * @return Iterator The test instance.
     */
    private static function createIterator(array $data): Iterator
    {
        /** @psalm-suppress MissingTemplateParam */
        return new class ($data) implements Iterator
        {
            private array $data;
            private int $index;

            public function __construct(array $data)
            {
                $this->data = $data;
                $this->index = 0;
            }

            public function current(): mixed
            {
                return $this->data[$this->index] ?? null;
            }

            public function next(): void
            {
                ++$this->index;
            }

            public function rewind(): void
            {
                $this->index = 0;
            }

            public function valid(): bool
            {
                return count($this->data) > $this->index;
            }

            public function key(): mixed
            {
                return $this->valid() ? $this->index : null;
            }
        };
    }

    /**
     * Helper to create a Iterable instance for testing that has non-sequential, potentially duplicated keys.
     *
     * @param array $data The data the Iterable will traverse.
     *
     * @return Iterator The test instance.
     */
    private static function createIteratorWithKeys(array $values, ?array $keys = null): Iterator
    {
        /** @psalm-suppress MissingTemplateParam */
        return new class ($values, $keys) implements Iterator
        {
            private array $values;
            private array $keys;
            private int $index;

            public function __construct(array $values, ?array $keys = null)
            {
                $this->values = array_values($values);

                if (null === $keys) {
                    $this->keys = array_keys($values);
                } else {
                    $this->keys = $keys;
                }

                $this->index = 0;
            }

            public function current(): mixed
            {
                return $this->values[$this->index] ?? null;
            }

            public function next(): void
            {
                ++$this->index;
            }

            public function rewind(): void
            {
                $this->index = 0;
            }

            public function valid(): bool
            {
                return count($this->values) > $this->index;
            }

            public function key(): mixed
            {
                return $this->valid() ? $this->keys[$this->index] : null;
            }
        };
    }

    /**
     * Helper to create a Generator instance for testing.
     *
     * @param array $data The data the generator will yield.
     *
     * @return iterable The test instance.
     */
    private static function createGenerator(array $data): iterable
    {
        yield from $data;
    }

    /** Test data for testMap1() */
    public static function providerTestMap1(): iterable
    {
        yield from [
            "stringCallable" => [[1, 4, 9,], "sqrt", [1, 2, 3,],],
            "closureCallable" => [[1, 4, 9,], fn (int $value) => (int) sqrt($value), [1, 2, 3,],],
            "staticMethodTupleCallable" => [[1, 4, 9,], [self::class, "sqrt"], [1, 2, 3,],],
            "invokableCallable" => [
                [1, 4, 9,],
                new class ()
                {
                    public function __invoke(float $value): float
                    {
                        return sqrt($value);
                    }
                },
                [1, 2, 3,],
            ],
        ];
    }

    /**
     * @param iterable $data The test data.
     * @param callable $fn The test mapping function.
     * @param iterable $expected The expected mapped data.
     */
    #[DataProvider("providerTestMap1")]
    public function testMap1(iterable $data, callable $fn, iterable $expected, ?string $exceptionClass = null): void
    {
        self::assertEquals(toArray($expected), toArray(map($data, $fn)));
    }

    /** Test data for testFlatten1() */
    public static function providerTestFlatten1(): iterable
    {
        yield from [
            "typicalInts" => [
                [1, 2, 3, [4, 5, [6,], 7, [8, 9,],], 10, 11, 12,],
                [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12,],
            ],
            "typicalIntsAlreadyFlat" => [
                [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12,],
                [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12,],
            ],
            "extremeEmpty" => [
                [],
                [],
            ],
            "typicalStrings" => [
                ["1", "2", "3", ["4", "5", ["6",], "7", ["8", "9",],], "10", "11", "12",],
                ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12",],
            ],
            "typicalStringsAlreadyFlat" => [
                ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12",],
                ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12",],
            ],
        ];
    }

    /**
     * @param iterable $data The test data.
     * @param iterable $expected The expected flattened iterable.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    #[DataProvider("providerTestFlatten1")]
    public function testFlatten1(iterable $data, iterable $expected): void
    {
        self::assertEquals(toArray($expected), toArray(flatten($data)));
    }

    /** Test data for testToArray1() */
    public static function providerTestToArray1(): iterable
    {
        yield from [
            "typicalIterable" => [
                self::createIterator([1, 2, 3,]),
                [1, 2, 3,],
            ],
            "typicalEmptyIterable" => [
                self::createIterator([]),
                [],
            ],
            "typicalGenerator" => [
                self::createGenerator([1, 2, 3,]),
                [1, 2, 3,],
            ],
            "typicalEmptyGenerator" => [
                self::createGenerator([]),
                [],
            ],
            "typicalAlreadyArray" => [
                [1, 2, 3,],
                [1, 2, 3,],
            ],
            "typicalAlreadyEmptyArray" => [
                [],
                [],
            ],
            "typicalPreservesKeysGenerator" => [
                self::createGenerator([1, "two" => 42, "pi" => 3.14]),
                [1, "two" => 42, "pi" => 3.14],
            ],
            "typicalPreservesKeysIterator" => [
                self::createIteratorWithKeys([1, "two" => 42, "pi" => 3.14]),
                [1, "two" => 42, "pi" => 3.14],
            ],
            "typicalPreservesKeysArray" => [
                [1, "two" => 42, "pi" => 3.14],
                [1, "two" => 42, "pi" => 3.14],
            ],
            "duplicate-keys-get-last-item-iterator" => [
                self::createIteratorWithKeys([1, 2, 42, 3.14], [0, "two", "two", "pi"]),
                [1, "two" => 42, "pi" => 3.14],
            ],
            "duplicate-keys-get-last-item-generator" => [
                (static function (): Generator {
                    yield 1;
                    yield "two" => 2;
                    yield "two" => 42;
                    yield "pi" => 3.14;
                })(),
                [1, "two" => 42, "pi" => 3.14],
            ],
        ];
    }

    /**
     * @param iterable $data The test data.
     * @param array $expected The expected array.
     */
    #[DataProvider("providerTestToArray1")]
    public function testToArray1(iterable $data, array $expected): void
    {
        self::assertSame($expected, toArray($data));
    }


    /** Test data for testImplode1() */
    public static function providerTestImplode1(): iterable
    {
        yield from [
            "typicalIterableWithComma" => [
                self::createIterator([1, 2, 3,]),
                ",",
                "1,2,3",
            ],
            "typicalIterableWithSemicolon" => [
                self::createIterator([1, 2, 3,]),
                ";",
                "1;2;3",
            ],
            "typicalIterableWithDash" => [
                self::createIterator([1, 2, 3,]),
                "-",
                "1-2-3",
            ],
            "extremeIterableWithLongString" => [
                self::createIterator([1, 2, 3,]),
                "RIDICULOUSLY-LONG-GLUE",
                "1RIDICULOUSLY-LONG-GLUE2RIDICULOUSLY-LONG-GLUE3",
            ],
            "extremeIterableWithEmptyString" => [
                self::createIterator([1, 2, 3,]),
                "",
                "123",
            ],
            "typicalIterableWithCommaSpace" => [
                self::createIterator([1, 2, 3,]),
                ", ",
                "1, 2, 3",
            ],
            "typicalEmptyIterableComma" => [
                self::createIterator([]),
                ",",
                "",
            ],
            "typicalEmptyIterableCommaSpace" => [
                self::createIterator([]),
                ", ",
                "",
            ],
            "typicalGenerator" => [
                self::createGenerator([1, 2, 3,]),
                ",",
                "1,2,3",
            ],
            "extremeGeneratorWithLongString" => [
                self::createGenerator([1, 2, 3,]),
                "RIDICULOUSLY-LONG-GLUE",
                "1RIDICULOUSLY-LONG-GLUE2RIDICULOUSLY-LONG-GLUE3",
            ],
            "extremeGeneratorWithEmptyString" => [
                self::createGenerator([1, 2, 3,]),
                "",
                "123",
            ],
            "typicalEmptyGeneratorComma" => [
                self::createGenerator([]),
                ",",
                ""
            ],
            "typicalEmptyGeneratorCommaSpace" => [
                self::createGenerator([]),
                ", ",
                ""
            ],
            "typicalArrayComma" => [
                [1, 2, 3,],
                ",",
                "1,2,3",
            ],
            "typicalArrayDash" => [
                [1, 2, 3,],
                "-",
                "1-2-3",
            ],
            "typicalArraySemicolon" => [
                [1, 2, 3,],
                ";",
                "1;2;3",
            ],
            "typicalArrayCommaSpace" => [
                [1, 2, 3,],
                ", ",
                "1, 2, 3",
            ],
            "extremeArrayWithLongString" => [
                [1, 2, 3,],
                "RIDICULOUSLY-LONG-GLUE",
                "1RIDICULOUSLY-LONG-GLUE2RIDICULOUSLY-LONG-GLUE3",
            ],
            "extremeArrayWithEmptyString" => [
                [1, 2, 3,],
                "",
                "123",
            ],
            "typicalEmptyArrayComma" => [
                [],
                ",",
                "",
            ],
            "typicalEmptyArrayCommaSpace" => [
                [],
                ", ",
                "",
            ],
        ];
    }

    /**
     * @param iterable $iterable The iterable to test with.
     * @param string $glue The glue to test with.
     */
    #[DataProvider("providerTestImplode1")]
    public function testImplode1(iterable $iterable, string $glue, string $expected): void
    {
        self::assertEquals($expected, implode($glue, $iterable));
    }


    /** Test data for testGrammaticalImplode1() */
    public static function providerTestGrammaticalImplode1(): iterable
    {
        yield from [
            "typicalIterableWithComma" => [
                self::createIterator([1, 2, 3,]),
                ", ",
                " and ",
                "1, 2 and 3",
            ],
            "typicalIterableWithSemicolon" => [
                self::createIterator([1, 2, 3,]),
                "; ",
                " or ",
                "1; 2 or 3",
            ],
            "typicalIterableWithDash" => [
                self::createIterator([1, 2, 3,]),
                " - ",
                " and ",
                "1 - 2 and 3",
            ],
            "typicalSingleItemIterable" => [
                self::createIterator(["foo",]),
                " - ",
                " and ",
                "foo",
            ],
            "extremeIterableWithLongString" => [
                self::createIterator([1, 2, 3,]),
                "RIDICULOUSLY-LONG-GLUE",
                "RIDICULOUSLY-LONG-LAST-GLUE",
                "1RIDICULOUSLY-LONG-GLUE2RIDICULOUSLY-LONG-LAST-GLUE3",
            ],
            "extremeIterableWithEmptyGlue" => [
                self::createIterator([1, 2, 3,]),
                "",
                " and ",
                "12 and 3",
            ],
            "extremeIterableWithEmptyLastGlue" => [
                self::createIterator([1, 2, 3,]),
                ", ",
                "",
                "1, 23",
            ],
            "typicalEmptyIterableComma" => [
                self::createIterator([]),
                ", ",
                " and ",
                "",
            ],
            "typicalGenerator" => [
                self::createGenerator([1, 2, 3,]),
                ", ",
                " and ",
                "1, 2 and 3",
            ],
            "typicalGeneratorSemicolon" => [
                self::createGenerator([1, 2, 3,]),
                "; ",
                " or ",
                "1; 2 or 3",
            ],
            "typicalSingleItemGenerator" => [
                self::createGenerator(["foo",]),
                " - ",
                " and ",
                "foo",
            ],
            "extremeGeneratorWithLongString" => [
                self::createGenerator([1, 2, 3,]),
                "RIDICULOUSLY-LONG-GLUE",
                "RIDICULOUSLY-LONG-LAST-GLUE",
                "1RIDICULOUSLY-LONG-GLUE2RIDICULOUSLY-LONG-LAST-GLUE3",
            ],
            "extremeGeneratorWithEmptyGlue" => [
                self::createGenerator([1, 2, 3,]),
                "",
                " and ",
                "12 and 3",
            ],
            "extremeGeneratorWithEmptyLastGlue" => [
                self::createGenerator([1, 2, 3,]),
                ", ",
                "",
                "1, 23",
            ],
            "extremeGeneratorWithEmptyGlues" => [
                self::createGenerator([1, 2, 3,]),
                "",
                "",
                "123",
            ],
            "typicalEmptyGeneratorComma" => [
                self::createGenerator([]),
                ", ",
                " and ",
                "",
            ],
            "typicalArrayComma" => [
                [1, 2, 3,],
                ", ",
                " and ",
                "1, 2 and 3",
            ],
            "typicalArrayDash" => [
                [1, 2, 3,],
                "-",
                " and ",
                "1-2 and 3",
            ],
            "typicalArraySemicolon" => [
                [1, 2, 3,],
                "; ",
                " or ",
                "1; 2 or 3",
            ],
            "typicalSingleItemArray" => [
                ["foo",],
                " - ",
                " and ",
                "foo",
            ],
            "extremeArrayWithLongString" => [
                [1, 2, 3,],
                "RIDICULOUSLY-LONG-GLUE",
                "RIDICULOUSLY-LONG-LAST-GLUE",
                "1RIDICULOUSLY-LONG-GLUE2RIDICULOUSLY-LONG-LAST-GLUE3",
            ],
            "extremeArrayWithEmptyGlue" => [
                [1, 2, 3,],
                "",
                " and ",
                "12 and 3",
            ],
            "extremeArrayWithEmptyLastGlue" => [
                [1, 2, 3,],
                ", ",
                "",
                "1, 23",
            ],
            "extremeArrayWithEmptyGlues" => [
                [1, 2, 3,],
                "",
                "",
                "123",
            ],
            "typicalEmptyArrayComma" => [
                [],
                ", ",
                " and ",
                "",
            ],
            "typicalEmptyArrayCommaSpace" => [
                [],
                ", ",
                " and ",
                "",
            ],
        ];
    }

    /**
     * @param iterable $iterable The iterable to test with.
     * @param string $glue The glue to test with.
     * @param string $lastGlue The glue for the last pair of items.
     * @param string $expected The expected output.
     */
    #[DataProvider("providerTestGrammaticalImplode1")]
    public function testGrammaticalImplode1(iterable $iterable, string $glue, string $lastGlue, string $expected): void
    {
        self::assertEquals($expected, grammaticalImplode($iterable, $glue, $lastGlue));
    }

    /** Ensure grammaticalImplode uses the correct default glues. */
    public function testGrammaticalImplode2(): void
    {
        $actual = grammaticalImplode(["red", "green", "blue"]);
        self::assertIsString($actual);
        self::assertEquals("red, green and blue", $actual);
    }

    /** Ensure grammaticalImplode uses the correct default last glue when a glue is given but no last glue. */
    public function testGrammaticalImplodeWithDefaultLastGlue(): void
    {
        self::assertEquals("red; green and blue", grammaticalImplode(["red", "green", "blue"], "; "));
    }

    /** The test data for testTransform1(). */
    public static function providerTestTransform1(): iterable
    {
        $sqrt = function (float $value): float {
            return sqrt($value);
        };

        $staticSqrt = [self::class, "sqrt"];

        // note Iterator implementations can't be traversed by reference
        yield from [
            "typicalArrayAndClosure" => [[1, 4, 9,], $sqrt, [1, 2, 3,],],
            "typicalArrayIteratorAndClosure" => [new ArrayIterator([1, 4, 9,]), $sqrt, [1, 2, 3,],],
            "extremeEmptyArrayAndClosure" => [[], $sqrt, [],],
            "extremeEmptyArrayIteratorAndClosure" => [new ArrayIterator([]), $sqrt, [],],
            "typicalArrayAndStaticMethod" => [[1, 4, 9,], $staticSqrt, [1, 2, 3,],],
            "typicalArrayIteratorAndStaticMethod" => [new ArrayIterator([1, 4, 9,]), $staticSqrt, [1, 2, 3,],],
            "extremeEmptyArrayAndStaticMethod" => [[], $staticSqrt, [],],
            "extremeEmptyArrayIteratorAndStaticMethod" => [new ArrayIterator([]), $staticSqrt, [],],
            "typicalArrayAndFunctionName" => [[1, 4, 9,], "sqrt", [1, 2, 3,],],
            "typicalArrayIteratorAndFunctionName" => [new ArrayIterator([1, 4, 9,]), "sqrt", [1, 2, 3,],],
            "extremeEmptyArrayAndFunctionName" => [[], "sqrt", [],],
            "extremeEmptyArrayIteratorAndFunctionName" => [new ArrayIterator([]), "sqrt", [],],
        ];
    }

    /**
     * @param iterable $data The test data to transform.
     * @param callable $fn The callable to test with.
     * @param iterable $expected The expected transformed values.
     */
    #[DataProvider("providerTestTransform1")]
    public function testTransform1(iterable $data, callable $fn, iterable $expected): void
    {
        $actual = transform($data, $fn);
        self::assertSame($data, $actual);

        $expected = toArray($expected);
        $actual = toArray($actual);

        for ($idx = 0; $idx < count($expected); ++$idx) {
            self::assertEquals($expected[$idx], $actual[$idx]);
        }
    }

    /** The test data for testTransform2(). */
    public static function providerTestTransform2(): iterable
    {
        $sqrt = function (float $value): float {
            return sqrt($value);
        };

        $staticSqrt = [self::class, "sqrt"];

        // note Iterator implementations can't be traversed by reference
        yield from [
            "typicalIterableAndClosure" => [self::createIterator([1, 4, 9,]), $sqrt, Error::class,],
            "extremeEmptyIterableAndClosure" => [self::createIterator([]), $sqrt, Error::class,],
            "typicalIterableAndStaticMethod" => [self::createIterator([1, 4, 9,]), $staticSqrt, Error::class,],
            "extremeEmptyIterableAndStaticMethod" => [self::createIterator([]), $staticSqrt, Error::class,],
            "typicalIterableAndFunctionName" => [self::createIterator([1, 4, 9,]), "sqrt", Error::class,],
            "extremeEmptyIterableAndFunctionName" => [self::createIterator([]), "sqrt", Error::class,],
        ];
    }

    /**
     * Ensure transform() thorws the expected error with Iterators that can't be traversed by reference.
     * @param iterable $data The test data to transform.
     * @param callable $fn The callable to test with.
     * @param iterable $expected The expected transformed values.
     */
    #[DataProvider("providerTestTransform2")]
    public function testTransform2(Traversable $data, callable $fn): void
    {
        $this->expectException(Error::class);
        transform($data, $fn);
    }

    /** Ensure that transform() works with generators, even though doing so renders the generator useless. */
    public function testTransform3(): void
    {
        $data = (function & (): Generator {
            $data = [1, 2, 3,];

            foreach ($data as & $item) {
                yield & $item;
            }
        })();

        $actual = transform($data, fn ($value) => $value);
        self::assertSame($data, $actual);
    }

    /** Test data for testReduce1(). */
    public static function providerTestReduce1(): iterable
    {
        $product = fn (int $value, int $carry): int => $carry * $value;
        $max = fn (int $value, int $carry): int => max($value, $carry);

        yield from [
            "typicalArrayProductClosure" => [[1, 3, 2,], $product, 1, 6,],
            "typicalArrayProductStaticMethodTuple" => [[1, 3, 2,], [self::class, "product"], 1, 6,],

            "typicalIteratorProductClosure" => [self::createIterator([1, 3, 2,]), $product, 1, 6,],
            "typicalIteratorProductStaticMethodTuple" => [self::createIterator([1, 3, 2,]), [self::class, "product"], 1, 6,],

            "typicalGeneratorProductClosure" => [self::createGenerator([1, 3, 2,]), $product, 1, 6,],
            "typicalGeneratorProductStaticMethodTuple" => [self::createGenerator([1, 3, 2,]), [self::class, "product"], 1, 6,],

            "typicalArrayMaxStringClosure" => [[1, 3, 2,], $max, PHP_INT_MIN, 3,],
            "typicalArrayMaxStringStaticMethodTuple" => [[1, 3, 2,], [self::class, "max"], PHP_INT_MIN, 3,],
            "typicalArrayMaxStringFunctionName" => [[1, 3, 2,], "max", PHP_INT_MIN, 3,],

            "typicalIteratorMaxStringClosure" => [self::createIterator([1, 3, 2,]), $max, PHP_INT_MIN, 3,],
            "typicalIteratorMaxStringStaticMethodTuple" => [self::createIterator([1, 3, 2,]), [self::class, "max"], PHP_INT_MIN, 3,],
            "typicalIteratorMaxStringFunctionName" => [self::createIterator([1, 3, 2,]), "max", PHP_INT_MIN, 3,],

            "typicalGeneratorMaxStringClosure" => [self::createGenerator([1, 3, 2,]), $max, PHP_INT_MIN, 3,],
            "typicalGeneratorMaxStringStaticMethodTuple" => [self::createGenerator([1, 3, 2,]), [self::class, "max"], PHP_INT_MIN, 3,],
            "typicalGeneratorMaxStringFunctionName" => [self::createGenerator([1, 3, 2,]), "max", PHP_INT_MIN, 3,],

            // ensure we get init value when there is nothing to reduce
            "extremeEmptyArray" => [[], $max, PHP_INT_MIN, PHP_INT_MIN,],
            "extremeEmptyIterator" => [self::createIterator([]), $max, PHP_INT_MIN, PHP_INT_MIN,],
            "extremeEmptyGenerator" => [self::createGenerator([]), $max, PHP_INT_MIN, PHP_INT_MIN,],
        ];
    }

    /**
     * Test reduce() function.
     *
     * @param mixed $data The test data to reduce.
     * @param mixed $fn The function to do the reduction.
     * @param mixed $init The starting value for the reduction to test with.
     * @param mixed $expected The expected outcome.
     * @param string|null $exceptionClass The exception expected, if any.
     */
    #[DataProvider("providerTestReduce1")]
    public function testReduce1(iterable $data, callable $fn, mixed $init, mixed $expected): void
    {
        self::assertEquals($expected, reduce($data, $fn, $init));
    }

    /** Test data for testAccumulate1(). */
    public static function providerTestAccumulate1(): iterable
    {
        $product = fn (int $value, int $carry): int => $carry * $value;

        yield from [
            "typicalArrayDefault" => [[1, 2, 3,], null, null, 6,],
            "typicalIteratorDefault" => [self::createIterator([1, 2, 3,]), null, null, 6,],
            "typicalGeneratorDefault" => [self::createGenerator([1, 2, 3,]), null, null, 6,],

            "typicalArrayDefaultAccumulatorWithInit" => [[1, 2, 3,], null, 1, 7,],
            "typicalIteratorDefaultAccumulatorWithInit" => [self::createIterator([1, 2, 3,]), null, 1, 7,],
            "typicalGeneratorDefaultAccumulatorWithInit" => [self::createGenerator([1, 2, 3,]), null, 1, 7,],

            "typicalArrayProductDefaultInitClosure" => [[1, 3, 2,], $product, null, 0,],
            "typicalArrayProductDefaultInitStaticMethodTuple" => [[1, 3, 2,], [self::class, "product"], null, 0,],

            "typicalIteratorProductDefaultInitClosure" => [self::createIterator([1, 3, 2,]), $product, null, 0,],
            "typicalIteratorProductDefaultInitStaticMethodTuple" => [self::createIterator([1, 3, 2,]), [self::class, "product"], null, 0,],

            "typicalGeneratorProductDefaultInitClosure" => [self::createGenerator([1, 3, 2,]), $product, null, 0,],
            "typicalGeneratorProductDefaultInitStaticMethodTuple" => [self::createGenerator([1, 3, 2,]), [self::class, "product"], null, 0,],

            "typicalArrayProductClosure" => [[1, 3, 2,], $product, 1, 6,],
            "typicalArrayProductStaticMethodTuple" => [[1, 3, 2,], [self::class, "product"], 1, 6,],

            "typicalIteratorProductClosure" => [self::createIterator([1, 3, 2,]), $product, 1, 6,],
            "typicalIteratorProductStaticMethodTuple" => [self::createIterator([1, 3, 2,]), [self::class, "product"], 1, 6,],

            "typicalGeneratorProductClosure" => [self::createGenerator([1, 3, 2,]), $product, 1, 6,],
            "typicalGeneratorProductStaticMethodTuple" => [self::createGenerator([1, 3, 2,]), [self::class, "product"], 1, 6,],

            // ensure we get init value when there is nothing to accumulate
            "extremeEmptyArray" => [[], $product, 1, 1,],
            "extremeEmptyIterator" => [self::createIterator([]), $product, 1, 1,],
            "extremeEmptyGenerator" => [self::createGenerator([]), $product, 1, 1,],

            // ensure we get the default init value when there is nothing to accumulate and no init
            "extremeEmptyArrayDefaultInit" => [[], $product, null, 0,],
            "extremeEmptyIteratorDefaultInit" => [self::createIterator([]), $product, null, 0,],
            "extremeEmptyGeneratorDefaultInit" => [self::createGenerator([]), $product, null, 0,],
        ];
    }

    /**
     * Test reduce() function.
     *
     * @param iterable $data The test data to reduce.
     * @param callable | null $fn The function to do the reduction.
     * @param mixed $init The starting value for the reduction to test with. `null` indicates the default arg.
     * @param mixed $expected The expected outcome.
     */
    #[DataProvider("providerTestAccumulate1")]
    public function testAccumulate1(iterable $data, ?callable $fn, mixed $init, mixed $expected): void
    {
        $args = [$data, $fn,];

        if (isset($init)) {
            $args[] = $init;
        }

        self::assertEquals($expected, accumulate(...$args));
    }

    /** Test data for the all() function. */
    public static function providerTestAll1(): iterable
    {
        $true = fn ($value): bool => true;
        $false = fn ($value): bool => false;
        $isInt = fn ($value): bool => is_int($value);

        yield from [
            // predicate expected to pass for all items
            "typicalArrayAllIntsClosurePredicate" => [[1, 2, 3,], $isInt, true,],
            "typicalArrayAllIntsStaticMethodTuplePredicate" => [[1, 2, 3,], [self::class, "isInt"], true,],
            "typicalArrayAllIntsFunctionNamePredicate" => [[1, 2, 3,], "is_int", true,],

            "typicalIteratorAllIntsClosurePredicate" => [self::createIterator([1, 2, 3,]), $isInt, true,],
            "typicalIteratorAllIntsStaticMethodTuplePredicate" => [self::createIterator([1, 2, 3,]), [self::class, "isInt"], true,],
            "typicalIteratorAllIntsFunctionNamePredicate" => [self::createIterator([1, 2, 3,]), "is_int", true,],

            "typicalGeneratorAllIntsClosurePredicate" => [self::createGenerator([1, 2, 3,]), $isInt, true,],
            "typicalGeneratorAllIntsStaticMethodTuplePredicate" => [self::createGenerator([1, 2, 3,]), [self::class, "isInt"], true,],
            "typicalGeneratorAllIntsFunctionNamePredicate" => [self::createGenerator([1, 2, 3,]), "is_int", true,],

            // predicate expected to fail for some items
            "typicalArraySomeIntsClosurePredicate" => [[1, 3.1415926, 3,], $isInt, false,],
            "typicalArraySomeIntsStaticMethodTuplePredicate" => [[1, 3.1415926, 3,], [self::class, "isInt"], false,],
            "typicalArraySomeIntsFunctionNamePredicate" => [[1, 3.1415926, 3,], "is_int", false,],

            "typicalIteratorSomeIntsClosurePredicate" => [self::createIterator([1, 3.1415926, 3,]), $isInt, false,],
            "typicalIteratorSomeIntsStaticMethodTuplePredicate" => [self::createIterator([1, 3.1415926, 3,]), [self::class, "isInt"], false,],
            "typicalIteratorSomeIntsFunctionNamePredicate" => [self::createIterator([1, 3.1415926, 3,]), "is_int", false,],

            "typicalGeneratorSomeIntsClosurePredicate" => [self::createGenerator([1, 3.1415926, 3,]), $isInt, false,],
            "typicalGeneratorSomeIntsStaticMethodTuplePredicate" => [self::createGenerator([1, 3.1415926, 3,]), [self::class, "isInt"], false,],
            "typicalGeneratorSomeIntsFunctionNamePredicate" => [self::createGenerator([1, 3.1415926, 3,]), "is_int", false,],

            // predicate expected to fail for all items
            "typicalArrayNoIntsClosurePredicate" => [[1.1, 3.1415926, 0.1], $isInt, false,],
            "typicalArrayNoIntsStaticMethodTuplePredicate" => [[1.1, 3.1415926, 0.1], [self::class, "isInt"], false,],
            "typicalArrayNoIntsFunctionNamePredicate" => [[1.1, 3.1415926, 0.1], "is_int", false,],

            "typicalIteratorNoIntsClosurePredicate" => [self::createIterator([1.1, 3.1415926, 0.1]), $isInt, false,],
            "typicalIteratorNoIntsStaticMethodTuplePredicate" => [self::createIterator([1.1, 3.1415926, 0.1]), [self::class, "isInt"], false,],
            "typicalIteratorNoIntsFunctionNamePredicate" => [self::createIterator([1.1, 3.1415926, 0.1]), "is_int", false,],

            "typicalGeneratorNoIntsClosurePredicate" => [self::createGenerator([1.1, 3.1415926, 0.1]), $isInt, false,],
            "typicalGeneratorNoIntsStaticMethodTuplePredicate" => [self::createGenerator([1.1, 3.1415926, 0.1]), [self::class, "isInt"], false,],
            "typicalGeneratorNoIntsFunctionNamePredicate" => [self::createGenerator([1.1, 3.1415926, 0.1]), "is_int", false,],

            // ensure an unsatisfiable predicate works as expected
            "extremeArrayIntsClosureFalsePredicate" => [[1, 2, 3,], $false, false,],
            "extremeArrayIntsStaticMethodTupleFalsePredicate" => [[1, 2, 3,], [self::class, "alwaysFalse"], false,],

            "extremeIteratorIntsClosureFalsePredicate" => [self::createIterator([1, 2, 3,]), $false, false,],
            "extremeIteratorIntsStaticMethodTupleFalsePredicate" => [self::createIterator([1, 2, 3,]), [self::class, "alwaysFalse"], false,],

            "extremeGeneratorIntsClosureFalsePredicate" => [self::createGenerator([1, 2, 3,]), $false, false,],
            "extremeGeneratorIntsStaticMethodTupleFalsePredicate" => [self::createGenerator([1, 2, 3,]), [self::class, "alwaysFalse"], false,],

            // ensure an unfussy predicate works as expected
            "extremeArrayIntsClosureTruePredicate" => [[1, 2, 3,], $true, true,],
            "extremeArrayIntsStaticMethodTupleTruePredicate" => [[1, 2, 3,], [self::class, "alwaysTrue"], true,],

            "extremeIteratorIntsClosureTruePredicate" => [self::createIterator([1, 2, 3,]), $true, true,],
            "extremeIteratorIntsStaticMethodTupleTruePredicate" => [self::createIterator([1, 2, 3,]), [self::class, "alwaysTrue"], true,],

            "extremeGeneratorIntsClosureTruePredicate" => [self::createGenerator([1, 2, 3,]), $true, true,],
            "extremeGeneratorIntsStaticMethodTupleTruePredicate" => [self::createGenerator([1, 2, 3,]), [self::class, "alwaysTrue"], true,],

            // ensure empty arrays behave as expected
            "extremeEmptyArrayClosureFalsePredicate" => [[], $false, true,],
            "extremeEmptyArrayStaticMethodTupleFalsePredicate" => [[], [self::class, "alwaysFalse"], true,],

            "extremeEmptyIteratorClosureFalsePredicate" => [self::createIterator([]), $false, true,],
            "extremeEmptyIteratorStaticMethodTupleFalsePredicate" => [self::createIterator([]), [self::class, "alwaysFalse"], true,],

            "extremeEmptyGeneratorClosureFalsePredicate" => [self::createGenerator([]), $false, true,],
            "extremeEmptyGeneratorStaticMethodTupleFalsePredicate" => [self::createGenerator([]), [self::class, "alwaysFalse"], true,],

            "extremeEmptyArrayClosureTruePredicate" => [[], $true, true,],
            "extremeEmptyArrayStaticMethodTupleTruePredicate" => [[], [self::class, "alwaysTrue"], true,],

            "extremeEmptyIteratorClosureTruePredicate" => [self::createIterator([]), $true, true,],
            "extremeEmptyIteratorStaticMethodTupleTruePredicate" => [self::createIterator([]), [self::class, "alwaysTrue"], true,],

            "extremeEmptyGeneratorClosureTruePredicate" => [self::createGenerator([]), $true, true,],
            "extremeEmptyGeneratorStaticMethodTupleTruePredicate" => [self::createGenerator([]), [self::class, "alwaysTrue"], true,],
        ];
    }

    /**
     * Test all().
     *
     * @param iterable $collection The data to test with.
     * @param callable $predicate The predicate to test with.
     * @param bool $expected The expected return value from all()
     */
    #[DataProvider("providerTestAll1")]
    public function testAll1(iterable $collection, callable $predicate, bool $expected): void
    {
        self::assertEquals($expected, all($collection, $predicate));
    }

    /** Test data for the none() function. */
    public static function providerTestNone1(): iterable
    {
        $true = fn ($value): bool => true;
        $false = fn ($value): bool => false;
        $isInt = fn ($value): bool => is_int($value);

        yield from [
            // predicate expected to pass for all items
            "typicalArrayAllIntsClosurePredicate" => [[1, 2, 3,], $isInt, false,],
            "typicalArrayAllIntsStaticMethodTuplePredicate" => [[1, 2, 3,], [self::class, "isInt"], false,],
            "typicalArrayAllIntsFunctionNamePredicate" => [[1, 2, 3,], "is_int", false,],

            "typicalIteratorAllIntsClosurePredicate" => [self::createIterator([1, 2, 3,]), $isInt, false,],
            "typicalIteratorAllIntsStaticMethodTuplePredicate" => [self::createIterator([1, 2, 3,]), [self::class, "isInt"], false,],
            "typicalIteratorAllIntsFunctionNamePredicate" => [self::createIterator([1, 2, 3,]), "is_int", false,],

            "typicalGeneratorAllIntsClosurePredicate" => [self::createGenerator([1, 2, 3,]), $isInt, false,],
            "typicalGeneratorAllIntsStaticMethodTuplePredicate" => [self::createGenerator([1, 2, 3,]), [self::class, "isInt"], false,],
            "typicalGeneratorAllIntsFunctionNamePredicate" => [self::createGenerator([1, 2, 3,]), "is_int", false,],

            // predicate expected to fail for some items
            "typicalArraySomeIntsClosurePredicate" => [[1, 3.1415926, 3,], $isInt, false,],
            "typicalArraySomeIntsStaticMethodTuplePredicate" => [[1, 3.1415926, 3,], [self::class, "isInt"], false,],
            "typicalArraySomeIntsFunctionNamePredicate" => [[1, 3.1415926, 3,], "is_int", false,],

            "typicalIteratorSomeIntsClosurePredicate" => [self::createIterator([1, 3.1415926, 3,]), $isInt, false,],
            "typicalIteratorSomeIntsStaticMethodTuplePredicate" => [self::createIterator([1, 3.1415926, 3,]), [self::class, "isInt"], false,],
            "typicalIteratorSomeIntsFunctionNamePredicate" => [self::createIterator([1, 3.1415926, 3,]), "is_int", false,],

            "typicalGeneratorSomeIntsClosurePredicate" => [self::createGenerator([1, 3.1415926, 3,]), $isInt, false,],
            "typicalGeneratorSomeIntsStaticMethodTuplePredicate" => [self::createGenerator([1, 3.1415926, 3,]), [self::class, "isInt"], false,],
            "typicalGeneratorSomeIntsFunctionNamePredicate" => [self::createGenerator([1, 3.1415926, 3,]), "is_int", false,],

            // predicate expected to fail for all items
            "typicalArrayNoIntsClosurePredicate" => [[1.1, 3.1415926, 0.1], $isInt, true,],
            "typicalArrayNoIntsStaticMethodTuplePredicate" => [[1.1, 3.1415926, 0.1], [self::class, "isInt"], true,],
            "typicalArrayNoIntsFunctionNamePredicate" => [[1.1, 3.1415926, 0.1], "is_int", true,],

            "typicalIteratorNoIntsClosurePredicate" => [self::createIterator([1.1, 3.1415926, 0.1]), $isInt, true,],
            "typicalIteratorNoIntsStaticMethodTuplePredicate" => [self::createIterator([1.1, 3.1415926, 0.1]), [self::class, "isInt"], true,],
            "typicalIteratorNoIntsFunctionNamePredicate" => [self::createIterator([1.1, 3.1415926, 0.1]), "is_int", true,],

            "typicalGeneratorNoIntsClosurePredicate" => [self::createGenerator([1.1, 3.1415926, 0.1]), $isInt, true,],
            "typicalGeneratorNoIntsStaticMethodTuplePredicate" => [self::createGenerator([1.1, 3.1415926, 0.1]), [self::class, "isInt"], true,],
            "typicalGeneratorNoIntsFunctionNamePredicate" => [self::createGenerator([1.1, 3.1415926, 0.1]), "is_int", true,],

            // ensure an unsatisfiable predicate works as expected
            "extremeArrayIntsClosureFalsePredicate" => [[1, 2, 3,], $false, true,],
            "extremeArrayIntsStaticMethodTupleFalsePredicate" => [[1, 2, 3,], [self::class, "alwaysFalse"], true,],

            "extremeIteratorIntsClosureFalsePredicate" => [self::createIterator([1, 2, 3,]), $false, true,],
            "extremeIteratorIntsStaticMethodTupleFalsePredicate" => [self::createIterator([1, 2, 3,]), [self::class, "alwaysFalse"], true,],

            "extremeGeneratorIntsClosureFalsePredicate" => [self::createGenerator([1, 2, 3,]), $false, true,],
            "extremeGeneratorIntsStaticMethodTupleFalsePredicate" => [self::createGenerator([1, 2, 3,]), [self::class, "alwaysFalse"], true,],

            // ensure an unfussy predicate works as expected
            "extremeArrayIntsClosureTruePredicate" => [[1, 2, 3,], $true, false,],
            "extremeArrayIntsStaticMethodTupleTruePredicate" => [[1, 2, 3,], [self::class, "alwaysTrue"], false,],

            "extremeIteratorIntsClosureTruePredicate" => [self::createIterator([1, 2, 3,]), $true, false,],
            "extremeIteratorIntsStaticMethodTupleTruePredicate" => [self::createIterator([1, 2, 3,]), [self::class, "alwaysTrue"], false,],

            "extremeGeneratorIntsClosureTruePredicate" => [self::createGenerator([1, 2, 3,]), $true, false,],
            "extremeGeneratorIntsStaticMethodTupleTruePredicate" => [self::createGenerator([1, 2, 3,]), [self::class, "alwaysTrue"], false,],

            // ensure empty arrays behave as expected
            "extremeEmptyArrayClosureFalsePredicate" => [[], $false, true,],
            "extremeEmptyArrayStaticMethodTupleFalsePredicate" => [[], [self::class, "alwaysFalse"], true,],

            "extremeEmptyIteratorClosureFalsePredicate" => [self::createIterator([]), $false, true,],
            "extremeEmptyIteratorStaticMethodTupleFalsePredicate" => [self::createIterator([]), [self::class, "alwaysFalse"], true,],

            "extremeEmptyGeneratorClosureFalsePredicate" => [self::createGenerator([]), $false, true,],
            "extremeEmptyGeneratorStaticMethodTupleFalsePredicate" => [self::createGenerator([]), [self::class, "alwaysFalse"], true,],

            "extremeEmptyArrayClosureTruePredicate" => [[], $true, true,],
            "extremeEmptyArrayStaticMethodTupleTruePredicate" => [[], [self::class, "alwaysTrue"], true,],

            "extremeEmptyIteratorClosureTruePredicate" => [self::createIterator([]), $true, true,],
            "extremeEmptyIteratorStaticMethodTupleTruePredicate" => [self::createIterator([]), [self::class, "alwaysTrue"], true,],

            "extremeEmptyGeneratorClosureTruePredicate" => [self::createGenerator([]), $true, true,],
            "extremeEmptyGeneratorStaticMethodTupleTruePredicate" => [self::createGenerator([]), [self::class, "alwaysTrue"], true,],
        ];
    }

    /**
     * Test none().
     *
     * @param iterable $collection The data to test with.
     * @param callable $predicate The predicate to test with.
     * @param bool $expected The expected return value from none()
     */
    #[DataProvider("providerTestNone1")]
    public function testNone1(iterable $collection, callable $predicate, bool $expected, ?string $exceptionClass = null): void
    {
        self::assertEquals($expected, none($collection, $predicate));
    }


    /** Test data for the some() function. */
    public static function providerTestSome1(): iterable
    {
        $true = fn ($value): bool => true;
        $false = fn ($value): bool => false;
        $isInt = fn ($value): bool => is_int($value);

        yield from [
            // predicate expected to pass for all items
            "typicalArrayAllIntsClosurePredicate" => [[1, 2, 3,], $isInt, true,],
            "typicalArrayAllIntsStaticMethodTuplePredicate" => [[1, 2, 3,], [self::class, "isInt"], true,],
            "typicalArrayAllIntsFunctionNamePredicate" => [[1, 2, 3,], "is_int", true,],

            "typicalIteratorAllIntsClosurePredicate" => [self::createIterator([1, 2, 3,]), $isInt, true,],
            "typicalIteratorAllIntsStaticMethodTuplePredicate" => [self::createIterator([1, 2, 3,]), [self::class, "isInt"], true,],
            "typicalIteratorAllIntsFunctionNamePredicate" => [self::createIterator([1, 2, 3,]), "is_int", true,],

            "typicalGeneratorAllIntsClosurePredicate" => [self::createGenerator([1, 2, 3,]), $isInt, true,],
            "typicalGeneratorAllIntsStaticMethodTuplePredicate" => [self::createGenerator([1, 2, 3,]), [self::class, "isInt"], true,],
            "typicalGeneratorAllIntsFunctionNamePredicate" => [self::createGenerator([1, 2, 3,]), "is_int", true,],

            // predicate expected to pass for some items
            "typicalArraySomeIntsClosurePredicate" => [[1, 3.1415926, 3,], $isInt, true,],
            "typicalArraySomeIntsStaticMethodTuplePredicate" => [[1, 3.1415926, 3,], [self::class, "isInt"], true,],
            "typicalArraySomeIntsFunctionNamePredicate" => [[1, 3.1415926, 3,], "is_int", true,],

            "typicalIteratorSomeIntsClosurePredicate" => [self::createIterator([1, 3.1415926, 3,]), $isInt, true,],
            "typicalIteratorSomeIntsStaticMethodTuplePredicate" => [self::createIterator([1, 3.1415926, 3,]), [self::class, "isInt"], true,],
            "typicalIteratorSomeIntsFunctionNamePredicate" => [self::createIterator([1, 3.1415926, 3,]), "is_int", true,],

            "typicalGeneratorSomeIntsClosurePredicate" => [self::createGenerator([1, 3.1415926, 3,]), $isInt, true,],
            "typicalGeneratorSomeIntsStaticMethodTuplePredicate" => [self::createGenerator([1, 3.1415926, 3,]), [self::class, "isInt"], true,],
            "typicalGeneratorSomeIntsFunctionNamePredicate" => [self::createGenerator([1, 3.1415926, 3,]), "is_int", true,],

            // predicate expected to fail for all items
            "typicalArrayNoIntsClosurePredicate" => [[1.1, 3.1415926, 0.1], $isInt, false,],
            "typicalArrayNoIntsStaticMethodTuplePredicate" => [[1.1, 3.1415926, 0.1], [self::class, "isInt"], false,],
            "typicalArrayNoIntsFunctionNamePredicate" => [[1.1, 3.1415926, 0.1], "is_int", false,],

            "typicalIteratorNoIntsClosurePredicate" => [self::createIterator([1.1, 3.1415926, 0.1]), $isInt, false,],
            "typicalIteratorNoIntsStaticMethodTuplePredicate" => [self::createIterator([1.1, 3.1415926, 0.1]), [self::class, "isInt"], false,],
            "typicalIteratorNoIntsFunctionNamePredicate" => [self::createIterator([1.1, 3.1415926, 0.1]), "is_int", false,],

            "typicalGeneratorNoIntsClosurePredicate" => [self::createGenerator([1.1, 3.1415926, 0.1]), $isInt, false,],
            "typicalGeneratorNoIntsStaticMethodTuplePredicate" => [self::createGenerator([1.1, 3.1415926, 0.1]), [self::class, "isInt"], false,],
            "typicalGeneratorNoIntsFunctionNamePredicate" => [self::createGenerator([1.1, 3.1415926, 0.1]), "is_int", false,],

            // ensure an unsatisfiable predicate works as expected
            "extremeArrayIntsClosureFalsePredicate" => [[1, 2, 3,], $false, false,],
            "extremeArrayIntsStaticMethodTupleFalsePredicate" => [[1, 2, 3,], [self::class, "alwaysFalse"], false,],

            "extremeIteratorIntsClosureFalsePredicate" => [self::createIterator([1, 2, 3,]), $false, false,],
            "extremeIteratorIntsStaticMethodTupleFalsePredicate" => [self::createIterator([1, 2, 3,]), [self::class, "alwaysFalse"], false,],

            "extremeGeneratorIntsClosureFalsePredicate" => [self::createGenerator([1, 2, 3,]), $false, false,],
            "extremeGeneratorIntsStaticMethodTupleFalsePredicate" => [self::createGenerator([1, 2, 3,]), [self::class, "alwaysFalse"], false,],

            // ensure an unfussy predicate works as expected
            "extremeArrayIntsClosureTruePredicate" => [[1, 2, 3,], $true, true,],
            "extremeArrayIntsStaticMethodTupleTruePredicate" => [[1, 2, 3,], [self::class, "alwaysTrue"], true,],

            "extremeIteratorIntsClosureTruePredicate" => [self::createIterator([1, 2, 3,]), $true, true,],
            "extremeIteratorIntsStaticMethodTupleTruePredicate" => [self::createIterator([1, 2, 3,]), [self::class, "alwaysTrue"], true,],

            "extremeGeneratorIntsClosureTruePredicate" => [self::createGenerator([1, 2, 3,]), $true, true,],
            "extremeGeneratorIntsStaticMethodTupleTruePredicate" => [self::createGenerator([1, 2, 3,]), [self::class, "alwaysTrue"], true,],

            // ensure empty arrays behave as expected
            "extremeEmptyArrayClosureFalsePredicate" => [[], $false, false,],
            "extremeEmptyArrayStaticMethodTupleFalsePredicate" => [[], [self::class, "alwaysFalse"], false,],

            "extremeEmptyIteratorClosureFalsePredicate" => [self::createIterator([]), $false, false,],
            "extremeEmptyIteratorStaticMethodTupleFalsePredicate" => [self::createIterator([]), [self::class, "alwaysFalse"], false,],

            "extremeEmptyGeneratorClosureFalsePredicate" => [self::createGenerator([]), $false, false,],
            "extremeEmptyGeneratorStaticMethodTupleFalsePredicate" => [self::createGenerator([]), [self::class, "alwaysFalse"], false,],

            "extremeEmptyArrayClosureTruePredicate" => [[], $true, false,],
            "extremeEmptyArrayStaticMethodTupleTruePredicate" => [[], [self::class, "alwaysTrue"], false,],

            "extremeEmptyIteratorClosureTruePredicate" => [self::createIterator([]), $true, false,],
            "extremeEmptyIteratorStaticMethodTupleTruePredicate" => [self::createIterator([]), [self::class, "alwaysTrue"], false,],

            "extremeEmptyGeneratorClosureTruePredicate" => [self::createGenerator([]), $true, false,],
            "extremeEmptyGeneratorStaticMethodTupleTruePredicate" => [self::createGenerator([]), [self::class, "alwaysTrue"], false,],
        ];
    }

    /**
     * Test some().
     *
     * @param mixed $collection The data to test with.
     * @param mixed $predicate The predicate to test with.
     * @param bool $expected The expected return value from some()
     */
    #[DataProvider("providerTestSome1")]
    public function testSome1(iterable $collection, callable $predicate, bool $expected): void
    {
        self::assertEquals($expected, some($collection, $predicate));
    }

    /** Test data for testIsSubsetOf1() */
    public static function providerTestIsSubsetOf1(): iterable
    {
        yield from [
            "typicalArrayArraySubset" => [[1, 2,], [1, 2, 3,], true,],
            "typicalArrayIteratorSubset" => [[1, 2,], self::createIterator([1, 2, 3,]), true,],
            "typicalArrayGeneratorSubset" => [[1, 2,], self::createGenerator([1, 2, 3,]), true,],

            "typicalIteratorArraySubset" => [self::createIterator([1, 2,]), [1, 2, 3,], true,],
            "typicalIteratorIteratorSubset" => [self::createIterator([1, 2,]),self::createIterator([1, 2, 3,]), true,],
            "typicalIteratorGeneratorSubset" => [self::createIterator([1, 2,]), self::createGenerator([1, 2, 3,]), true,],

            "typicalGeneratorArraySubset" => [self::createGenerator([1, 2,]), [1, 2, 3,], true,],
            "typicalGeneratorIteratorSubset" => [self::createGenerator([1, 2,]), self::createIterator([1, 2, 3,]), true,],
            "typicalGeneratorGenratorSubset" => [self::createGenerator([1, 2,]), self::createGenerator([1, 2, 3,]), true,],

            "typicalArrayArrayNotSubset" => [[1, 5,], [1, 2, 3,], false,],
            "typicalArrayIteratorNotSubset" => [[1, 5,], self::createIterator([1, 2, 3,]), false,],
            "typicalArrayGeneratorNotSubset" => [[1, 5,], self::createGenerator([1, 2, 3,]), false,],

            "typicalIteratorArrayNotSubset" => [self::createIterator([1, 5,]), [1, 2, 3,], false,],
            "typicalIteratorIteratorNotSubset" => [self::createIterator([1, 5,]), self::createIterator([1, 2, 3,]), false,],
            "typicalIteratorGeneratorNotSubset" => [self::createIterator([1, 5,]), self::createGenerator([1, 2, 3,]), false,],

            "typicalGeneratorArrayNotSubset" => [self::createGenerator([1, 5,]), [1, 2, 3,], false,],
            "typicalGeneratorIteratorNotSubset" => [self::createGenerator([1, 5,]), self::createIterator([1, 2, 3,]), false,],
            "typicalGeneratorGeneratorNotSubset" => [self::createGenerator([1, 5,]), self::createGenerator([1, 2, 3,]), false,],

            "extremeArrayArrayEmptySubset" => [[], [1, 2, 3,], true,],
            "extremeArrayIteratorEmptySubset" => [[], self::createIterator([1, 2, 3,]), true,],
            "extremeArrayGeneratorEmptySubset" => [[], self::createGenerator([1, 2, 3,]), true,],

            "extremeIteratorArrayEmptySubset" => [self::createIterator([]), [1, 2, 3,], true,],
            "extremeIteratorIteratorEmptySubset" => [self::createIterator([]), self::createIterator([1, 2, 3,]), true,],
            "extremeIteratorGeneratorEmptySubset" => [self::createIterator([]), self::createGenerator([1, 2, 3,]), true,],

            "extremeGeneratorArrayEmptySubset" => [self::createGenerator([]), [1, 2, 3,], true,],
            "extremeGeneratorIteratorEmptySubset" => [self::createGenerator([]), self::createIterator([1, 2, 3,]), true,],
            "extremeGeneratorGeneratorEmptySubset" => [self::createGenerator([]), self::createGenerator([1, 2, 3,]), true,],

            "extremeArrayArrayEmptySuperset" => [[1, 2, ], [], false,],
            "extremeArrayIteratorEmptySuperset" => [[1, 2, ], self::createIterator([]), false,],
            "extremeArrayGeneratorEmptySuperset" => [[1, 2, ], self::createGenerator([]), false,],

            "extremeIteratorArrayEmptySuperset" => [self::createIterator([1, 2,]), [], false,],
            "extremeIteratorIteratorEmptySuperset" => [self::createIterator([1, 2,]), self::createIterator([]), false,],
            "extremeIteratorGeneratorEmptySuperset" => [self::createIterator([1, 2,]), self::createGenerator([]), false,],

            "extremeGeneratorArrayEmptySuperset" => [self::createGenerator([1, 2,]), [], false,],
            "extremeGeneratorIteratorEmptySuperset" => [self::createGenerator([1, 2,]), self::createIterator([]), false,],
            "extremeGeneratorGeneratorEmptySuperset" => [self::createGenerator([1, 2,]), self::createGenerator([]), false,],

            "extremeArrayArrayEmptySubsetEmtpySuperset" => [[], [], true,],
            "extremeArrayIteratorEmptySubsetEmtpySuperset" => [[], self::createIterator([]), true,],
            "extremeArrayGeneratorEmptySubsetEmtpySuperset" => [[], self::createGenerator([]), true,],

            "extremeIteratorArrayEmptySubsetEmtpySuperset" => [self::createIterator([]), [], true,],
            "extremeIteratorIteratorEmptySubsetEmtpySuperset" => [self::createIterator([]), self::createIterator([]), true,],
            "extremeIteratorGeneratorEmptySubsetEmtpySuperset" => [self::createIterator([]), self::createGenerator([]), true,],

            "extremeGeneratorArrayEmptySubsetEmtpySuperset" => [self::createGenerator([]), [], true,],
            "extremeGeneratorIteratorEmptySubsetEmtpySuperset" => [self::createGenerator([]), self::createIterator([]), true,],
            "extremeGeneratorGeneratorEmptySubsetEmtpySuperset" => [self::createGenerator([]), self::createGenerator([]), true,],
        ];
    }

    /**
     * @param iterable $subset The dataset to test as a potential subset.
     * @param iterable $set The dataaset that the subset should be contained within.
     * @param bool $expected The expected return value from isSubsetOf
     */
    #[DataProvider("providerTestIsSubsetOf1")]
    public function testIsSubsetOf1(iterable $subset, iterable $set, bool $expected): void
    {
        self::assertEquals($expected, isSubsetOf($subset, $set));
    }

    /** Test data for testRecursiveCount1. */
    public static function providerTestRecursiveCount1(): iterable
    {
        yield from [
            "typicalFlatArray" => [[1, 2, 3,], 3,],
            "typicalNestedArrays" => [[[1, 2, 3,], [4, 5, 6,],], 6,],
            "typicalMixed" => [[[1, 2, 3,], 4, 5,], 5,],

            "typicalGenerator" => [self::createGenerator([1, 2, 3,]), 3,],
            "typicalArrayWithNestedGenerator" => [[4, [5, 6,], self::createGenerator([1, 2, 3,]),], 6,],

            "typicalIterator" => [self::createIterator([1, 2, 3,]), 3,],
            "typicalArrayWithNestedIterator" => [[4, [5, 6,], self::createIterator([1, 2, 3,]),], 6,],

            "extremeNestedwithOneEmptyArray" => [[[1, 2, 3,], 4, 5, [],], 5,],
            "extremeEmpty" => [[], 0,],
            "extremeNestedEmptyArrays" => [[[], [], [],], 0,],
            "extremeDeeplyNestedEmptyArrays" => [[[[[[],],],[[[],],],],[],], 0,],
        ];
    }

    /**
     * @param iterable $iterable The iterable to count.
     * @param int $expected The expected recursive count.
     */
    #[DataProvider("providerTestRecursiveCount1")]
    public function testRecursiveCount1(mixed $iterable, int $expected): void
    {
        self::assertEquals($expected, recursiveCount($iterable));
    }

    /** Provides iterables and predicates with the expected set of filtered items. */
    public static function providerFilteredIterables(): iterable
    {
        $truePredicate = static fn (mixed $value, string | int $key): bool => true;
        $falsePredicate = static fn (mixed $value, string | int $key): bool => false;
        $isString = static fn (mixed $value, string | int $key): bool => is_string($value);
        $matchKey = static fn (mixed $value, string | int $key): bool => "two" === $key;

        yield "empty-generator" => [self::createGenerator([]), $truePredicate, []];
        yield "empty-iterator" => [self::createIterator([]), $truePredicate, []];
        yield "empty-array" => [self::createGenerator([]), $truePredicate, []];
        yield "no-matches-generator" => [self::createGenerator([1, 2, 3]), $falsePredicate, []];
        yield "no-matches-iterator" => [self::createIterator([1, 2, 3]), $falsePredicate, []];
        yield "no-matches-array" => [[1, "two", 3.14], $falsePredicate, []];
        yield "all-matches-generator" => [self::createGenerator([1, "two", 3.14]), $truePredicate, [1, "two", 3.14]];
        yield "all-matches-iterator" => [self::createIterator([1, "two", 3.14]), $truePredicate, [1, "two", 3.14]];
        yield "all-matches-array" => [[1, "two", 3.14], $truePredicate, [1, "two" , 3.14]];
        yield "some-matches-generator" => [self::createGenerator([1, "two", 3.14]), $isString, [1 => "two"]];
        yield "some-matches-iterator" => [self::createIterator([1, "two", 3.14]), $isString, [1 => "two"]];
        yield "some-matches-array" => [[1, "two", 3.14], $isString, [1 => "two"]];
        yield "some-key-matches-generator" => [self::createGenerator(["first", "two" => 42, "pi" => 3.14]), $matchKey, ["two" => 42]];
        yield "some-key-matches-itarator" => [self::createIteratorWithKeys(["first", "two" => 42, "pi" => 3.14]), $matchKey, ["two" => 42]];
        yield "some-key-matches-array" => [["first", "two" => 42, "pi" => 3.14], $matchKey, ["two" => 42]];
    }

    /**
     * Ensure filter() yields the correct results.
     *
     * @param iterable $collection The iterable to filter.
     * @param callable $predicate The filtering predicate.
     * @param array $expected The expected filtered items.
     */
    #[DataProvider("providerFilteredIterables")]
    public function testFilter1(iterable $collection, callable $predicate, array $expected): void
    {
        self::assertSame($expected, toArray(filter($collection, $predicate)));
    }

    public static function providerTestPartition1(): iterable
    {
        $data = [1, 2, 3, 4, 5, 6, 7, 8, 9,];
        yield "array" => [$data,];
        yield "generator" => [self::createGenerator($data),];
        yield "iterator" => [self::createIterator($data),];
    }


    /** Ensure all types of iterable can be partitioned. */
    #[DataProvider("providerTestPartition1")]
    public function testPartition1(iterable $data): void
    {
        $predicate = static fn (int $value): bool => $value < 5;
        [$partition1, $partition2,] = partition($data, $predicate);
        self::assertSame([1, 2, 3, 4,], $partition1);
        self::assertSame([5, 6, 7, 8, 9,], $partition2);
    }
}
