<?php

declare(strict_types=1);

namespace BeadTests\Traversable;

use BeadTests\Framework\TestCase;
use TypeError;

use function Bead\Traversable\map;

final class TraversableTest extends TestCase
{
	public static function sqrt(float $value): float
	{
		return sqrt($value);
	}

	private static function iterableToArray(iterable $data): array
	{
		$dataArray = [];

		foreach ($data as $item) {
			$dataArray[] = $item;
		}

		return $dataArray;
	}

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
		$this->assertEquals(self::iterableToArray($expected), self::iterableToArray($actual));
	}
}
