<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Equit\Database\Model;
use Equit\Exceptions\Database\UnknownRelationException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class UnknownRelationExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithModelAndRelation(): void
    {
        $err = new UnknownRelationException(Model::class, "foo");
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getRelation());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithModelRelationAndMessage(): void
    {
        $err = new UnknownRelationException(Model::class, "foo", "Message.");
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getRelation());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithOperatorMessageAndCode(): void
    {
        $err = new UnknownRelationException(Model::class, "foo", "Message.", 42);
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getRelation());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithOperatorMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new UnknownRelationException(Model::class, "foo", "Message.", 42, $previous);
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getRelation());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }
}
