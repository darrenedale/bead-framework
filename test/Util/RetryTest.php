<?php

declare(strict_types=1);

namespace Equit\Test\Util;

use Equit\Test\Framework\TestCase;
use Equit\Util\Retry;
use InvalidArgumentException;

class RetryTest extends TestCase
{
	private static function createCallable(): callable
	{
		return function(){};
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

			// TODO invalid types
		];
	}

	public function testTimes($times, ?string $exceptionClass): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$retry = new Retry(self::createCallable());
		$actual = $retry->times($times);
		self::assertSame($retry, $actual);
		self::assertEquals($times, $actual->maxRetries());
	}

	public function testUntil(int $times, $predicate, ?string $exceptionClass): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$retry = (new Retry(self::createCallable()))
			->times($times);

		$actual = $retry->until($times);
		self::assertSame($retry, $actual);
	}

	public function testInvoke(): void
	{
		// TODO implement
	}

	public function testSetMaxRetries(): void
	{
		// TODO implement
	}

	public function testMaxRetries(): void
	{
		// TODO implement
	}

	public function testSetCallableToRetry(): void
	{
		// TODO implement
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
