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
        $this->assertEquals(42, $err->getLimit());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithLimitAndMessage(): void
    {
        $err = new InvalidLimitException(42, "Message.");
        $this->assertEquals(42, $err->getLimit());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithLimitMessageAndCode(): void
    {
        $err = new InvalidLimitException(42, "Message.", 42);
        $this->assertEquals(42, $err->getLimit());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithLimitMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidLimitException(42, "Message.", 42, $previous);
        $this->assertEquals(42, $err->getLimit());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }
}
