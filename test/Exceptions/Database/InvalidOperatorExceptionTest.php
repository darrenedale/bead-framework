<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Bead\Exceptions\Database\InvalidOperatorException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class InvalidOperatorExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithOperator(): void
    {
        $err = new InvalidOperatorException("->");
        self::assertEquals("->", $err->getOperator());
        self::assertMessage("", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithOperatorAndMessage(): void
    {
        $err = new InvalidOperatorException("->", "Message.");
        self::assertEquals("->", $err->getOperator());
        self::assertMessage("Message.", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithOperatorMessageAndCode(): void
    {
        $err = new InvalidOperatorException("->", "Message.", 42);
        self::assertEquals("->", $err->getOperator());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithOperatorMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidOperatorException("->", "Message.", 42, $previous);
        self::assertEquals("->", $err->getOperator());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious($previous, $err);
    }
}
