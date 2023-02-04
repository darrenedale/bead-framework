<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Bead\Exceptions\Database\InvalidLimitException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class InvalidLimitExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithLimit(): void
    {
        $err = new InvalidLimitException(42);
        self::assertEquals(42, $err->getLimit());
        self::assertMessage("", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithLimitAndMessage(): void
    {
        $err = new InvalidLimitException(42, "Message.");
        self::assertEquals(42, $err->getLimit());
        self::assertMessage("Message.", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithLimitMessageAndCode(): void
    {
        $err = new InvalidLimitException(42, "Message.", 42);
        self::assertEquals(42, $err->getLimit());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithLimitMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidLimitException(42, "Message.", 42, $previous);
        self::assertEquals(42, $err->getLimit());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious($previous, $err);
    }
}
