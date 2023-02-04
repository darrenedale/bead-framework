<?php

declare(strict_types=1);

namespace BeadTests\Exceptions\Session;

use Bead\Exceptions\Session\InvalidSessionHandlerException;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class InvalidSessionHandlerExceptionTest extends TestCase
{
	use AssertsCommonExceptionProperties;

	/** @var string The session ID to test with. */
	private const TestHandler = "Bead\\Session\\Handlers\\InvalidHandler";

	/** Ensure the session ID can be set in the exception constructor. */
	public function testConstructor(): void
	{
		$exception = new InvalidSessionHandlerException(self::TestHandler);
		self::assertEquals(self::TestHandler, $exception->getHandler());
		self::assertCode(0, $exception);
		self::assertMessage("", $exception);
		self::assertPrevious(null, $exception);
	}

	/** Ensure we can set an exception code in the constructor. */
	public function testConstructorWithCode(): void
	{
		$exception = new InvalidSessionHandlerException(self::TestHandler, code: 42);
		self::assertEquals(self::TestHandler, $exception->getHandler());
		self::assertCode(42, $exception);
		self::assertMessage("", $exception);
		self::assertPrevious(null, $exception);
	}

	/** Ensure we can set an message in the constructor. */
	public function testConstructorWithMessage(): void
	{
		$exception = new InvalidSessionHandlerException(self::TestHandler, message: "The meaning of life.");
		self::assertEquals(self::TestHandler, $exception->getHandler());
		self::assertCode(0, $exception);
		self::assertMessage("The meaning of life.", $exception);
		self::assertPrevious(null, $exception);
	}

	/** Ensure we can set a previous exception in the constructor. */
	public function testConstructorWithPrevious(): void
	{
		$previous = new RuntimeException();
		$exception = new InvalidSessionHandlerException(self::TestHandler, previous: $previous);
		self::assertEquals(self::TestHandler, $exception->getHandler());
		self::assertCode(0, $exception);
		self::assertMessage("", $exception);
		self::assertPrevious($previous, $exception);
	}
}
