<?php

declare(strict_types=1);

namespace BeadTests\Web;

use Bead\Web\Header;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @covers \Bead\Web\Header */
class HeaderTest extends TestCase
{
    private const TestHeaderName = "Content-Type";

    private const TestHeaderValue = "application/json";

    private Header $header;

    protected function setUp(): void
    {
        $this->header = new Header(self::TestHeaderName, self::TestHeaderValue);
    }

    protected function tearDown(): void
    {
        unset($this->header);
    }

    /** Provider of valid header names. */
    public static function validNames(): iterable
    {
        yield "typical" => [self::TestHeaderName,];
        yield "upper-case" => [strtoupper(self::TestHeaderName),];
        yield "lower-case" => [strtolower(self::TestHeaderName),];
        yield "minimal" => ["a",];
        yield "several-hyphens" => ["X-Bead-Long-Test-Header-Name",];
        yield "numeric" => ["123"];
    }

    /** Provider of invalid header names. */
    public static function invalidNames(): iterable
    {
        yield "empty" => [""];
        yield "whitespace" => ["  "];
        yield "leading-whitespace" => [" content type"];
        yield "trailing-whitespace" => ["content-type  "];
        yield "surrounding-whitespace" => ["  content-type "];
        yield "internal-whitespace" => ["content type"];
        yield "invalid-characters" => ["\x10\x20\x30\x40\x50\x60\x70\x80\x90\xa0\xb0\xc0\xd0\xe0\xf0"];
    }

    /**
     * Ensure valid names are detected as such.
     * @dataProvider validNames
     */
    public function testIsValidName1(string $name): void
    {
        self::assertTrue(Header::isValidName($name));
    }

    /**
     * Ensure invalid names are detected as such.
     * @dataProvider invalidNames
     */
    public function testIsValidName2(string $name): void
    {
        self::assertFalse(Header::isValidName($name));
    }

    /** Ensure we can retrieve the correct name. */
    public function testName1(): void
    {
        self::assertSame(strtolower(self::TestHeaderName), strtolower($this->header->name()));
    }

    /** Ensure withName() does not return the same instance. */
    public function testWithName1(): void
    {
        self::assertNotSame($this->header, $this->header->withName("Content-Transfer-Encoding"));
    }

    /** Ensure withName() does not mutate the original. */
    public function testWithName2(): void
    {
        $this->header->withName("Content-Transfer-Encoding");
        self::assertSame(strtolower(self::TestHeaderName), strtolower($this->header->name()));
    }

    /** Ensure withName() provides a Header with the new name. */
    public function testWithName3(): void
    {
        $header = $this->header->withName("Content-Transfer-Encoding");
        self::assertSame("content-transfer-encoding", strtolower($header->name()));
    }

    /**
     * Ensure withName() throws with an invalid name.
     * @dataProvider invalidNames
     */
    public function testWithName4(string $name): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Expected valid header name, found \"{$name}\"");
        $this->header->withName($name);
    }

    /** Ensure we can retrieve the correct value. */
    public function testValue1(): void
    {
        self::assertSame(self::TestHeaderValue, $this->header->value());
    }

    /** Ensure withName() does not return the same instance. */
    public function testWithValue1(): void
    {
        self::assertNotSame($this->header, $this->header->withValue("text/html"));
    }

    /** Ensure withValue() does not mutate the original. */
    public function testWithValue2(): void
    {
        $this->header->withValue("text/html");
        self::assertSame(strtolower(self::TestHeaderValue), $this->header->Value());
    }

    /** Ensure withValue() provides a Header with the new value. */
    public function testWithValue3(): void
    {
        $header = $this->header->withValue("text/html");
        self::assertSame("text/html", $header->Value());
    }

    /** Ensure the header line is generated correctly. */
    public function testLine1(): void
    {
        self::assertSame("content-type: application/json", $this->header->line());
    }
}
