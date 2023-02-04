<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Bead\Exceptions\Database\UnrecognisedQueryOperatorException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class UnrecognisedQueryOperatorExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithOperator(): void
    {
        $err = new UnrecognisedQueryOperatorException("->");
        self::assertEquals("->", $err->getOperator());
        self::assertMessage($err, "");
        self::assertCode($err, 0);
        self::assertPrevious($err, null);
    }

    public function testWithOperatorAndMessage(): void
    {
        $err = new UnrecognisedQueryOperatorException("->", "Message.");
        self::assertEquals("->", $err->getOperator());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 0);
        self::assertPrevious($err, null);
    }

    public function testWithOperatorMessageAndCode(): void
    {
        $err = new UnrecognisedQueryOperatorException("->", "Message.", 42);
        self::assertEquals("->", $err->getOperator());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 42);
        self::assertPrevious($err, null);
    }

    public function testWithOperatorMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new UnrecognisedQueryOperatorException("->", "Message.", 42, $previous);
        self::assertEquals("->", $err->getOperator());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 42);
        self::assertPrevious($err, $previous);
    }
}
