<?php

declare(strict_types=1);

namespace BeadTests\Exceptions\Session;

use Bead\Exceptions\Session\InvalidSessionDirectoryException;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class InvalidSessionDirectoryExceptionTest extends TestCase
{
	use AssertsCommonExceptionProperties;

	/** @var string The session ID to test with. */
	private const TestDirectory = "/tmp/bead-sessions/bad-dir";

	/** Ensure the session ID can be set in the exception constructor. */
	public function testConstructor(): void
	{
		$exception = new InvalidSessionDirectoryException(self::TestDirectory);
		self::assertEquals(self::TestDirectory, $exception->getDirectory());
		self::assertCode($exception, 0);
		self::assertMessage($exception, "");
		self::assertPrevious($exception, null);
	}

	/** Ensure we can set an exception code in the constructor. */
	public function testConstructorWithCode(): void
	{
		$exception = new InvalidSessionDirectoryException(self::TestDirectory, code: 42);
		self::assertEquals(self::TestDirectory, $exception->getDirectory());
		self::assertCode($exception, 42);
		self::assertMessage($exception, "");
		self::assertPrevious($exception, null);
	}

	/** Ensure we can set an message in the constructor. */
	public function testConstructorWithMessage(): void
	{
		$exception = new InvalidSessionDirectoryException(self::TestDirectory, message: "The meaning of life.");
		self::assertEquals(self::TestDirectory, $exception->getDirectory());
		self::assertCode($exception, 0);
		self::assertMessage($exception, "The meaning of life.");
		self::assertPrevious($exception, null);
	}

	/** Ensure we can set a previous exception in the constructor. */
	public function testConstructorWithPrevious(): void
	{
		$previous = new RuntimeException();
		$exception = new InvalidSessionDirectoryException(self::TestDirectory, previous: $previous);
		self::assertEquals(self::TestDirectory, $exception->getDirectory());
		self::assertCode($exception, 0);
		self::assertMessage($exception, "");
		self::assertPrevious($exception, $previous);
	}
}
