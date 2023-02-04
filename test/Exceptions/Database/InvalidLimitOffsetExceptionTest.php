<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Bead\Exceptions\Database\InvalidLimitOffsetException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class InvalidLimitOffsetExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithLimitOffset(): void
    {
        $err = new InvalidLimitOffsetException(42);
        self::assertEquals(42, $err->getOffset());
        self::assertMessage("", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithLimitOffsetAndMessage(): void
    {
        $err = new InvalidLimitOffsetException(42, "Message.");
        self::assertEquals(42, $err->getOffset());
        self::assertMessage("Message.", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithLimitOffsetMessageAndCode(): void
    {
        $err = new InvalidLimitOffsetException(42, "Message.", 42);
        self::assertEquals(42, $err->getOffset());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithLimitOffsetMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidLimitOffsetException(42, "Message.", 42, $previous);
        self::assertEquals(42, $err->getOffset());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious($previous, $err);
    }
}
