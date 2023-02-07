<?php

declare(strict_types=1);

namespace BeadTests\Exceptions;

use Bead\Exceptions\DuplicateRouteParameterNameException;
use BeadTests\Framework\TestCase;
use RuntimeException;

final class DuplicateRouteParameterNameExceptionTest extends TestCase
{
	use AssertsCommonExceptionProperties;

	/** Ensure the duplicate parameter and route can be set in the exception constructor. */
	public function testConstructor(): void
	{
		$exception = new DuplicateRouteParameterNameException("invalid-parameter", "/home/route");
		self::assertEquals("invalid-parameter", $exception->getParameterName());
		self::assertEquals("/home/route", $exception->getRoute());
		self::assertCode(0, $exception);
		self::assertMessage("", $exception);
		self::assertPrevious(null, $exception);
	}

	/** Ensure we can set an exception code in the constructor. */
	public function testConstructorWithCode(): void
	{
        $exception = new DuplicateRouteParameterNameException("invalid-parameter", "/home/route", code: 42);
        self::assertEquals("invalid-parameter", $exception->getParameterName());
        self::assertEquals("/home/route", $exception->getRoute());
        self::assertCode(42, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious(null, $exception);
	}

	/** Ensure we can set a message in the constructor. */
	public function testConstructorWithMessage(): void
	{
        $exception = new DuplicateRouteParameterNameException("invalid-parameter", "/home/route", message: "The meaning of life.");
        self::assertEquals("invalid-parameter", $exception->getParameterName());
        self::assertEquals("/home/route", $exception->getRoute());
        self::assertCode(0, $exception);
		self::assertMessage("The meaning of life.", $exception);
		self::assertPrevious(null, $exception);
	}

	/** Ensure we can set a previous exception in the constructor. */
	public function testConstructorWithPrevious(): void
	{
        $previous = new RuntimeException();
        $exception = new DuplicateRouteParameterNameException("invalid-parameter", "/home/route", previous: $previous);
        self::assertEquals("invalid-parameter", $exception->getParameterName());
        self::assertEquals("/home/route", $exception->getRoute());
		self::assertCode(0, $exception);
		self::assertMessage("", $exception);
		self::assertPrevious($previous, $exception);
	}
}
