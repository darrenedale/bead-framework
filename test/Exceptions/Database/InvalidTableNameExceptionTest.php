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
        self::assertMessage($err, "");
        self::assertCode($err, 0);
        self::assertPrevious($err, null);
    }

    public function testWithTableNameAndMessage(): void
    {
        $err = new InvalidTableNameException("table", "Message.");
        self::assertEquals("table", $err->getTableName());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 0);
        self::assertPrevious($err, null);
    }

    public function testWithTableNameMessageAndCode(): void
    {
        $err = new InvalidTableNameException("table", "Message.", 42);
        self::assertEquals("table", $err->getTableName());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 42);
        self::assertPrevious($err, null);
    }

    public function testWithTableNameMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidTableNameException("table", "Message.", 42, $previous);
        self::assertEquals("table", $err->getTableName());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 42);
        self::assertPrevious($err, $previous);
    }
}
