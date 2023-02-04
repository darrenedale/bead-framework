<?php

namespace BeadTests\Exceptions\Testing;

use Bead\Exceptions\Testing\StaticXRayException;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StaticXRayExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    /** @var string The session ID to test with. */
    private const TesClassName = "Bead\\TestClass";

    /** Ensure the class name of the xrayed class can be set in the exception constructor. */
    public function testConstructor(): void
    {
        $exception = new StaticXRayException(self::TesClassName);
        self::assertEquals(self::TesClassName, $exception->getClassName());
        self::assertCode(0, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set an exception code in the constructor. */
    public function testConstructorWithCode(): void
    {
        $exception = new StaticXRayException(self::TesClassName, code: 42);
        self::assertEquals(self::TesClassName, $exception->getClassName());
        self::assertCode(42, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set an message in the constructor. */
    public function testConstructorWithMessage(): void
    {
        $exception = new StaticXRayException(self::TesClassName, message: "The meaning of life.");
        self::assertEquals(self::TesClassName, $exception->getClassName());
        self::assertCode(0, $exception);
        self::assertMessage("The meaning of life.", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set a previous exception in the constructor. */
    public function testConstructorWithPrevious(): void
    {
        $previous = new RuntimeException();
        $exception = new StaticXRayException(self::TesClassName, previous: $previous);
        self::assertEquals(self::TesClassName, $exception->getClassName());
        self::assertCode(0, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious($previous, $exception);
    }
}
