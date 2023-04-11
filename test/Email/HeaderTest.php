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
    public function testConstructor(): void
    {
        $header = new Header("name", "value");
        self::assertEquals("name", $header->name());
        self::assertEquals("value", $header->value());
        self::assertEquals([], $header->parameters());
    }

    /** Ensure we can create a header with parameters. */
    public function testConstructorWithParameters(): void
    {
        $header = new Header("name", "value", ["parameter-name" => "parameter-value",]);
        self::assertEquals("name", $header->name());
        self::assertEquals("value", $header->value());
        self::assertEquals(["parameter-name" => "parameter-value",], $header->parameters());
    }

    /** Ensure constructor throws with invalid header name. */
    public function testConstructorThrowsWithInvalidName(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Invalid header name \"\".");
        new Header("", "value");
    }

    /** Ensure constructor throws with invalid parameter name. */
    public function testConstructorThrowsWithInvalidParameterName(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("All header parameter names must be strings.");
        new Header("name", "value", ["parameter-value",]);
    }

    /** Ensure constructor throws with invalid parameter name. */
    public function testConstructorThrowsWithInvalidParameterValue(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("All header parameters must be strings.");
        new Header("name", "value", ["parameter-name" => 42,]);
    }

    /** Ensure we can set the header name. */
    public function testSetName(): void
    {
        self::assertNotEquals("other-header-name", $this->header->name());
        $this->header->setName("other-header-name");
        self::assertEquals("other-header-name", $this->header->name());
    }

    /** Ensure setName() throws with an invalid name. */
    public function testSetNameThrows(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Invalid header name \"\".");
        $this->header->setName("");
    }

    /** Ensure we can retrieve the header name. */
    public function testName(): void
    {
        self::assertEquals("header-name", $this->header->name());
    }

    /** Ensure we can set the header value. */
    public function testSetValue(): void
    {
        self::assertNotEquals("other-header-value", $this->header->value());
        $this->header->setValue("other-header-value");
        self::assertEquals("other-header-value", $this->header->value());
    }

    /** Ensure we can retrieve the header value. */
    public function testValue(): void
    {
        self::assertEquals("header-value", $this->header->value());
    }

    /** Ensure we can set a header parameter. */
    public function testSetParameter(): void
    {
        self::assertNull($this->header->parameter("parameter-name"));
        $this->header->setParameter("parameter-name", "parameter-value");
        self::assertEquals("parameter-value", $this->header->parameter("parameter-name"));
    }

    /** Ensure we can retrieve a header parameter value. */
    public function testParameter(): void
    {
        $this->header->setParameter("parameter-name", "parameter-value");
        self::assertEquals("parameter-value", $this->header->parameter("parameter-name"));
    }

    /** Ensure we retrieve null when asking for header parameter value that isn't set. */
    public function testParameterWithUnset(): void
    {
        self::assertNull($this->header->parameter("parameter-name"));
    }

    /** Ensure we can retrieve the correct parameter count. */
    public function testParameterCount(): void
    {
        self::assertEquals(0, $this->header->parameterCount());
        $this->header->setParameter("parameter-name", "parameter-value");
        self::assertEquals(1, $this->header->parameterCount());
    }

    /** Ensure we receive true when checking for an existing parameter. */
    public function testHasParameter(): void
    {
        $this->header->setParameter("parameter-name", "parameter-value");
        self::assertTrue($this->header->hasParameter("parameter-name"));
    }

    /** Ensure we receive false when checking for a parameter that isn't set. */
    public function testHasParameterWithUnset(): void
    {
        $this->header->setParameter("parameter-name", "parameter-value");
        self::assertFalse($this->header->hasParameter("other-parameter-name"));
    }

    /** Ensure we can retrieve the parameters. */
    public function testParameters(): void
    {
        $this->header->setParameter("parameter-name", "parameter-value");
        self::assertEqualsCanonicalizing(["parameter-name" => "parameter-value",], $this->header->parameters());
    }

    /** Ensure we get an empty array when there are no parameters. */
    public function testParametersEmpty(): void
    {
        self::assertEquals([], $this->header->parameters());
    }

    /** Ensure we can remove parameters. */
    public function testRemoveParameter(): void
    {
        self::assertEquals([], $this->header->parameters());
        $this->header->setParameter("parameter-name-1", "parameter-value-1");
        $this->header->setParameter("parameter-name-2", "parameter-value-2");
        self::assertEqualsCanonicalizing(["parameter-name-1" => "parameter-value-1", "parameter-name-2" => "parameter-value-2",], $this->header->parameters());
        $this->header->removeParameter("parameter-name-1");
        self::assertEqualsCanonicalizing(["parameter-name-2" => "parameter-value-2",], $this->header->parameters());
    }

    /** Ensure removing a parameter that is not set does not alter parameters. */
    public function testRemoveParameterNotSet(): void
    {
        self::assertEquals([], $this->header->parameters());
        $this->header->setParameter("parameter-name-1", "parameter-value-1");
        $this->header->setParameter("parameter-name-2", "parameter-value-2");
        self::assertEqualsCanonicalizing(["parameter-name-1" => "parameter-value-1", "parameter-name-2" => "parameter-value-2",], $this->header->parameters());
        $this->header->removeParameter("parameter-name-3");
        self::assertEqualsCanonicalizing(["parameter-name-1" => "parameter-value-1", "parameter-name-2" => "parameter-value-2",], $this->header->parameters());
    }

    /** Ensure we get the expected header line when casting the header to a string. */
    public function testToString(): void
    {
        self::assertEquals("header-name: header-value", (string) $this->header);
        $this->header->setParameter("parameter-1", "value-1");
        $this->header->setParameter("parameter-2", "value-2");
        self::assertEquals("header-name: header-value; parameter-1=value-1; parameter-2=value-2", (string) $this->header);
    }

    /** Ensure we generate the correct header line. */
    public function testGenerate(): void
    {
        self::assertEquals("header-name: header-value", $this->header->generate());
        $this->header->setParameter("parameter-1", "value-1");
        $this->header->setParameter("parameter-2", "value-2");
        self::assertEquals("header-name: header-value; parameter-1=value-1; parameter-2=value-2", $this->header->generate());
    }

    /**
     * Test data for testIsValidName().
     *
     * @return iterable The test data.
     */
    public function dataForTestIsValidName(): iterable
    {
        yield ["header-name", true,];
        yield ["", false,];
    }

    /**
     * Ensure isValidName() correctly identifies valid and invalid header names.
     *
     * @dataProvider dataForTestIsValidName
     *
     * @param string $name The name to test.
     * @param bool $expected The expected outcome.
     */
    public function testIsValidName(string $name, bool $expected): void
    {
        self::assertEquals($expected, Header::isValidName($name));
    }
}
