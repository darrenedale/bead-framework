<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Bead\Exceptions\Database\DuplicateColumnNameException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class DuplicateColumnNameExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithColumnName(): void
    {
        $err = new DuplicateColumnNameException("column");
        self::assertEquals("column", $err->getColumnName());
        self::assertMessage($err, "");
        self::assertCode($err, 0);
        self::assertPrevious($err, null);
    }

    public function testWithColumnNameAndMessage(): void
    {
        $err = new DuplicateColumnNameException("column", "Message.");
        self::assertEquals("column", $err->getColumnName());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 0);
        self::assertPrevious($err, null);
    }

    public function testWithColumnNameMessageAndCode(): void
    {
        $err = new DuplicateColumnNameException("column", "Message.", 42);
        self::assertEquals("column", $err->getColumnName());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 42);
        self::assertPrevious($err, null);
    }

    public function testWithColumnNameMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new DuplicateColumnNameException("column", "Message.", 42, $previous);
        self::assertEquals("column", $err->getColumnName());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 42);
        self::assertPrevious($err, $previous);
    }
}
