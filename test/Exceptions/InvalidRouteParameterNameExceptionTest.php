<?php

declare(strict_types=1);

namespace BeadTests\Exceptions;

use Bead\Exceptions\InvalidRouteParameterNameException;
use BeadTests\Framework\TestCase;
use RuntimeException;

final class InvalidRouteParameterNameExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    /** Ensure the invalid parameter and route can be set in the exception constructor. */
    public function testConstructor(): void
    {
        $exception = new InvalidRouteParameterNameException("invalid parameter", "/home/route/{invalid parameter}");
        self::assertEquals("invalid parameter", $exception->getParameterName());
        self::assertEquals("/home/route/{invalid parameter}", $exception->getRoute());
        self::assertCode(0, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set an exception code in the constructor. */
    public function testConstructorWithCode(): void
    {
        $exception = new InvalidRouteParameterNameException("invalid parameter", "/home/route/{invalid parameter}", code: 42);
        self::assertEquals("invalid parameter", $exception->getParameterName());
        self::assertEquals("/home/route/{invalid parameter}", $exception->getRoute());
        self::assertCode(42, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set a message in the constructor. */
    public function testConstructorWithMessage(): void
    {
        $exception = new InvalidRouteParameterNameException("invalid parameter", "/home/route/{invalid parameter}", message: "The meaning of life.");
        self::assertEquals("invalid parameter", $exception->getParameterName());
        self::assertEquals("/home/route/{invalid parameter}", $exception->getRoute());
        self::assertCode(0, $exception);
        self::assertMessage("The meaning of life.", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set a previous exception in the constructor. */
    public function testConstructorWithPrevious(): void
    {
        $previous = new RuntimeException();
        $exception = new InvalidRouteParameterNameException("invalid parameter", "/home/route/{invalid parameter}", previous: $previous);
        self::assertEquals("invalid parameter", $exception->getParameterName());
        self::assertEquals("/home/route/{invalid parameter}", $exception->getRoute());
        self::assertCode(0, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious($previous, $exception);
    }
}
