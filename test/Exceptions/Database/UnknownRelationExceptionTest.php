<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Bead\Database\Model;
use Bead\Exceptions\Database\ModelPropertyCastException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class ModelPropertyCastExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithModelPropertyAndIntValue(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", 42);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals(42, $err->getValue());
        self::assertMessage("", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelModelPropertyIntValueAndMessage(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", 42, "Message.");
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals(42, $err->getValue());
        self::assertMessage("Message.", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelModelPropertyIntValueMessageAndCode(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", 42, "Message.", 42);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals(42, $err->getValue());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelModelPropertyIntValueMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new ModelPropertyCastException(Model::class, "foo", 42, "Message.", 42, $previous);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals(42, $err->getValue());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious($previous, $err);
    }

    public function testWithModelPropertyAndFloatValue(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", 3.1415927);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals(3.1415927, $err->getValue());
        self::assertMessage("", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelModelPropertyFloatValueAndMessage(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", 3.1415927, "Message.");
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals(3.1415927, $err->getValue());
        self::assertMessage("Message.", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelModelPropertyFloatValueMessageAndCode(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", 3.1415927, "Message.", 42);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals(3.1415927, $err->getValue());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelModelPropertyFloatValueMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new ModelPropertyCastException(Model::class, "foo", 3.1415927, "Message.", 42, $previous);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals(3.1415927, $err->getValue());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious($previous, $err);
    }

    public function testWithModelPropertyAndStringValue(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", "value");
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals("value", $err->getValue());
        self::assertMessage("", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelModelPropertyStringValueAndMessage(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", "value", "Message.");
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals("value", $err->getValue());
        self::assertMessage("Message.", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelModelPropertyStringValueMessageAndCode(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", "value", "Message.", 42);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals("value", $err->getValue());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelModelPropertyStringValueMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new ModelPropertyCastException(Model::class, "foo", "value", "Message.", 42, $previous);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals("value", $err->getValue());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious($previous, $err);
    }

    public function testWithModelPropertyAndArrayValue(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", ["foo", "bar",]);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals(["foo", "bar",], $err->getValue());
        self::assertMessage("", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelModelPropertyArrayValueAndMessage(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", ["foo", "bar",], "Message.");
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals(["foo", "bar",], $err->getValue());
        self::assertMessage("Message.", $err);
        self::assertCode(0, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelModelPropertyArrayValueMessageAndCode(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", ["foo", "bar",], "Message.", 42);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals(["foo", "bar",], $err->getValue());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious(null, $err);
    }

    public function testWithModelModelPropertyArrayValueMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new ModelPropertyCastException(Model::class, "foo", ["foo", "bar",], "Message.", 42, $previous);
        self::assertEquals(Model::class, $err->getModel());
        self::assertEquals("foo", $err->getProperty());
        self::assertEquals(["foo", "bar",], $err->getValue());
        self::assertMessage("Message.", $err);
        self::assertCode(42, $err);
        self::assertPrevious($previous, $err);
    }
}
