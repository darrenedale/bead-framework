<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Bead\Exceptions\Database\InvalidTableNameException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class InvalidTableNameExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithTableName(): void
    {
        $err = new InvalidTableNameException("table");
        $this->assertEquals("table", $err->getTableName());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithTableNameAndMessage(): void
    {
        $err = new InvalidTableNameException("table", "Message.");
        $this->assertEquals("table", $err->getTableName());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithTableNameMessageAndCode(): void
    {
        $err = new InvalidTableNameException("table", "Message.", 42);
        $this->assertEquals("table", $err->getTableName());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithTableNameMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidTableNameException("table", "Message.", 42, $previous);
        $this->assertEquals("table", $err->getTableName());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }
}
