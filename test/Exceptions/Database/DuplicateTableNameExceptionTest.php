<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Equit\Exceptions\Database\DuplicateTableNameException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class DuplicateTableNameExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithTableName(): void
    {
        $err = new DuplicateTableNameException("table");
        $this->assertEquals("table", $err->getTableName());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithTableNameAndMessage(): void
    {
        $err = new DuplicateTableNameException("table", "Message.");
        $this->assertEquals("table", $err->getTableName());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithTableNameMessageAndCode(): void
    {
        $err = new DuplicateTableNameException("table", "Message.", 42);
        $this->assertEquals("table", $err->getTableName());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithTableNameMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new DuplicateTableNameException("table", "Message.", 42, $previous);
        $this->assertEquals("table", $err->getTableName());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }
}
