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
        self::assertEquals("column", $err->getColumnName());
        self::assertMessage("", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithColumnNameAndMessage(): void
    {
        $err = new InvalidColumnNameException("column", "Message.");
        self::assertEquals("column", $err->getColumnName());
        self::assertMessage("Message.", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithColumnNameMessageAndCode(): void
    {
        $err = new InvalidColumnNameException("column", "Message.", 42);
        self::assertEquals("column", $err->getColumnName());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithColumnNameMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidColumnNameException("column", "Message.", 42, $previous);
        self::assertEquals("column", $err->getColumnName());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious($previous, $err);
    }
}
