<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Bead\Exceptions\Database\InvalidColumnNameException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class InvalidColumnNameExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithColumnName(): void
    {
        $err = new InvalidColumnNameException("column");
        $this->assertEquals("column", $err->getColumnName());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithColumnNameAndMessage(): void
    {
        $err = new InvalidColumnNameException("column", "Message.");
        $this->assertEquals("column", $err->getColumnName());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithColumnNameMessageAndCode(): void
    {
        $err = new InvalidColumnNameException("column", "Message.", 42);
        $this->assertEquals("column", $err->getColumnName());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithColumnNameMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidColumnNameException("column", "Message.", 42, $previous);
        $this->assertEquals("column", $err->getColumnName());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }
}
