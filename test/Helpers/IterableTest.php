<?php

declare(strict_types=1);

namespace BeadTests\Helpers;

use ArrayIterator;
use BeadTests\Framework\TestCase;
use Error;
use Generator;
use Iterator;
use Traversable;
use TypeError;

use function Bead\Helpers\Iterable\accumulate;
use function Bead\Helpers\Iterable\all;
use function Bead\Helpers\Iterable\flatten;
use function Bead\Helpers\Iterable\grammaticalImplode;
use function Bead\Helpers\Iterable\implode;
use function Bead\Helpers\Iterable\isSubsetOf;
use function Bead\Helpers\Iterable\map;
use function Bead\Helpers\Iterable\none;
use function Bead\Helpers\Iterable\recursiveCount;
use function Bead\Helpers\Iterable\reduce;
use function Bead\Helpers\Iterable\some;
use function Bead\Helpers\Iterable\toArray;
use function Bead\Helpers\Iterable\transform;

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
	 * Helper to create a Traversable instance for testing.
	 *
	 * @param array $data The data the traversable will traverse.
	 *
	 * @return Traversable The test instance.
	 */
	private static function createIterator(array $data): Traversable
	{
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
	 * Helper to create a Generator instance for testing.
	 *
	 * @param array $data The data the generator will yield.
	 *
	 * @return Generator The test instance.
	 */
	private function createGenerator(array $data): Generator
	{
		yield from $data;
	}

	/**
	 * Test data for testMap()
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestMap(): iterable
	{
		yield from [
			"stringCallable" => [[1, 4, 9,], "sqrt", [1, 2, 3,],],
			"closureCallable" => [[1, 4, 9,], fn(int $value) => (int) sqrt($value), [1, 2, 3,],],
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

			"invalidNullCallable" => [[1, 4, 9,], null, [1, 2, 3,], TypeError::class,],
			"invalidAnonymousClassCallable" => [
				[1, 4, 9,],
				new class
				{
				},
				[1, 2, 3,],
				TypeError::class,
			],
			"invalidObjectClassCallable" => [[1, 4, 9,], (object) [], [1, 2, 3,], TypeError::class,],
			"invalidIntCallable" => [[1, 4, 9,], 42, [1, 2, 3,], TypeError::class,],
			"invalidFloatCallable" => [[1, 4, 9,], 3.1415926, [1, 2, 3,], TypeError::class,],
			"invalidEmptyStringCallable" => [[1, 4, 9,], "", [1, 2, 3,], TypeError::class,],
			"invalidEmptyArrayCallable" => [[1, 4, 9,], [], [1, 2, 3,], TypeError::class,],

			"invalidNullIterable" => [null, "sqrt", [1, 2, 3,], TypeError::class,],
			"invalidAnonymousClassIterable" => [
				new class
				{
				},
				"sqrt",
				[1, 2, 3,],
				TypeError::class,
			],
			"invalidObjectIterable" => [(object) [], "sqrt", [1, 2, 3,], TypeError::class,],
			"invalidIntCallable" => [42, "sqrt", [1, 2, 3,], TypeError::class,],
			"invalidFloatCallable" => [3.1415926, "sqrt", [1, 2, 3,], TypeError::class,],
			"invalidStringIterable" => ["1, 4, 9", "sqrt", [1, 2, 3,], TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestMap
	 *
	 * @param mixed $data The test data.
	 * @param mixed $fn The test mapping function.
	 * @param iterable $expected The expected mapped data.
	 * @param string|null $exceptionClass The exception class expected, if any.
	 */
	public function testMap($data, $fn, iterable $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = map($data, $fn);
		self::assertIsIterable($actual);
		self::assertEquals(toArray($expected), toArray($actual));
	}

	/**
	 * Test data for testFlatten()
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestFlatten(): iterable
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

			"invalidNull" => [null, [], TypeError::class,],
			"invalidString" => ["string", [], TypeError::class,],
			"invalidEmptyString" => ["", [], TypeError::class,],
			"invalidInt" => [42, [], TypeError::class,],
			"invalidFloat" => [3.1415926, [], TypeError::class,],
			"invalidTrue" => [true, [], TypeError::class,],
			"invalidFalse" => [false, [], TypeError::class,],
			"invalidAnonymousClass" => [
				new class
				{
				},
				[],
				TypeError::class,
			],
			"invalidObject" => [(object) [], [], TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestFlatten
	 *
	 * @param mixed $data The test data.
	 * @param iterable $expected The expected flattened iterable.
	 * @param string|null $exceptionClass The exception expected, if any.
	 */
	public function testFlatten($data, iterable $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = flatten($data);
		self::assertIsIterable($actual);
		self::assertEquals(toArray($expected), toArray($actual));
	}

	/**
	 * Test data for testToArray()
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestToArray(): iterable
	{
		yield from [
			"typicalTraversable" => [
				$this->createIterator([1, 2, 3,]),
				[1, 2, 3,],
			],
			"typicalEmptyTraversable" => [
				$this->createIterator([]),
				[],
			],
			"typicalGenerator" => [
				$this->createGenerator([1, 2, 3,]),
				[1, 2, 3,],
			],
			"typicalEmptyGenerator" => [
				$this->createGenerator([]),
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
			"invalidNull" => [null, [], TypeError::class,],
			"invalidString" => ["string", [], TypeError::class,],
			"invalidEmptyString" => ["", [], TypeError::class,],
			"invalidInt" => [42, [], TypeError::class,],
			"invalidFloat" => [3.1415926, [], TypeError::class,],
			"invalidTrue" => [true, [], TypeError::class,],
			"invalidFalse" => [false, [], TypeError::class,],
			"invalidAnonymousClass" => [
				new class
				{
				},
				[],
				TypeError::class,
			],
			"invalidObject" => [(object) [], [], TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestToArray
	 *
	 * @param mixed $data The test data.
	 * @param array $expected The expected array.
	 * @param string|null $exceptionClass The exception expected, if any.
	 */
	public function testToArray($data, array $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = toArray($data);
		self::assertIsArray($actual);
		self::assertEquals($expected, $actual);
	}


	/**
	 * Test data for testImplode()
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestImplode(): iterable
	{
		yield from [
			"typicalTraversableWithComma" => [
				$this->createIterator([1, 2, 3,]),
				",",
				"1,2,3",
			],
			"typicalTraversableWithSemicolon" => [
				$this->createIterator([1, 2, 3,]),
				";",
				"1;2;3",
			],
			"typicalTraversableWithDash" => [
				$this->createIterator([1, 2, 3,]),
				"-",
				"1-2-3",
			],
			"extremeTraversableWithLongString" => [
				$this->createIterator([1, 2, 3,]),
				"RIDICULOUSLY-LONG-GLUE",
				"1RIDICULOUSLY-LONG-GLUE2RIDICULOUSLY-LONG-GLUE3",
			],
			"extremeTraversableWithEmptyString" => [
				$this->createIterator([1, 2, 3,]),
				"",
				"123",
			],
			"typicalTraversableWithComma" => [
				$this->createIterator([1, 2, 3,]),
				",",
				"1,2,3",
			],
			"typicalTraversableWithCommaSpace" => [
				$this->createIterator([1, 2, 3,]),
				", ",
				"1, 2, 3",
			],
			"typicalEmptyTraversableComma" => [
				$this->createIterator([]),
				",",
				"",
			],
			"typicalEmptyTraversableCommaSpace" => [
				$this->createIterator([]),
				", ",
				"",
			],
			"typicalGenerator" => [
				$this->createGenerator([1, 2, 3,]),
				",",
				"1,2,3",
			],
			"typicalGenerator" => [
				$this->createGenerator([1, 2, 3,]),
				", ",
				"1, 2, 3",
			],
			"extremeGeneratorWithLongString" => [
				$this->createGenerator([1, 2, 3,]),
				"RIDICULOUSLY-LONG-GLUE",
				"1RIDICULOUSLY-LONG-GLUE2RIDICULOUSLY-LONG-GLUE3",
			],
			"extremeGeneratorWithEmptyString" => [
				$this->createGenerator([1, 2, 3,]),
				"",
				"123",
			],
			"typicalEmptyGeneratorComma" => [
				$this->createGenerator([]),
				",",
				""
			],
			"typicalEmptyGeneratorCommaSpace" => [
				$this->createGenerator([]),
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
			"invalidIterableNull" => [null, ",", "", TypeError::class,],
			"invalidIterableString" => ["string", ",", "", TypeError::class,],
			"invalidIterableEmptyString" => ["", ",", "", TypeError::class,],
			"invalidIterableInt" => [42, ",", "", TypeError::class,],
			"invalidIterableFloat" => [3.1415926, ",", "", TypeError::class,],
			"invalidIterableTrue" => [true, ",", "", TypeError::class,],
			"invalidIterableFalse" => [false, ",", "", TypeError::class,],
			"invalidIterableAnonymousClass" => [
				new class
				{
				},
				",",
				"",
				TypeError::class,
			],
			"invalidIterableObject" => [(object) [], ",", "", TypeError::class,],
			
			"invalidGlueNull" => [[], null, "", TypeError::class,],
			"invalidGlueInt" => [[], 42, "", TypeError::class,],
			"invalidGlueFloat" => [[], 3.1415926, "", TypeError::class,],
			"invalidGlueTrue" => [[], true, "", TypeError::class,],
			"invalidGlueFalse" => [[], false, "", TypeError::class,],
			"invalidGlueAnonymousClass" => [
				[],
				new class
				{
				},
				"",
				TypeError::class,
			],
			"invalidGlueObject" => [[], (object) [], "", TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestImplode
	 *
	 * @param mixed $iterable The iterable to test with.
	 * @param mixed $glue The glue to test with.
	 * @param string|null $exceptionClass The exception expected, if any.
	 */
	public function testImplode($iterable, $glue, string $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = implode($glue, $iterable);
		self::assertIsString($actual);
		self::assertEquals($expected, $actual);
	}


	/**
	 * Test data for testImplode()
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestGrammaticalImplode(): iterable
	{
		yield from [
			"typicalTraversableWithComma" => [
				$this->createIterator([1, 2, 3,]),
				", ",
				" and ",
				"1,2 and 3",
			],
			"typicalTraversableWithSemicolon" => [
				$this->createIterator([1, 2, 3,]),
				"; ",
				" or ",
				"1; 2 or 3",
			],
			"typicalTraversableWithDash" => [
				$this->createIterator([1, 2, 3,]),
				" - ",
				" and ",
				"1 - 2 and 3",
			],
			"typicalSingleItemTraversable" => [
				$this->createIterator(["foo",]),
				" - ",
				" and ",
				"foo",
			],
			"extremeTraversableWithLongString" => [
				$this->createIterator([1, 2, 3,]),
				"RIDICULOUSLY-LONG-GLUE",
				"RIDICULOUSLY-LONG-LAST-GLUE",
				"1RIDICULOUSLY-LONG-GLUE2RIDICULOUSLY-LONG-LAST-GLUE3",
			],
			"extremeTraversableWithEmptyGlue" => [
				$this->createIterator([1, 2, 3,]),
				"",
				" and ",
				"12 and 3",
			],
			"extremeTraversableWithEmptyLastGlue" => [
				$this->createIterator([1, 2, 3,]),
				", ",
				"",
				"1, 23",
			],
			"typicalTraversableWithComma" => [
				$this->createIterator([1, 2, 3,]),
				", ",
				" and ",
				"1, 2 and 3",
			],
			"typicalEmptyTraversableComma" => [
				$this->createIterator([]),
				", ",
				" and ",
				"",
			],
			"typicalGenerator" => [
				$this->createGenerator([1, 2, 3,]),
				", ",
				" and ",
				"1, 2 and 3",
			],
			"typicalGeneratorSemicolon" => [
				$this->createGenerator([1, 2, 3,]),
				"; ",
				" or ",
				"1; 2 or 3",
			],
			"typicalSingleItemGenerator" => [
				$this->createGenerator(["foo",]),
				" - ",
				" and ",
				"foo",
			],
			"extremeGeneratorWithLongString" => [
				$this->createGenerator([1, 2, 3,]),
				"RIDICULOUSLY-LONG-GLUE",
				"RIDICULOUSLY-LONG-LAST-GLUE",
				"1RIDICULOUSLY-LONG-GLUE2RIDICULOUSLY-LONG-LAST-GLUE3",
			],
			"extremeGeneratorWithEmptyGlue" => [
				$this->createGenerator([1, 2, 3,]),
				"",
				" and ",
				"12 and 3",
			],
			"extremeGeneratorWithEmptyLastGlue" => [
				$this->createGenerator([1, 2, 3,]),
				", ",
				"",
				"1, 23",
			],
			"extremeGeneratorWithEmptyGlues" => [
				$this->createGenerator([1, 2, 3,]),
				"",
				"",
				"123",
			],
			"typicalEmptyGeneratorComma" => [
				$this->createGenerator([]),
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
			
			"invalidIterableNull" => [null, ", ", " and ", "", TypeError::class,],
			"invalidIterableString" => ["string", ", ", " and ", "", TypeError::class,],
			"invalidIterableEmptyString" => ["", ", ", " and ", "", TypeError::class,],
			"invalidIterableInt" => [42, ", ", " and ", "", TypeError::class,],
			"invalidIterableFloat" => [3.1415926, ", ", " and ", "", TypeError::class,],
			"invalidIterableTrue" => [true, ", ", " and ", "", TypeError::class,],
			"invalidIterableFalse" => [false, ", ", " and ", "", TypeError::class,],
			"invalidIterableAnonymousClass" => [
				new class
				{
				},
				", ",
				" and ",
				"",
				TypeError::class,
			],
			"invalidIterableObject" => [(object) [], ", ", " and ", "", TypeError::class,],

			"invalidGlueNull" => [[], null, " and ", "", TypeError::class,],
			"invalidGlueInt" => [[], 42, " and ", "", TypeError::class,],
			"invalidGlueFloat" => [[], 3.1415926, " and ", "", TypeError::class,],
			"invalidGlueTrue" => [[], true, " and ", "", TypeError::class,],
			"invalidGlueFalse" => [[], false, " and ", "", TypeError::class,],
			"invalidGlueAnonymousClass" => [
				[],
				new class
				{
				},
				" and ",
				"",
				TypeError::class,
			],
			"invalidGlueObject" => [[], (object) [], " and ", "", TypeError::class,],

			"invalidLastGlueNull" => [[], ", ", null, "", TypeError::class,],
			"invalidLastGlueInt" => [[], ", ", 42, "", TypeError::class,],
			"invalidLastGlueFloat" => [[], ", ", 3.1415926, "", TypeError::class,],
			"invalidLastGlueTrue" => [[], ", ", true, "", TypeError::class,],
			"invalidLastGlueFalse" => [[], ", ", false, "", TypeError::class,],
			"invalidLastGlueAnonymousClass" => [
				[],
				", ", 
				new class
				{
				},
				"",
				TypeError::class,
			],
			"invalidLastGlueObject" => [[], ", ", (object) [], "", TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestGrammaticalImplode
	 *
	 * @param mixed $iterable The iterable to test with.
	 * @param mixed $glue The glue to test with.
	 * @param string|null $exceptionClass The exception expected, if any.
	 */
	public function testGrammaticalImplode($iterable, $glue, $lastGlue, string $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = grammaticalImplode($iterable, $glue, $lastGlue);
		self::assertIsString($actual);
		self::assertEquals($expected, $actual);
	}

	/**
	 * Ensure grammaticalImplode uses the correct default glues.
	 */
	public function testGrammaticalImplodeWithDefaultGlue(): void
	{
		$actual = grammaticalImplode(["red", "green", "blue"]);
		self::assertIsString($actual);
		self::assertEquals("red, green and blue", $actual);
	}

	/**
	 * Ensure grammaticalImplode uses the correct default last glue when a glue is given but no last glue.
	 */
	public function testGrammaticalImplodeWithDefaultLastGlue(): void
	{
		$actual = grammaticalImplode(["red", "green", "blue"], "; ");
		self::assertIsString($actual);
		self::assertEquals("red; green and blue", $actual);
	}

	/**
	 * The test data for testTransform().
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestTransform(): iterable
	{
		$sqrt = function (float $value): float {
			return sqrt($value);
		};

		$staticSqrt = [self::class, "sqrt"];

		// note Iterator implementations can't be traversed by reference
		yield from [
			"typicalArrayAndClosure" => [[1, 4, 9,], $sqrt, [1, 2, 3,],],
			"typicalArrayIteratorAndClosure" => [new ArrayIterator([1, 4, 9,]), $sqrt, [1, 2, 3,],],
			"typicalTraversableAndClosure" => [self::createIterator([1, 4, 9,]), $sqrt, [1, 2, 3,], Error::class,],
			"extremeEmptyArrayAndClosure" => [[], $sqrt, [],],
			"extremeEmptyArrayIteratorAndClosure" => [new ArrayIterator([]), $sqrt, [],],
			"extremeEmptyTraversableAndClosure" => [self::createIterator([]), $sqrt, [], Error::class,],
			"typicalArrayAndStaticMethod" => [[1, 4, 9,], $staticSqrt, [1, 2, 3,],],
			"typicalArrayIteratorAndStaticMethod" => [new ArrayIterator([1, 4, 9,]), $staticSqrt, [1, 2, 3,],],
			"typicalTraversableAndStaticMethod" => [self::createIterator([1, 4, 9,]), $staticSqrt, [1, 2, 3,], Error::class,],
			"extremeEmptyArrayAndStaticMethod" => [[], $staticSqrt, [],],
			"extremeEmptyArrayIteratorAndStaticMethod" => [new ArrayIterator([]), $staticSqrt, [],],
			"extremeEmptyTraversableAndStaticMethod" => [self::createIterator([]), $staticSqrt, [], Error::class,],
			"typicalArrayAndFunctionName" => [[1, 4, 9,], "sqrt", [1, 2, 3,],],
			"typicalArrayIteratorAndFunctionName" => [new ArrayIterator([1, 4, 9,]), "sqrt", [1, 2, 3,],],
			"typicalTraversableAndFunctionName" => [self::createIterator([1, 4, 9,]), "sqrt", [1, 2, 3,], Error::class,],
			"extremeEmptyArrayAndFunctionName" => [[], "sqrt", [],],
			"extremeEmptyArrayIteratorAndFunctionName" => [new ArrayIterator([]), "sqrt", [],],
			"extremeEmptyTraversableAndFunctionName" => [self::createIterator([]), "sqrt", [], Error::class,],
		];
	}

	/**
	 * @dataProvider dataForTestTransform
	 *
	 * @param mixed $data The test data to transform.
	 * @param mixed $fn The callable to test with.
	 * @param iterable $expected The expected transformed values.
	 * @param string|null $exceptionClass The exception expected, if any.
	 */
	public function testTransform($data, $fn, iterable $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = transform($data, $fn);
		self::assertSame($data, $actual);

		$expected = toArray($expected);
		$actual = toArray($actual);

		for ($idx = 0; $idx < count($expected); ++$idx) {
			self::assertEquals($expected[$idx], $actual[$idx]);
		}
	}

	/**
	 * Ensure that transform() works with generators, even though doing so renders the generator useless.
	 */
	public function testTransformWithGenerator(): void
	{
		$data = (function & (): Generator {
			$data = [1, 2, 3,];

			foreach ($data as & $item) {
				yield & $item;
			}
		})();

		$actual = transform($data, fn($value) => $value);
		self::assertSame($data, $actual);
	}

	/**
	 * Test data for testReduce().
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestReduce(): iterable
	{
		$product = fn(int $value, int $carry): int => $carry * $value;
		$max = fn(int $value, int $carry): int => max($value, $carry);

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

			// ensure invalid args are rejected
			"invalidIterableNull" => [null, $max, 1, 1, TypeError::class,],
			"invalidIterableString" => ["string", $max, 1, 1, TypeError::class,],
			"invalidIterableEmptyString" => ["", $max, 1, 1, TypeError::class,],
			"invalidIterableInt" => [42, $max, 1, 1, TypeError::class,],
			"invalidIterableFloat" => [3.1415926, $max, 1, 1, TypeError::class,],
			"invalidIterableTrue" => [true, $max, 1, 1, TypeError::class,],
			"invalidIterableFalse" => [false, $max, 1, 1, TypeError::class,],
			"invalidIterableAnonymousClass" => [
				new class
				{
				},
				$max,
				1,
				1,
				TypeError::class,
			],
			"invalidIterableObject" => [(object) [], $max, 1, 1, TypeError::class,],

			"invalidCallableNull" => [[1, 2, 3,], null, 1, 1, TypeError::class,],
			"invalidCallableString" => [[1, 2, 3,], "this_function_does_not_exist", 1, 1, TypeError::class,],
			"invalidCallableEmptyString" => [[1, 2, 3,], "", 1, 1, TypeError::class,],
			"invalidCallableInt" => [[1, 2, 3,], 42, 1, 1, TypeError::class,],
			"invalidCallableFloat" => [[1, 2, 3,], 3.1415926, 1, 1, TypeError::class,],
			"invalidCallableTrue" => [[1, 2, 3,], true, 1, 1, TypeError::class,],
			"invalidCallableFalse" => [[1, 2, 3,], false, 1, 1, TypeError::class,],
			"invalidCallableArray" => [[1, 2, 3,], [fn(): int => 0], 1, 1, TypeError::class,],
			"invalidCallableAnonymousClass" => [
				[1, 2, 3,],
				new class
				{
				},
				1,
				1,
				TypeError::class,
			],
			"invalidCallableObject" => [[1, 2, 3,], (object) [], 1, 1, TypeError::class,],
		];
	}

	/**
	 * Test reduce() function.
	 *
	 * @dataProvider dataForTestReduce
	 *
	 * @param mixed $data The test data to reduce.
	 * @param mixed $fn The function to do the reduction.
	 * @param mixed $init The starting value for the reduction to test with.
	 * @param mixed $expected The expected outcome.
	 * @param string|null $exceptionClass The exception expected, if any.
	 */
	public function testReduce($data, $fn, $init, $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = reduce($data, $fn, $init);
		self::assertEquals($expected, $actual);
	}

	/**
	 * Test data for testAccumulate().
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestAccumulate(): iterable
	{
		$product = fn(int $value, int $carry): int => $carry * $value;

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
			"extremeEmptyArray" => [[], $product, null, 0,],
			"extremeEmptyIterator" => [self::createIterator([]), $product, null, 0,],
			"extremeEmptyGenerator" => [self::createGenerator([]), $product, null, 0,],

			// ensure invalid args are rejected
			"invalidIterableNull" => [null, $product, 1, 1, TypeError::class,],
			"invalidIterableString" => ["string", $product, 1, 1, TypeError::class,],
			"invalidIterableEmptyString" => ["", $product, 1, 1, TypeError::class,],
			"invalidIterableInt" => [42, $product, 1, 1, TypeError::class,],
			"invalidIterableFloat" => [3.1415926, $product, 1, 1, TypeError::class,],
			"invalidIterableTrue" => [true, $product, 1, 1, TypeError::class,],
			"invalidIterableFalse" => [false, $product, 1, 1, TypeError::class,],
			"invalidIterableAnonymousClass" => [
				new class
				{
				},
				$product,
				1,
				1,
				TypeError::class,
			],
			"invalidIterableObject" => [(object) [], $product, 1, 1, TypeError::class,],

			"invalidCallableString" => [[1, 2, 3,], "this_function_does_not_exist", 1, 1, TypeError::class,],
			"invalidCallableEmptyString" => [[1, 2, 3,], "", 1, 1, TypeError::class,],
			"invalidCallableInt" => [[1, 2, 3,], 42, 1, 1, TypeError::class,],
			"invalidCallableFloat" => [[1, 2, 3,], 3.1415926, 1, 1, TypeError::class,],
			"invalidCallableTrue" => [[1, 2, 3,], true, 1, 1, TypeError::class,],
			"invalidCallableFalse" => [[1, 2, 3,], false, 1, 1, TypeError::class,],
			"invalidCallableArray" => [[1, 2, 3,], [fn(): int => 0], 1, 1, TypeError::class,],
			"invalidCallableAnonymousClass" => [
				[1, 2, 3,],
				new class
				{
				},
				1,
				1,
				TypeError::class,
			],
			"invalidCallableObject" => [[1, 2, 3,], (object) [], 1, 1, TypeError::class,],
		];
	}

	/**
	 * Test reduce() function.
	 *
	 * @dataProvider dataForTestAccumulate
	 *
	 * @param mixed $data The test data to reduce.
	 * @param mixed $fn The function to do the reduction.
	 * @param mixed $init The starting value for the reduction to test with. `null` indicates the default arg.
	 * @param mixed $expected The expected outcome.
	 * @param string|null $exceptionClass The exception expected, if any.
	 */
	public function testAccumulate($data, $fn, $init, $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$args = [$data, $fn,];

		if (isset($init)) {
			$args[] = $init;
		}

		$actual = accumulate(...$args);
		self::assertEquals($expected, $actual);
	}

	/**
	 * Test data for the all() function.
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestAll(): iterable
	{
		$true = fn($value): bool => true;
		$false = fn($value): bool => false;
		$isInt = fn($value): bool => is_int($value);
		
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

			// ensure invalid args are rejected
			"invalidIterableNull" => [null, $true, true, TypeError::class,],
			"invalidIterableString" => ["string", $true, true, TypeError::class,],
			"invalidIterableEmptyString" => ["", $true, true, TypeError::class,],
			"invalidIterableInt" => [42, $true, true, TypeError::class,],
			"invalidIterableFloat" => [3.1415926, $true, true, TypeError::class,],
			"invalidIterableTrue" => [true, $true, true, TypeError::class,],
			"invalidIterableFalse" => [false, $true, true, TypeError::class,],
			"invalidIterableAnonymousClass" => [
				new class
				{
				},
				$true,
				true,
				TypeError::class,
			],
			"invalidIterableObject" => [(object) [], $true, true, TypeError::class,],

			"invalidCallableString" => [[1, 2, 3,], "this_function_does_not_exist", true, TypeError::class,],
			"invalidCallableEmptyString" => [[1, 2, 3,], "", true, TypeError::class,],
			"invalidCallableInt" => [[1, 2, 3,], 42, true, TypeError::class,],
			"invalidCallableFloat" => [[1, 2, 3,], 3.1415926, true, TypeError::class,],
			"invalidCallableTrue" => [[1, 2, 3,], true, true, TypeError::class,],
			"invalidCallableFalse" => [[1, 2, 3,], false, true, TypeError::class,],
			"invalidCallableArray" => [[1, 2, 3,], [fn(): int => 0], true, TypeError::class,],
			"invalidCallableAnonymousClass" => [
				[1, 2, 3,],
				new class
				{
				},
				true,
				TypeError::class,
			],
			"invalidCallableObject" => [[1, 2, 3,], (object) [], true, TypeError::class,],
		];
	}

	/**
	 * Test all().
	 *
	 * @dataProvider dataForTestAll
	 *
	 * @param mixed $collection The data to test with.
	 * @param mixed $predicate The predicate to test with.
	 * @param bool $expected The expected return value from all()
	 * @param string|null $exceptionClass The expected exception, if any.
	 */
	public function testAll($collection, $predicate, bool $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}
		
		$actual = all($collection, $predicate);
		self::assertEquals($expected, $actual);
	}
	
	/**
	 * Test data for the none() function.
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestNone(): iterable
	{
		$true = fn($value): bool => true;
		$false = fn($value): bool => false;
		$isInt = fn($value): bool => is_int($value);

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

			// ensure invalid args are rejected
			"invalidIterableNull" => [null, $true, true, TypeError::class,],
			"invalidIterableString" => ["string", $true, true, TypeError::class,],
			"invalidIterableEmptyString" => ["", $true, true, TypeError::class,],
			"invalidIterableInt" => [42, $true, true, TypeError::class,],
			"invalidIterableFloat" => [3.1415926, $true, true, TypeError::class,],
			"invalidIterableTrue" => [true, $true, true, TypeError::class,],
			"invalidIterableFalse" => [false, $true, true, TypeError::class,],
			"invalidIterableAnonymousClass" => [
				new class
				{
				},
				$true,
				true,
				TypeError::class,
			],
			"invalidIterableObject" => [(object) [], $true, true, TypeError::class,],

			"invalidCallableString" => [[1, 2, 3,], "this_function_does_not_exist", true, TypeError::class,],
			"invalidCallableEmptyString" => [[1, 2, 3,], "", true, TypeError::class,],
			"invalidCallableInt" => [[1, 2, 3,], 42, true, TypeError::class,],
			"invalidCallableFloat" => [[1, 2, 3,], 3.1415926, true, TypeError::class,],
			"invalidCallableTrue" => [[1, 2, 3,], true, true, TypeError::class,],
			"invalidCallableFalse" => [[1, 2, 3,], false, true, TypeError::class,],
			"invalidCallableArray" => [[1, 2, 3,], [fn(): int => 0], true, TypeError::class,],
			"invalidCallableAnonymousClass" => [
				[1, 2, 3,],
				new class
				{
				},
				true,
				TypeError::class,
			],
			"invalidCallableObject" => [[1, 2, 3,], (object) [], true, TypeError::class,],
		];
	}

	/**
	 * Test none().
	 *
	 * @dataProvider dataForTestNone
	 *
	 * @param mixed $collection The data to test with.
	 * @param mixed $predicate The predicate to test with.
	 * @param bool $expected The expected return value from none()
	 * @param string|null $exceptionClass The expected exception, if any.
	 */
	public function testNone($collection, $predicate, bool $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = none($collection, $predicate);
		self::assertEquals($expected, $actual);
	}


	/**
	 * Test data for the some() function.
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestSome(): iterable
	{
		$true = fn($value): bool => true;
		$false = fn($value): bool => false;
		$isInt = fn($value): bool => is_int($value);

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

			// ensure invalid args are rejected
			"invalidIterableNull" => [null, $true, true, TypeError::class,],
			"invalidIterableString" => ["string", $true, true, TypeError::class,],
			"invalidIterableEmptyString" => ["", $true, true, TypeError::class,],
			"invalidIterableInt" => [42, $true, true, TypeError::class,],
			"invalidIterableFloat" => [3.1415926, $true, true, TypeError::class,],
			"invalidIterableTrue" => [true, $true, true, TypeError::class,],
			"invalidIterableFalse" => [false, $true, true, TypeError::class,],
			"invalidIterableAnonymousClass" => [
				new class
				{
				},
				$true,
				true,
				TypeError::class,
			],
			"invalidIterableObject" => [(object) [], $true, true, TypeError::class,],

			"invalidCallableString" => [[1, 2, 3,], "this_function_does_not_exist", true, TypeError::class,],
			"invalidCallableEmptyString" => [[1, 2, 3,], "", true, TypeError::class,],
			"invalidCallableInt" => [[1, 2, 3,], 42, true, TypeError::class,],
			"invalidCallableFloat" => [[1, 2, 3,], 3.1415926, true, TypeError::class,],
			"invalidCallableTrue" => [[1, 2, 3,], true, true, TypeError::class,],
			"invalidCallableFalse" => [[1, 2, 3,], false, true, TypeError::class,],
			"invalidCallableArray" => [[1, 2, 3,], [fn(): int => 0], true, TypeError::class,],
			"invalidCallableAnonymousClass" => [
				[1, 2, 3,],
				new class
				{
				},
				true,
				TypeError::class,
			],
			"invalidCallableObject" => [[1, 2, 3,], (object) [], true, TypeError::class,],
		];
	}

	/**
	 * Test some().
	 *
	 * @dataProvider dataForTestSome
	 *
	 * @param mixed $collection The data to test with.
	 * @param mixed $predicate The predicate to test with.
	 * @param bool $expected The expected return value from some()
	 * @param string|null $exceptionClass The expected exception, if any.
	 */
	public function testSome($collection, $predicate, bool $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = some($collection, $predicate);
		self::assertEquals($expected, $actual);
	}

	/**
	 * Test data for testIsSubsetOf()
	 * 
	 * @return iterable The test data.
	 */
	public function dataForTestIsSubsetOf(): iterable
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
			
			"invalidSupersetString" => [[1, 2, 3,], "[]", false, TypeError::class,],
			"invalidSupersetEmptyString" => [[1, 2, 3,], "", false, TypeError::class,],
			"invalidSupersetInt" => [[1, 2, 3,], 42, false, TypeError::class,],
			"invalidSupersetFloat" => [[1, 2, 3,], 3.1415926, false, TypeError::class,],
			"invalidSupersetTrue" => [[1, 2, 3,], true, false, TypeError::class,],
			"invalidSupersetFalse" => [[1, 2, 3,], false, false, TypeError::class,],
			"invalidSupersetAnonymousClass" => [
				[1, 2, 3,],
				new class
				{
				},
				false,
				TypeError::class,
			],
			"invalidSupersetObject" => [[1, 2, 3,], (object) [], false, TypeError::class,],
			"invalidSupersetClosure" => [[1, 2, 3,], fn() => [], false, TypeError::class,],
			
			"invalidSubsetString" => ["[1, 2]", [1, 2, 3,], false, TypeError::class,],
			"invalidSubsetEmptyString" => ["", [1, 2, 3,], false, TypeError::class,],
			"invalidSubsetInt" => [42, [1, 2, 3,], false, TypeError::class,],
			"invalidSubsetFloat" => [3.1415926, [1, 2, 3,], false, TypeError::class,],
			"invalidSubsetTrue" => [true, [1, 2, 3,], false, TypeError::class,],
			"invalidSubsetFalse" => [false, [1, 2, 3,], false, TypeError::class,],
			"invalidSubsetAnonymousClass" => [
				new class
				{
				},
				[1, 2, 3,],
				false,
				TypeError::class,
			],
			"invalidSubsetObject" => [(object) [], [1, 2, 3,], false, TypeError::class,],
			"invalidSubsetClosure" => [fn() => [], [1, 2, 3,], false, TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestIsSubsetOf
	 *
	 * @param mixed $subset The dataset to test as a potential subset.
	 * @param mixed $set The dataaset that the subset should be contained within.
	 * @param bool $expected The expected return value from isSubsetOf
	 * @param string|null $exceptionClass The expected exception, if any.
	 */
	public function testIsSubsetOf($subset, $set, bool $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = isSubsetOf($subset, $set);
		self::assertEquals($expected, $actual);
	}

	/**
	 * Test data for testRecursiveCount.
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestRecursiveCount(): iterable
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

			"invalidString" => ["foo", 0, TypeError::class,],
			"invalidInt" => [42, 0, TypeError::class,],
			"invalidFloat" => [3.1415927, 0, TypeError::class,],
			"invalidBoolean" => [true, 0, TypeError::class,],
			"invalidClosure" => [fn(): int  => 0, 0, TypeError::class,],
			"invalidObject" => [(object) [1, 2, 3,], 0, TypeError::class,],
			"invalidCountable" => [
				new class
				{
					public function count(): int
					{
						return 0;
					}
				},
				0,
				TypeError::class,
			],
		];
	}

	/**
	 * @dataProvider dataForTestRecursiveCount
	 *
	 * @param mixed $iterable The iterable to count.
	 * @param int $expected The expected recursive count.
	 * @param string|null $exceptionClass The type exception expected to be throw, if any.
	 */
	public function testRecursiveCount(mixed $iterable, int $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = recursiveCount($iterable);
		self::assertEquals($expected, $actual);
	}
}
