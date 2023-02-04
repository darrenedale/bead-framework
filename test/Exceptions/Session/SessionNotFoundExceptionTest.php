<?php

namespace BeadTests\Exceptions\Session;

use Bead\Exceptions\Session\SessionNotFoundException;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SessionNotFoundExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    /** @var string The session ID to test with. */
    private const TestId = "fca40c6b-660a-4ddf-935a-31adb2aca09e";

    /** Ensure the ID of the missing session can be set in the exception constructor. */
    public function testConstructor(): void
    {
        $exception = new SessionNotFoundException(self::TestId);
        self::assertEquals(self::TestId, $exception->getSessionId());
        self::assertCode(0, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set an exception code in the constructor. */
    public function testConstructorWithCode(): void
    {
        $exception = new SessionNotFoundException(self::TestId, code: 42);
        self::assertEquals(self::TestId, $exception->getSessionId());
        self::assertCode(42, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set an message in the constructor. */
    public function testConstructorWithMessage(): void
    {
        $exception = new SessionNotFoundException(self::TestId, message: "The meaning of life.");
        self::assertEquals(self::TestId, $exception->getSessionId());
        self::assertCode(0, $exception);
        self::assertMessage("The meaning of life.", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set a previous exception in the constructor. */
    public function testConstructorWithPrevious(): void
    {
        $previous = new RuntimeException();
        $exception = new SessionNotFoundException(self::TestId, previous: $previous);
        self::assertEquals(self::TestId, $exception->getSessionId());
        self::assertCode(0, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious($previous, $exception);
    }
}
