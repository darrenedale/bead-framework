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
        self::assertMessage("", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithTableNameAndMessage(): void
    {
        $err = new OrphanedJoinException("table", "Message.");
        self::assertEquals("table", $err->getTableName());
        self::assertMessage("Message.", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithTableNameMessageAndCode(): void
    {
        $err = new OrphanedJoinException("table", "Message.", 42);
        self::assertEquals("table", $err->getTableName());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithTableNameMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new OrphanedJoinException("table", "Message.", 42, $previous);
        self::assertEquals("table", $err->getTableName());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious($previous, $err);
    }
}
