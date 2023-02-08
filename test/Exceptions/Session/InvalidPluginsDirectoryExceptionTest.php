<?php

declare(strict_types=1);

namespace BeadTests\Exceptions\Session;

use Bead\Exceptions\InvalidPluginsDirectoryException;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class InvalidPluginsDirectoryExceptionTest extends TestCase
{
	use AssertsCommonExceptionProperties;

	/** @var string The plugins directory to test with. */
	private const TestDirectory = "plugins/enabled";

	/** Ensure the directory can be set in the exception constructor. */
	public function testConstructor(): void
	{
		$exception = new InvalidPluginsDirectoryException(self::TestDirectory);
		self::assertEquals(self::TestDirectory, $exception->getDirectory());
		self::assertCode(0, $exception);
		self::assertMessage("", $exception);
		self::assertPrevious(null, $exception);
	}

	/** Ensure we can set an exception code in the constructor. */
	public function testConstructorWithCode(): void
	{
		$exception = new InvalidPluginsDirectoryException(self::TestDirectory, code: 42);
		self::assertEquals(self::TestDirectory, $exception->getDirectory());
		self::assertCode(42, $exception);
		self::assertMessage("", $exception);
		self::assertPrevious(null, $exception);
	}

	/** Ensure we can set an message in the constructor. */
	public function testConstructorWithMessage(): void
	{
		$exception = new InvalidPluginsDirectoryException(self::TestDirectory, message: "The meaning of life.");
		self::assertEquals(self::TestDirectory, $exception->getDirectory());
		self::assertCode(0, $exception);
		self::assertMessage("The meaning of life.", $exception);
		self::assertPrevious(null, $exception);
	}

	/** Ensure we can set a previous exception in the constructor. */
	public function testConstructorWithPrevious(): void
	{
		$previous = new RuntimeException();
		$exception = new InvalidPluginsDirectoryException(self::TestDirectory, previous: $previous);
		self::assertEquals(self::TestDirectory, $exception->getDirectory());
		self::assertCode(0, $exception);
		self::assertMessage("", $exception);
		self::assertPrevious($previous, $exception);
	}
}
