<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Bead\Exceptions\Database\OrphanedJoinException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class OrphanedJoinExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithTableName(): void
    {
        $err = new OrphanedJoinException("table");
        self::assertEquals("table", $err->getTableName());
        self::assertMessage($err, "");
        self::assertCode($err, 0);
        self::assertPrevious($err, null);
    }

    public function testWithTableNameAndMessage(): void
    {
        $err = new OrphanedJoinException("table", "Message.");
        self::assertEquals("table", $err->getTableName());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 0);
        self::assertPrevious($err, null);
    }

    public function testWithTableNameMessageAndCode(): void
    {
        $err = new OrphanedJoinException("table", "Message.", 42);
        self::assertEquals("table", $err->getTableName());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 42);
        self::assertPrevious($err, null);
    }

    public function testWithTableNameMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new OrphanedJoinException("table", "Message.", 42, $previous);
        self::assertEquals("table", $err->getTableName());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 42);
        self::assertPrevious($err, $previous);
    }
}
