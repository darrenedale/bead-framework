<?php

declare(strict_types=1);

namespace BeadTests\Exceptions;

use Bead\Exceptions\ConflictingRouteException;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;
use BeadTests\Framework\TestCase;
use RuntimeException;

class ConflictingRouteExceptionTest extends TestCase
{
	use AssertsCommonExceptionProperties;

	/** @var string The session ID to test with. */
	private const TestPath = "/notes/add";

	/** Ensure the session ID can be set in the exception constructor. */
	public function testConstructor(): void
	{
		$exception = new ConflictingRouteException(self::TestPath);
		self::assertEquals(self::TestPath, $exception->getPath());
		self::assertCode(0, $exception);
		self::assertMessage("", $exception);
		self::assertPrevious(null, $exception);
	}

	/** Ensure we can set an exception code in the constructor. */
	public function testConstructorWithCode(): void
	{
		$exception = new ConflictingRouteException(self::TestPath, code: 42);
		self::assertEquals(self::TestPath, $exception->getPath());
		self::assertCode(42, $exception);
		self::assertMessage("", $exception);
		self::assertPrevious(null, $exception);
	}

	/** Ensure we can set an message in the constructor. */
	public function testConstructorWithMessage(): void
	{
		$exception = new ConflictingRouteException(self::TestPath, message: "The meaning of life.");
		self::assertEquals(self::TestPath, $exception->getPath());
		self::assertCode(0, $exception);
		self::assertMessage("The meaning of life.", $exception);
		self::assertPrevious(null, $exception);
	}

	/** Ensure we can set a previous exception in the constructor. */
	public function testConstructorWithPrevious(): void
	{
		$previous = new RuntimeException();
		$exception = new ConflictingRouteException(self::TestPath, previous: $previous);
		self::assertEquals(self::TestPath, $exception->getPath());
		self::assertCode(0, $exception);
		self::assertMessage("", $exception);
		self::assertPrevious($previous, $exception);
	}
}
