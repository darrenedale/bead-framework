<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Equit\Exceptions\Database\InvalidLimitOffsetException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class InvalidLimitOffsetExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithLimitOffset(): void
    {
        $err = new InvalidLimitOffsetException(42);
        $this->assertEquals(42, $err->getOffset());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithLimitOffsetAndMessage(): void
    {
        $err = new InvalidLimitOffsetException(42, "Message.");
        $this->assertEquals(42, $err->getOffset());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithLimitOffsetMessageAndCode(): void
    {
        $err = new InvalidLimitOffsetException(42, "Message.", 42);
        $this->assertEquals(42, $err->getOffset());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithLimitOffsetMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidLimitOffsetException(42, "Message.", 42, $previous);
        $this->assertEquals(42, $err->getOffset());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }
}
