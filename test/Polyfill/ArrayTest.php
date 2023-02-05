<?php

declare(strict_types=1);

namespace BeadTests\Polyfill;

use BeadTests\Framework\TestCase;
use function Bead\Polyfill\array_is_list;

final class ArrayTest extends TestCase
{
	public function dataForTestArrayIsList(): iterable
	{
		yield "empty" => [[], true];
		yield "singleElement" => [[42], true];
		yield "manyElements" => [[42, 3.1415926, "hitch-hiker", "life", "how many roads?",], true];
		yield "map" => [
			[
				"answer" => 42,
				"pi" => 3.1415926,
				"source" => "hitch-hiker",
				"question" => "life",
				"actual-question" => "how many roads?",
			],
			false,
		];
		yield "almostListEnd" => [[0 => 42, 1 => 3.1415926, 2 => "hitch-hiker", 3 => "life", 5 => "how many roads?",], false];
		yield "almostListStart" => [[1 => 42, 2 => 3.1415926, 3 => "hitch-hiker", 4 => "life", 5 => "how many roads?",], false];
		yield "almostListNegative" => [[-1 => 42, 0 => 3.1415926, 1 => "hitch-hiker", 2 => "life", 3 => "how many roads?",], false];
	}

	/**
	 * @dataProvider dataForTestArrayIsList
	 *
	 * @param array $testArray The array to test with.
	 * @param bool $expected The expected result.
	 */
	public function testArrayIsList(array $testArray, bool $expected): void
	{
		self::assertEquals($expected, array_is_list($testArray));
	}
}
