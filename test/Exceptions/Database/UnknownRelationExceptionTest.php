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
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals(42, $err->getValue());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithModelModelPropertyIntValueAndMessage(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", 42, "Message.");
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals(42, $err->getValue());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithModelModelPropertyIntValueMessageAndCode(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", 42, "Message.", 42);
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals(42, $err->getValue());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithModelModelPropertyIntValueMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new ModelPropertyCastException(Model::class, "foo", 42, "Message.", 42, $previous);
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals(42, $err->getValue());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }

    public function testWithModelPropertyAndFloatValue(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", 3.1415927);
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals(3.1415927, $err->getValue());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithModelModelPropertyFloatValueAndMessage(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", 3.1415927, "Message.");
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals(3.1415927, $err->getValue());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithModelModelPropertyFloatValueMessageAndCode(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", 3.1415927, "Message.", 42);
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals(3.1415927, $err->getValue());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithModelModelPropertyFloatValueMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new ModelPropertyCastException(Model::class, "foo", 3.1415927, "Message.", 42, $previous);
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals(3.1415927, $err->getValue());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }

    public function testWithModelPropertyAndStringValue(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", "value");
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals("value", $err->getValue());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithModelModelPropertyStringValueAndMessage(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", "value", "Message.");
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals("value", $err->getValue());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithModelModelPropertyStringValueMessageAndCode(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", "value", "Message.", 42);
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals("value", $err->getValue());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithModelModelPropertyStringValueMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new ModelPropertyCastException(Model::class, "foo", "value", "Message.", 42, $previous);
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals("value", $err->getValue());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }

    public function testWithModelPropertyAndArrayValue(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", ["foo", "bar",]);
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals(["foo", "bar",], $err->getValue());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithModelModelPropertyArrayValueAndMessage(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", ["foo", "bar",], "Message.");
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals(["foo", "bar",], $err->getValue());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithModelModelPropertyArrayValueMessageAndCode(): void
    {
        $err = new ModelPropertyCastException(Model::class, "foo", ["foo", "bar",], "Message.", 42);
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals(["foo", "bar",], $err->getValue());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithModelModelPropertyArrayValueMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new ModelPropertyCastException(Model::class, "foo", ["foo", "bar",], "Message.", 42, $previous);
        $this->assertEquals(Model::class, $err->getModel());
        $this->assertEquals("foo", $err->getProperty());
        $this->assertEquals(["foo", "bar",], $err->getValue());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }
}
