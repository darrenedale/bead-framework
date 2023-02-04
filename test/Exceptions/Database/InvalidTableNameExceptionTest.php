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
        self::assertEquals("table", $err->getTableName());
        self::assertMessage("", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithTableNameAndMessage(): void
    {
        $err = new InvalidTableNameException("table", "Message.");
        self::assertEquals("table", $err->getTableName());
        self::assertMessage("Message.", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithTableNameMessageAndCode(): void
    {
        $err = new InvalidTableNameException("table", "Message.", 42);
        self::assertEquals("table", $err->getTableName());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithTableNameMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidTableNameException("table", "Message.", 42, $previous);
        self::assertEquals("table", $err->getTableName());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious($previous, $err);
    }
}
