<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Equit\Exceptions\Database\UnrecognisedQueryOperatorException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class UnrecognisedQueryOperatorExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithOperator(): void
    {
        $err = new UnrecognisedQueryOperatorException("->");
        $this->assertEquals("->", $err->getOperator());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithOperatorAndMessage(): void
    {
        $err = new UnrecognisedQueryOperatorException("->", "Message.");
        $this->assertEquals("->", $err->getOperator());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithOperatorMessageAndCode(): void
    {
        $err = new UnrecognisedQueryOperatorException("->", "Message.", 42);
        $this->assertEquals("->", $err->getOperator());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithOperatorMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new UnrecognisedQueryOperatorException("->", "Message.", 42, $previous);
        $this->assertEquals("->", $err->getOperator());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }
}
