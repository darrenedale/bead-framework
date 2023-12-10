<?php

namespace BeadTests\Email;

use Bead\Email\Header;
use Bead\Email\Part;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use InvalidArgumentException;

class PartTest extends TestCase
{
    use ConvertsHeadersToMaps;

    /** @var string Content for the test fixture. */
    private const TestContent = "Test content.";

    /** @var string Test plain text part content to set. */
    private const PlainTextContent = "Other test content.";

    /** @var string Test HTML part content to set. */
    private const HtmlContent = "<html lang=\"en\"><head><title>Test Document</title></head><body><p>HTML content.</p></body></html>";

    /** @var Part The Part under test. */
    private Part $part;

    public function setUp(): void
    {
        parent::setUp();
        $this->part = new Part(self::TestContent);
    }

    public function tearDown(): void
    {
        unset($this->part);
        parent::tearDown();
    }

    /** Ensure we can initialise a part with the expected default state. */
    public function testConstructor1(): void
    {
        $part = new Part();
        self::assertEquals("", $part->body());
        self::assertEquals("text/plain", $part->contentType());
        self::assertEquals("quoted-printable", $part->contentEncoding());
    }

    /** Ensure we can initialise a part with content. */
    public function testConstructor2(): void
    {
        $part = new Part(self::PlainTextContent);
        self::assertEquals(self::PlainTextContent, $part->body());
        self::assertEquals("text/plain", $part->contentType());
        self::assertEquals("quoted-printable", $part->contentEncoding());
    }

    /** Ensure we can initialise a part with content and content-type. */
    public function testConstructor3(): void
    {
        $part = new Part(self::HtmlContent, "text/html");
        self::assertEquals(self::HtmlContent, $part->body());
        self::assertEquals("text/html", $part->contentType());
        self::assertEquals("quoted-printable", $part->contentEncoding());
    }

    /** Ensure we can initialise a part with content, content-type and transfer encoding. */
    public function testConstructor4(): void
    {
        $part = new Part(self::HtmlContent, "text/html", "8-bit");
        self::assertEquals(self::HtmlContent, $part->body());
        self::assertEquals("text/html", $part->contentType());
        self::assertEquals("8-bit", $part->contentEncoding());
    }

    /** Ensure we can set the content type. */
    public function testWithContentType1(): void
    {
        self::assertNotEquals("text/html", $this->part->contentType());
        $part = $this->part->withContentType("text/html");
        self::assertNotSame($this->part, $part);
        self::assertEquals("text/plain", $this->part->contentType());
        self::assertEquals("text/html", $part->contentType());
    }

    public static function dataForTestWithContentType2(): iterable
    {
        yield "invalid" => ["invalid-content-type"];
        yield "nearly-valid" => ["application/x- something"];
        yield "empty" => [""];
    }

    /**
     * Ensure setting an invalid content type throws.
     *
     * @dataProvider dataForTestWithContentType2
     */
    public function testWithContentType2(string $contentType): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected valid media type, found \"{$contentType}\"");
        $this->part->withContentType($contentType);
    }

    /** Ensure leading and trailing whitespace is trimmed from content types. */
    public function testWithContentType3(): void
    {
        $part = $this->part->withContentType("  application/x-bead-test  ");
        self::assertEquals("application/x-bead-test", $part->contentType());
    }

    /** Ensure we can fetch the content type. */
    public function testContentType1(): void
    {
        self::assertEquals("text/plain", $this->part->contentType());
    }

    public static function dataForTestWithContentEncoding1(): iterable
    {
        yield "7bit" => ["7bit"];
        yield "7bit-variable-case" => ["7BiT"];
        yield "7bit-upper-case" => ["7BIT"];
        yield "8bit" => ["8bit"];
        yield "8bit-variable-case" => ["8BiT"];
        yield "8bit-upper-case" => ["8BIT"];
        yield "quoted-printable" => ["quoted-printable"];
        yield "quoted-printable-variable-case" => ["qUoteD-PRintABLE"];
        yield "quoted-printable-upper-case" => ["QUOTED-PRINTABLE"];
        yield "binary" => ["binary"];
        yield "binary-variable-case" => ["BInarY"];
        yield "binary-upper-case" => ["BINARY"];
        yield "base64" => ["base64"];
        yield "base64-upper-case" => ["bASe64"];
        yield "base64-variable-case" => ["BASE64"];
        yield "x-token" => ["x-bead-encoding"];
        yield "x-token-variable-case" => ["x-bEaD-ENcoDIng"];
        yield "x-token-upper-case" => ["X-BEAD-ENCODING"];
    }

    /** 
     * Ensure we can set the content encoding successfully.
     *
     * @dataProvider dataForTestWithContentEncoding1
     */
    public function testWithContentEncoding1(string $encoding): void
    {
        $originalEncoding = $this->part->contentEncoding();
        $part = $this->part->withContentEncoding($encoding);
        self::assertNotSame($this->part, $part);
        self::assertEquals($originalEncoding, $this->part->contentEncoding());
        self::assertEquals($encoding, $part->contentEncoding());
    }

    public static function dataForTestWithContentEncoding2(): iterable
    {
        yield "invalid" => ["invalid-encoding"];
        yield "almost-valid" => ["7-bit"];
        yield "almost-valid-x-token" => ["x- bead-encoding"];
    }

    /**
     * Ensure setting an invalid content encoding throws.
     *
     * @dataProvider dataForTestWithContentEncoding2
     */
    public function testWithContentEncoding2(string $encoding): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expecting valid content encoding, found \"{$encoding}\"");
        $this->part->withContentEncoding($encoding);
    }

    /** Ensure the content transfer encoding can be retrieved. */
    public function testContentEncoding1(): void
    {
        self::assertEquals("quoted-printable", $this->part->contentEncoding());
    }

    /** Ensure the body can be set. */
    public function testWithBody1(): void
    {
        $originalBody = $this->part->body();
        self::assertNotEquals(self::PlainTextContent, $this->part->body());
        $part = $this->part->withBody(self::PlainTextContent);
        self::assertNotSame($this->part, $part);
        self::assertEquals($originalBody, $this->part->body());
        self::assertEquals(self::PlainTextContent, $part->body());
    }

    /** Ensure the part body can be retrieved. */
    public function testBody1(): void
    {
        self::assertEquals(self::TestContent, $this->part->body());
    }

    /** Ensure body member is nullified if the message has parts. */
    public function testBody2(): void
    {
        $part = new XRay($this->part);
        $part->parts = [new Part(self::PlainTextContent),];
        self::assertIsString($part->body);
        $this->part->body();
        self::assertNull($part->body);
    }

    /** Ensure a header can be added using name and value. */
    public function testWithHeader1(): void
    {
        self::assertArrayNotHasEntry("header-1", "value-1", self::headersToAssociativeArray($this->part->headers()));
        $part = $this->part->withHeader("header-1", "value-1");
        self::assertNotSame($this->part, $part);
        self::assertArrayNotHasEntry("header-1", "value-1", self::headersToAssociativeArray($this->part->headers()));
        self::assertArrayHasEntry("header-1", "value-1", self::headersToAssociativeArray($part->headers()));
    }

    /** Ensure a Header object can be added. */
    public function testWithHeader2(): void
    {
        self::assertArrayNotHasEntry("header-1", "value-1", self::headersToAssociativeArray($this->part->headers()));
        $part = $this->part->withHeader(new Header("header-1", "value-1"));
        self::assertNotSame($this->part, $part);
        self::assertArrayNotHasEntry("header-1", "value-1", self::headersToAssociativeArray($this->part->headers()));
        self::assertArrayHasEntry("header-1", "value-1", self::headersToAssociativeArray($part->headers()));
    }

    /** Ensure adding content-type header by name and value sets the content type. */
    public function testWithHeader3(): void
    {
        $originalContentType = $this->part->contentType();
        self::assertNotEquals("text/html", $originalContentType);
        $part = $this->part->withHeader("content-type", "text/html");
        self::assertEquals($originalContentType, $this->part->contentType());
        self::assertEquals("text/html", $part->contentType());
    }

    /** Ensure adding content-type Header object sets the content type. */
    public function testWithHeader4(): void
    {
        $originalContentType = $this->part->contentType();
        self::assertNotEquals("text/html", $originalContentType);
        $part = $this->part->withHeader(new Header("content-type", "text/html"));
        self::assertEquals($originalContentType, $this->part->contentType());
        self::assertEquals("text/html", $part->contentType());
    }

    /** Ensure adding content-transfer-encoding header by name and value sets the content encoding. */
    public function testWithHeader5(): void
    {
        $originalContentEncoding = $this->part->contentEncoding();
        self::assertNotEquals("8bit", $originalContentEncoding);
        $part = $this->part->withHeader("content-transfer-encoding", "8bit");
        self::assertEquals($originalContentEncoding, $this->part->contentEncoding());
        self::assertEquals("8bit", $part->contentEncoding());
    }

    /** Ensure adding content-transfer-encoding Header object sets the content encoding. */
    public function testWithHeader6(): void
    {
        $originalContentEncoding = $this->part->contentEncoding();
        self::assertNotEquals("8bit", $originalContentEncoding);
        $part = $this->part->withHeader(new Header("content-transfer-encoding", "8bit"));
        self::assertEquals($originalContentEncoding, $this->part->contentEncoding());
        self::assertEquals("8bit", $part->contentEncoding());
    }
}
