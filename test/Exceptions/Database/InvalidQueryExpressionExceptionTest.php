<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Bead\Exceptions\Database\InvalidQueryExpressionException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class InvalidQueryExpressionExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithQueryExpression(): void
    {
        $err = new InvalidQueryExpressionException("foo -> bar");
        self::assertEquals("foo -> bar", $err->getExpression());
        self::assertMessage("", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithQueryExpressionAndMessage(): void
    {
        $err = new InvalidQueryExpressionException("foo -> bar", "Message.");
        self::assertEquals("foo -> bar", $err->getExpression());
        self::assertMessage("Message.", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithQueryExpressionMessageAndCode(): void
    {
        $err = new InvalidQueryExpressionException("foo -> bar", "Message.", 42);
        self::assertEquals("foo -> bar", $err->getExpression());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithQueryExpressionMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidQueryExpressionException("foo -> bar", "Message.", 42, $previous);
        self::assertEquals("foo -> bar", $err->getExpression());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious($previous, $err);
    }
}
