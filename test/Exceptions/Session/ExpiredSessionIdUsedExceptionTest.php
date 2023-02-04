<?php

declare(strict_types=1);

namespace BeadTests\Exceptions\Session;

use Bead\Exceptions\Session\ExpiredSessionIdUsedException;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExpiredSessionIdUsedExceptionTest extends TestCase
{
	use AssertsCommonExceptionProperties;

	/** @var string The session ID to test with. */
	private const TestId = "fca40c6b-660a-4ddf-935a-31adb2aca09e";

	/** Ensure the session ID can be set in the exception constructor. */
	public function testConstructor(): void
	{
		$exception = new ExpiredSessionIdUsedException(self::TestId);
		self::assertEquals(self::TestId, $exception->getSessionId());
		self::assertCode($exception, 0);
		self::assertMessage($exception, "");
		self::assertPrevious($exception, null);
	}

	/** Ensure we can set an exception code in the constructor. */
	public function testConstructorWithCode(): void
	{
		$exception = new ExpiredSessionIdUsedException(self::TestId, code: 42);
		self::assertEquals(self::TestId, $exception->getSessionId());
		self::assertCode($exception, 42);
		self::assertMessage($exception, "");
		self::assertPrevious($exception, null);
	}

	/** Ensure we can set an message in the constructor. */
	public function testConstructorWithMessage(): void
	{
		$exception = new ExpiredSessionIdUsedException(self::TestId, message: "The meaning of life.");
		self::assertEquals(self::TestId, $exception->getSessionId());
		self::assertCode($exception, 0);
		self::assertMessage($exception, "The meaning of life.");
		self::assertPrevious($exception, null);
	}

	/** Ensure we can set a previous exception in the constructor. */
	public function testConstructorWithPrevious(): void
	{
		$previous = new RuntimeException();
		$exception = new ExpiredSessionIdUsedException(self::TestId, previous: $previous);
		self::assertEquals(self::TestId, $exception->getSessionId());
		self::assertCode($exception, 0);
		self::assertMessage($exception, "");
		self::assertPrevious($exception, $previous);
	}
}
