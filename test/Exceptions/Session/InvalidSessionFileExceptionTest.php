<?php

namespace BeadTests\Exceptions\Session;

use Bead\Exceptions\Session\InvalidSessionFileException;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InvalidSessionFileExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    /** @var string The session ID to test with. */
    private const TesFilename = "/tmp/bead-framework/sessions/invalid-session-file";

    /** Ensure the ID of the missing session can be set in the exception constructor. */
    public function testConstructor(): void
    {
        $exception = new InvalidSessionFileException(self::TesFilename);
        self::assertEquals(self::TesFilename, $exception->getFileName());
        self::assertCode($exception, 0);
        self::assertMessage($exception, "");
        self::assertPrevious($exception, null);
    }

    /** Ensure we can set an exception code in the constructor. */
    public function testConstructorWithCode(): void
    {
        $exception = new InvalidSessionFileException(self::TesFilename, code: 42);
        self::assertEquals(self::TesFilename, $exception->getFileName());
        self::assertCode($exception, 42);
        self::assertMessage($exception, "");
        self::assertPrevious($exception, null);
    }

    /** Ensure we can set an message in the constructor. */
    public function testConstructorWithMessage(): void
    {
        $exception = new InvalidSessionFileException(self::TesFilename, message: "The meaning of life.");
        self::assertEquals(self::TesFilename, $exception->getFileName());
        self::assertCode($exception, 0);
        self::assertMessage($exception, "The meaning of life.");
        self::assertPrevious($exception, null);
    }

    /** Ensure we can set a previous exception in the constructor. */
    public function testConstructorWithPrevious(): void
    {
        $previous = new RuntimeException();
        $exception = new InvalidSessionFileException(self::TesFilename, previous: $previous);
        self::assertEquals(self::TesFilename, $exception->getFileName());
        self::assertCode($exception, 0);
        self::assertMessage($exception, "");
        self::assertPrevious($exception, $previous);
    }
}
