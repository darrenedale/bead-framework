<?php

namespace BeadTests\Exceptions\Database;

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
        self::assertMessage("", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelRelationAndMessage(): void
    {
        $err = new UnknownRelationException(Model::class, "foo", "Message.");
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getRelation());
        self::assertMessage("Message.", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithOperatorMessageAndCode(): void
    {
        $err = new UnknownRelationException(Model::class, "foo", "Message.", 42);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getRelation());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithOperatorMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new UnknownRelationException(Model::class, "foo", "Message.", 42, $previous);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getRelation());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious($previous, $err);
    }
}
