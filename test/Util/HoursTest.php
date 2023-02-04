<?php

declare(strict_types=1);

namespace BeadTests\Util;

use BeadTests\Framework\TestCase;
use Bead\Util\Hours;
use TypeError;

class HoursTest extends TestCase
{
	/**
	 * Test data for testConstructor
	 *
	 *@return iterable The test data.
	 */
	public function dataForTestConstructor(): iterable
	{
		for ($hours = -72; $hours <= 72; ++$hours) {
			yield "typical{$hours}Hours" => [$hours,];
		}

		yield from [
			"extremeIntMax" => [PHP_INT_MAX,],
			"extremeIntMin" => [PHP_INT_MIN,],
			"invalidFloat" => [3.1415927, TypeError::class,],
			"invalidBool" => [true, TypeError::class,],
			"invalidNull" => [null, TypeError::class,],
			"invalidString" => ["42", TypeError::class,],
			"invalidArray" => [[42,], TypeError::class,],
			"invalidObject" => [
				new class
				{
					public function hours(): int
					{
						return 42;
					}
				},
				TypeError::class,
			],
			"invalidClosure" => [fn(): int => 42, TypeError::class,],
		];
	}

	/**
	 * Ensure the constructor throws with invalid data.
	 *
	 * @dataProvider dataForTestConstructor
	 *
	 * @param mixed $hours The hours to test with.
	 * @param string|null $exceptionCalss The exception expected, if any.
	 */
	public function testConstructor($hours, ?string $exceptionCalss = null): void
	{
		if (isset($exceptionCalss)) {
			$this->expectException($exceptionCalss);
		}

		$testObject = new Hours($hours);
		self::assertEquals($hours, $testObject->hours());
	}

	/**
	 * Test data for testConstructor
	 *
	 *@return iterable The test data.
	 */
	public function dataForTestHours(): iterable
	{
		for ($hours = -72; $hours <= 72; ++$hours) {
			yield "typical{$hours}Hours" => [$hours,];
		}

		yield from [
			"extremeIntMax" => [PHP_INT_MAX,],
			"extremeIntMin" => [PHP_INT_MIN,],
		];
	}

	/**
	 * @dataProvider dataForTestHours
	 *
	 * @param int $hours The number of hours to test with.
	 */
	public function testHours(int $hours): void
	{
		$testObject = new Hours($hours);
		self::assertEquals($hours, $testObject->hours());
	}

	/**
	 * Test data for testPlus().
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestPlus(): iterable
	{
		yield from [
			"typical0Positive" => [0, 42, 42,],
			"typical0Negative" => [0, -42, -42,],
			"typicalNegative0" => [-10, 10, 0,],
			"typicalPositive0" => [10, -10, 0,],
			"typicalNegativePositivePositive" => [-38, 51, 13,],
			"typicalNegativePositiveNegative" => [-38, 21, -17,],
			"typicalNegativeNegativeNegative" => [-38, -12, -50,],
			"typicalPositivePositivePositive" => [12, 14, 26,],
			"typicalPositiveNegativePositive" => [42, -21, 21,],
			"typicalPositiveNegativeNegative" => [15, -31, -16,],

			"invalidFloat" => [42, 3.1415927, 0, TypeError::class,],
			"invalidBool" => [42, true, 0, TypeError::class,],
			"invalidNull" => [42, null, 0, TypeError::class,],
			"invalidString" => [42, "42", 0, TypeError::class,],
			"invalidArray" => [42, [42,], 0, TypeError::class,],
			"invalidObject" => [
				42,
				new class
				{
					public function hours(): int
					{
						return 42;
					}
				},
				0,
				TypeError::class,
			],
			"invalidClosure" => [42, fn(): int => 42, 0, TypeError::class,],
		];
	}

	/**
	 * Ensure plus() performs the correct calculation and preserves immutability.
	 *
	 * @dataProvider dataForTestPlus
	 *
	 * @param int $initial The number of hours in the test object.
	 * @param mixed $add The value to add.
	 * @param int $expected The expected number of hours after the addition.
	 * @param string|null $exceptionClass The exception expected, if any.
	 */
	public function testPlus(int $initial, $add, int $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$hours = new Hours($initial);
		$actual = $hours->plus($add);
		self::assertNotSame($hours, $actual);
		self::assertEquals($initial, $hours->hours());
		self::assertEquals($expected, $actual->hours());
	}

	/**
	 * Test data for testPlus().
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestMinus(): iterable
	{
		yield from [
			"typical0Negative" => [0, 42, -42,],
			"typical0Positive" => [0, -42, 42,],
			"typicalNegative0" => [-10, -10, 0,],
			"typicalPositive0" => [10, 10, 0,],
			"typicalNegativeNegativePositive" => [-38, -51, 13,],
			"typicalNegativePositiveNegative" => [-38, 12, -50,],
			"typicalNegativeNegativeNegative" => [-38, -12, -26,],
			"typicalPositivePositivePositive" => [42, 14, 28,],
			"typicalPositiveNegativePositive" => [42, -21, 63,],
			"typicalPositivePositiveNegative" => [15, 31, -16,],

			"invalidFloat" => [42, 3.1415927, 0, TypeError::class,],
			"invalidBool" => [42, true, 0, TypeError::class,],
			"invalidNull" => [42, null, 0, TypeError::class,],
			"invalidString" => [42, "42", 0, TypeError::class,],
			"invalidArray" => [42, [42,], 0, TypeError::class,],
			"invalidObject" => [
				42,
				new class
				{
					public function hours(): int
					{
						return 42;
					}
				},
				0,
				TypeError::class,
			],
			"invalidClosure" => [42, fn(): int => 42, 0, TypeError::class,],
		];
	}

	/**
	 * Ensure minus() performs the correct calculation and preserves immutability.
	 *
	 * @dataProvider dataForTestMinus
	 *
	 * @param int $initial The number of hours in the test object.
	 * @param mixed $sub The value to subtract.
	 * @param int $expected The expected number of hours after the addition.
	 * @param string|null $exceptionClass The exception expected, if any.
	 */
	public function testMinus(int $initial, $sub, int $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$hours = new Hours($initial);
		$actual = $hours->minus($sub);
		self::assertNotSame($hours, $actual);
		self::assertEquals($initial, $hours->hours());
		self::assertEquals($expected, $actual->hours());
	}

	/**
	 * Test data for testInSeconds()
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestInSeconds(): iterable
	{
		for ($hours = -72; $hours <= 72; ++$hours)
			yield "typical{$hours}Hours" => [$hours, Hours::SecondsPerHour * $hours,];

	}

	/**
	 * Ensure the conversion behaves as expected.
	 * @dataProvider dataForTestInSeconds
	 */
	public function testInSeconds(int $hours, int $expectedSeconds): void
	{
		self::assertEquals($expectedSeconds, (new Hours($hours))->inSeconds());
	}
}
