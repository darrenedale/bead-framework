<?php

declare(strict_types=1);

namespace BeadTests\Email;

use Bead\Email\Header;
use BeadTests\Framework\TestCase;
use InvalidArgumentException;

class HeaderTest extends TestCase
{
    private Header $header;

    public function setUp(): void
    {
        $this->header = new Header("header-name", "header-value");
    }

    public function tearDown(): void
    {
        unset($this->header);
        parent::tearDown();
    }

    /** Ensure we can create a header. */
    public function testConstructor1(): void
    {
        $header = new Header("name", "value");
        self::assertEquals("name", $header->name());
        self::assertEquals("value", $header->value());
        self::assertEquals([], $header->parameters());
    }

    /** Ensure we can create a header with parameters. */
    public function testConstructor2(): void
    {
        $header = new Header("name", "value", ["parameter-name" => "parameter-value",]);
        self::assertEquals("name", $header->name());
        self::assertEquals("value", $header->value());
        self::assertEquals(["parameter-name" => "parameter-value",], $header->parameters());
    }

    /** Ensure constructor throws with invalid header name. */
    public function testConstructor3(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Invalid header name \"\".");
        new Header("", "value");
    }

    /** Ensure constructor throws with invalid parameter name. */
    public function testConstructor4(): void
    {
        if ("1" !== ini_get("zend.assertions")) {
            $this->markTestSkipped("Assertions are not enabled, Header constructor must fail an assertion for this test.");
        }

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("All header parameter names must be strings.");
        new Header("name", "value", ["parameter-value",]);
    }

    /** Ensure constructor throws with invalid parameter name. */
    public function testConstructor5(): void
    {
        if ("1" !== ini_get("zend.assertions")) {
            $this->markTestSkipped("Assertions are not enabled, Header constructor must fail an assertion for this test.");
        }

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("All header parameters must be strings.");
        new Header("name", "value", ["parameter-name" => 42,]);
    }

    /** Ensure we can set the header name. */
    public function testWithName(): void
    {
        $originalName = $this->header->name();
        self::assertNotEquals("other-header-name", $originalName);
        $header = $this->header->withName("other-header-name");
        self::assertNotSame($this->header, $header);
        self::assertEquals($originalName, $this->header->name());
        self::assertEquals("other-header-name", $header->name());
    }

    /** Ensure withName() throws with an invalid name. */
    public function testWithName2(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Invalid header name \"\".");
        $this->header->withName("");
    }

    /** Ensure we can retrieve the header name. */
    public function testName1(): void
    {
        self::assertEquals("header-name", $this->header->name());
    }

    /** Ensure we can set the header value. */
    public function testWithValue1(): void
    {
        $originalValue = $this->header->value();
        self::assertNotEquals("other-header-value", $originalValue);
        $header = $this->header->withValue("other-header-value");
        self::assertNotSame($this->header, $header);
        self::assertEquals($originalValue, $this->header->value());
        self::assertEquals("other-header-value", $header->value());
    }

    /** Ensure we can retrieve the header value. */
    public function testValue1(): void
    {
        self::assertEquals("header-value", $this->header->value());
    }

    /** Ensure we can set a header parameter. */
    public function testWithParameter1(): void
    {
        self::assertNull($this->header->parameter("parameter-name"));
        $header = $this->header->withParameter("parameter-name", "parameter-value");
        self::assertNotSame($this->header, $header);
        self::assertNull($this->header->parameter("parameter-name"));
        self::assertEquals("parameter-value", $header->parameter("parameter-name"));
    }

    /** Ensure we can retrieve a header parameter value. */
    public function testParameter1(): void
    {
        $header = $this->header->withParameter("parameter-name", "parameter-value");
        self::assertEquals("parameter-value", $header->parameter("parameter-name"));
    }

    /** Ensure we retrieve null when asking for the value for a header parameter that isn't set. */
    public function testParameter2(): void
    {
        self::assertNull($this->header->parameter("parameter-name"));
    }

    /** Ensure we can retrieve the correct parameter count. */
    public function testParameterCount1(): void
    {
        self::assertEquals(0, $this->header->parameterCount());
        $header = $this->header->withParameter("parameter-name", "parameter-value");
        self::assertEquals(1, $header->parameterCount());
    }

    /** Ensure we receive true when checking for an existing parameter. */
    public function testHasParameter1(): void
    {
        $header = $this->header->withParameter("parameter-name", "parameter-value");
        self::assertTrue($header->hasParameter("parameter-name"));
    }

    /** Ensure we receive false when checking for a parameter that isn't set. */
    public function testHasParameter2(): void
    {
        $header = $this->header->withParameter("parameter-name", "parameter-value");
        self::assertFalse($header->hasParameter("other-parameter-name"));
    }

    /** Ensure we can retrieve the parameters. */
    public function testParameters1(): void
    {
        $header = $this->header->withParameter("parameter-name", "parameter-value");
        self::assertEqualsCanonicalizing(["parameter-name" => "parameter-value",], $header->parameters());
    }

    /** Ensure we get an empty array when there are no parameters. */
    public function testParameters2(): void
    {
        self::assertEquals([], $this->header->parameters());
    }

    /** Ensure we can remove parameters. */
    public function testWithoutParameter1(): void
    {
        self::assertEquals([], $this->header->parameters());

        $header = $this->header
            ->withParameter("parameter-name-1", "parameter-value-1")
            ->withParameter("parameter-name-2", "parameter-value-2");

        self::assertEqualsCanonicalizing(["parameter-name-1" => "parameter-value-1", "parameter-name-2" => "parameter-value-2",], $header->parameters());
        $header = $header->withoutParameter("parameter-name-1");
        self::assertEqualsCanonicalizing(["parameter-name-2" => "parameter-value-2",], $header->parameters());
    }

    /** Ensure removing a parameter that is not set does not alter parameters. */
    public function testWithoutParameter2(): void
    {
        self::assertEquals([], $this->header->parameters());

        $header = $this->header
            ->withParameter("parameter-name-1", "parameter-value-1")
            ->withParameter("parameter-name-2", "parameter-value-2");

        self::assertEqualsCanonicalizing(["parameter-name-1" => "parameter-value-1", "parameter-name-2" => "parameter-value-2",], $header->parameters());
        $header = $header->withoutParameter("parameter-name-3");
        self::assertEqualsCanonicalizing(["parameter-name-1" => "parameter-value-1", "parameter-name-2" => "parameter-value-2",], $header->parameters());
    }

    /** Ensure we get the expected header line when casting the header to a string. */
    public function testToString1(): void
    {
        self::assertEquals("header-name: header-value", (string) $this->header);
        $header = $this->header
            ->withParameter("parameter-1", "value-1")
            ->withParameter("parameter-2", "value-2");

        self::assertEquals("header-name: header-value; parameter-1=value-1; parameter-2=value-2", (string) $header);
    }

    /** Ensure we generate the correct header line. */
    public function testLine1(): void
    {
        self::assertEquals("header-name: header-value", $this->header->line());

        $header = $this->header
            ->withParameter("parameter-1", "value-1")
            ->withParameter("parameter-2", "value-2");

        self::assertEquals("header-name: header-value; parameter-1=value-1; parameter-2=value-2", $header->line());
    }
}
