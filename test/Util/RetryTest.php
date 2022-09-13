<?php

declare(strict_types=1);

namespace Equit\Test\Util;

use Equit\Test\Framework\TestCase;
use Equit\Util\Retry;
use InvalidArgumentException;
use TypeError;

class RetryTest extends TestCase
{
	private static function createCallable(): callable
	{
		return function(){};
	}

	public static function staticExitFunction($value): bool
	{
		return true;
	}

	public function exitFunction($value): bool
	{
		return true;
	}

	public static function staticCallableToRetry()
	{
		return null;
	}

	public function callableToRetry()
	{
		return null;
	}

	public function testConstructor(): void
	{
		$fn = self::createCallable();
		$retry = new Retry($fn);
		$this->assertSame($fn, $retry->callableToRetry());
		$this->assertSame(1, $retry->maxRetries());
		$this->assertNull($retry->exitCondition());
	}

	public function dataForTestTimes(): iterable
	{
		yield from [
			"typical5" => [5,],
			"extreme1" => [1,],
			"extremeIntMax" => [PHP_INT_MAX,],
			"invalid0" => [0, InvalidArgumentException::class,],
			"invalid-1" => [-1, InvalidArgumentException::class,],
			"invalidPhpIntMin" => [PHP_INT_MIN, InvalidArgumentException::class,],

			"invalidString" => ["5", TypeError::class],
			"invalidObject" => [
				new class
				{
					private int $m_retry = 5;

					public function retry(): int
					{
						return $this->m_retry;
					}
				},
				TypeError::class,
			],
			"invalidNull" => [null, TypeError::class],
			"invalidArray" => [[5,], TypeError::class],
			"invalidBoolean" => [[true,], TypeError::class],
		];
	}

	/**
	 * @dataProvider dataForTestTimes
	 *
	 * @param $times
	 * @param string|null $exceptionClass
	 */
	public function testTimes($times, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$retry = new Retry(self::createCallable());
		$actual = $retry->times($times);
		self::assertSame($retry, $actual);
		self::assertEquals($times, $actual->maxRetries());
	}

	public function dataForTestUntil(): iterable
	{
		yield from [
			"typicalLambda" => [5, fn(): bool => true,],
			"typicalStaticMethod" => [5, [self::class, "staticExitFunction"],],
			"typicalMethod" => [5, [$this, "exitFunction"],],
			"typicalInvokable" => [
				5,
				new class
				{
					public function __invoke($value): bool
					{
						return true;
					}
				},
			],
			"invalidString" => [5, "standaloneExitFunction", TypeError::class,],
			"invalidArray" => [5, ["standaloneExitFunction",], TypeError::class,],
			"invalidObject" => [5, new class {}, TypeError::class,],
			"invalidInt" => [5, 42, TypeError::class,],
			"invalidFloat" => [5, 3.1415927, TypeError::class,],
			"invalidBool" => [5, true, TypeError::class,],
			"invalidNull" => [5, null, TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestUntil
	 *
	 * @param int $times
	 * @param $predicate
	 * @param string|null $exceptionClass
	 *
	 * @return void
	 */
	public function testUntil(int $times, $predicate, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$retry = (new Retry(self::createCallable()))
			->times($times);

		$actual = $retry->until($predicate);
		self::assertSame($retry, $actual);
		self::assertSame($predicate, $retry->exitCondition());
	}

	public function testInvoke(): void
	{
		// TODO implement
	}

	public function dataForTestSetMaxRetries(): iterable
	{
		yield from $this->dataForTestMaxRetries();

		yield "invalidZero" => [0, InvalidArgumentException::class,];

		for ($retries = -1; $retries > -30; --$retries) {
			yield "invalid{$retries}" => [$retries, InvalidArgumentException::class,];
		}

		yield from [
			"invalidString" => ["5",TypeError::class,],
			"invalidArray" => [[5,], TypeError::class,],
			"invalidObject" => [
				new class {
					public function __toString(): string
					{
						return "5";
					}
				},
				TypeError::class,
			],
			"invalidClosure" => [fn(): int => 5, TypeError::class,],
			"invalidFloat" => [3.1415927, TypeError::class,],
			"invalidBool" => [true, TypeError::class,],
			"invalidNull" => [null, TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestSetMaxRetries
	 *
	 * @param int $retries
	 */
	public function testSetMaxRetries($retries, ?string $exceptionClass =  null): void
	{
		$retry = (new Retry(self::createCallable()));
		$retry();
		$this->assertEquals(1, $retry->attemptsTaken());

		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$retry->setMaxRetries($retries);
		$this->assertEquals($retries, $retry->maxRetries());
		$this->assertNull($retry->attemptsTaken());
	}


	public function dataForTestMaxRetries(): iterable
	{
		for ($retries = 1; $retries < 30; ++$retries) {
			yield "typical{$retries}" => [$retries,];
		}
	}

	/**
	 * @dataProvider dataForTestMaxRetries
	 * @param int $retries
	 */
	public function testMaxRetries(int $retries): void
	{
		$retry = (new Retry(self::createCallable()))
			->times($retries);

		$this->assertEquals($retries, $retry->maxRetries());
	}

	public function dataForTestSetCallableToRetry(): iterable
	{
		yield from [
			"typicalClosure" => [self::createCallable(),],
			"typicalStaticMethod" => [[self::class, 'staticCallableToRetry',],],
			"typicalMethod" => [[$this, 'callableToRetry',],],
			"typicalInvokable" => [
				new class {
					public function __invoke()
					{
						return null;
					}
				},
			],

			"invalidString" => ["5", TypeError::class,],
			"invalidArray" => [[5,], TypeError::class,],
			"invalidObject" => [
				new class {
					public function __toString(): string
					{
						return "5";
					}
				},
				TypeError::class,
			],
			"invalidInt" => [42, TypeError::class,],
			"invalidFloat" => [3.1415927, TypeError::class,],
			"invalidBool" => [true, TypeError::class,],
			"invalidNull" => [null, TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestSetCallableToRetry
	 *
	 * @param $callable
	 * @param string|null $exceptionClass
	 */
	public function testSetCallableToRetry($callable, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$retry = new Retry(self::createCallable());
		$retry->setCallableToRetry($callable);
		self::assertSame($callable, $retry->callableToRetry());
	}

	public function testCallableToRetry(): void
	{
		// TODO implement
	}

	public function testSetExitCondition(): void
	{
		// TODO implement
	}

	public function testExitCondition(): void
	{
		// TODO implement
	}

	public function testAttemptsTaken(): void
	{
		// TODO implement
	}

	public function testSucceeded(): void
	{
		// TODO implement
	}
}
