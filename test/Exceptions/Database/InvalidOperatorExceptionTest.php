<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Equit\Exceptions\Database\InvalidOperatorException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class InvalidOperatorExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithOperator(): void
    {
        $err = new InvalidOperatorException("->");
        $this->assertEquals("->", $err->getOperator());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithOperatorAndMessage(): void
    {
        $err = new InvalidOperatorException("->", "Message.");
        $this->assertEquals("->", $err->getOperator());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithOperatorMessageAndCode(): void
    {
        $err = new InvalidOperatorException("->", "Message.", 42);
        $this->assertEquals("->", $err->getOperator());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithOperatorMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidOperatorException("->", "Message.", 42, $previous);
        $this->assertEquals("->", $err->getOperator());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }
}
