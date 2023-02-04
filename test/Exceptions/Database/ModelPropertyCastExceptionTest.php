<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Bead\Database\Model;
use Bead\Exceptions\Database\UnknownRelationException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class UnknownRelationExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithModelAndRelation(): void
    {
        $err = new UnknownRelationException(Model::class, "foo");
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getRelation());
        self::assertMessage($err, "");
        self::assertCode($err, 0);
        self::assertPrevious($err, null);
    }

    public function testWithModelRelationAndMessage(): void
    {
        $err = new UnknownRelationException(Model::class, "foo", "Message.");
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getRelation());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 0);
        self::assertPrevious($err, null);
    }

    public function testWithOperatorMessageAndCode(): void
    {
        $err = new UnknownRelationException(Model::class, "foo", "Message.", 42);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getRelation());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 42);
        self::assertPrevious($err, null);
    }

    public function testWithOperatorMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new UnknownRelationException(Model::class, "foo", "Message.", 42, $previous);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getRelation());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 42);
        self::assertPrevious($err, $previous);
    }
}
