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
        $this->assertEquals("foo -> bar", $err->getExpression());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithQueryExpressionAndMessage(): void
    {
        $err = new InvalidQueryExpressionException("foo -> bar", "Message.");
        $this->assertEquals("foo -> bar", $err->getExpression());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithQueryExpressionMessageAndCode(): void
    {
        $err = new InvalidQueryExpressionException("foo -> bar", "Message.", 42);
        $this->assertEquals("foo -> bar", $err->getExpression());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithQueryExpressionMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidQueryExpressionException("foo -> bar", "Message.", 42, $previous);
        $this->assertEquals("foo -> bar", $err->getExpression());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }
}
