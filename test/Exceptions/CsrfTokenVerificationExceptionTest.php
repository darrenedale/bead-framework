<?php

declare(strict_types=1);

namespace BeadTests\Exceptions;

use Bead\Exceptions\CsrfTokenVerificationException;
use Bead\Request;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;
use BeadTests\Framework\TestCase;
use Mockery;
use RuntimeException;

final class CsrfTokenVerificationExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    private static function createRequest(): Request
    {
        return Mockery::mock(Request::class);
    }

    /** Ensure the erroneous request can be set in the exception constructor. */
    public function testConstructor(): void
    {
        $request = self::createRequest();
        $exception = new CsrfTokenVerificationException($request);
        self::assertSame($request, $exception->getRequest());
        self::assertCode(0, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set an exception code in the constructor. */
    public function testConstructorWithCode(): void
    {
        $request = self::createRequest();
        $exception = new CsrfTokenVerificationException($request, code: 42);
        self::assertSame($request, $exception->getRequest());
        self::assertCode(42, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set an message in the constructor. */
    public function testConstructorWithMessage(): void
    {
        $request = self::createRequest();
        $exception = new CsrfTokenVerificationException($request, message: "The meaning of life.");
        self::assertSame($request, $exception->getRequest());
        self::assertCode(0, $exception);
        self::assertMessage("The meaning of life.", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set a previous exception in the constructor. */
    public function testConstructorWithPrevious(): void
    {
        $previous = new RuntimeException();
        $request = self::createRequest();
        $exception = new CsrfTokenVerificationException($request, previous: $previous);
        self::assertSame($request, $exception->getRequest());
        self::assertCode(0, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious($previous, $exception);
    }
}
