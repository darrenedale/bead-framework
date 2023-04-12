<?php

namespace BeadTests\Email;

use Bead\Email\Header;
use Bead\Email\Part;
use BeadTests\Framework\TestCase;
use InvalidArgumentException;

class PartTest extends TestCase
{
    /** @var string Content for the test fixture. */
    private const TestContent = "Test content.";

    /** @var string Test plain text part content to set. */
    private const PlainTextContent = "Other test content.";

    /** @var string Test HTML part content to set. */
    private const HtmlContent = "<html lang=\"en\"><head><title>Test Document</title></head><body><p>HTML content.</p></body></html>";

    /** @var Part The Part under test. */
    private Part $part;

    /** Extract an array of headers to key-value pairs in an associative array. */
    private static function headersToAssociativeArray(array $headers): array
    {
        $arr = [];

        foreach ($headers as $header) {
            $arr[$header->name()] = $header->value();
        }

        return $arr;
    }

    public function setUp(): void
    {
        $this->part = new Part(self::TestContent);
    }

    public function tearDown(): void
    {
        unset($this->part);
        parent::tearDown();
    }

    /** Ensure we can initialise a part with the expected default state. */
    public function testDefaultConstructor(): void
    {
        $part = new Part();
        self::assertEquals("", $part->content());
        self::assertEquals("text/plain", $part->contentType());
        self::assertEquals("quoted-printable", $part->contentEncoding());
    }

    /** Ensure we can initialise a part with content. */
    public function testConstructorWithContent(): void
    {
        $part = new Part(self::PlainTextContent);
        self::assertEquals(self::PlainTextContent, $part->content());
        self::assertEquals("text/plain", $part->contentType());
        self::assertEquals("quoted-printable", $part->contentEncoding());
    }

    /** Ensure we can initialise a part with content and content-type. */
    public function testConstructorWithContentType(): void
    {
        $part = new Part(self::HtmlContent, 'text/html');
        self::assertEquals(self::HtmlContent, $part->content());
        self::assertEquals("text/html", $part->contentType());
        self::assertEquals("quoted-printable", $part->contentEncoding());
    }

    /** Ensure we can initialise a part with content, content-type and transfer encoding. */
    public function testConstructorWithContentEncoding(): void
    {
        $part = new Part(self::HtmlContent, 'text/html', "8-bit");
        self::assertEquals(self::HtmlContent, $part->content());
        self::assertEquals("text/html", $part->contentType());
        self::assertEquals("8-bit", $part->contentEncoding());
    }

    /** Ensure we can set the content type. */
    public function testSetContentType(): void
    {
        self::assertNotEquals("text/html", $this->part->contentType());
        $this->part->setContentType("text/html");
        self::assertEquals("text/html", $this->part->contentType());
    }

    /** Ensure setting an invalid content type throws. */
    public function testSetContentTypeThrows(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Content type \"invalid-content-type\" is not valid.");
        $this->part->setContentType("invalid-content-type");
    }

    /** Ensure we can fetch the content type. */
    public function testContentType(): void
    {
        self::assertEquals("text/plain", $this->part->contentType());
    }

    /** Ensure we can retrieve the content encoding. */
    public function testSetContentEncoding(): void
    {
        self::assertNotEquals("8-bit", $this->part->contentEncoding());
        $this->part->setContentEncoding("8-bit");
        self::assertEquals("8-bit", $this->part->contentEncoding());
    }

    /** Ensure setting an invalid content encoding throws. */
    public function testSetContentEncodingThrows(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Content encoding \"\" is not valid.");
        $this->part->setContentEncoding("");
    }

    /** Ensure the content transfer encoding can be retrieved. */
    public function testContentEncoding(): void
    {
        self::assertEquals("quoted-printable", $this->part->contentEncoding());
    }

    /** Ensure the content can be set. */
    public function testSetContent(): void
    {
        self::assertNotEquals(self::PlainTextContent, $this->part->content());
        $this->part->setContent(self::PlainTextContent);
        self::assertEquals(self::PlainTextContent, $this->part->content());
    }

    /** Ensure the part content can be retrieved. */
    public function testContent(): void
    {
        self::assertEquals(self::TestContent, $this->part->content());
    }

    /** Ensure a header can be added using name and value. */
    public function testAddHeaderNameValue(): void
    {
        self::assertArrayNotHasEntry("header-1", "value-1", $this->part->headers());
        $this->part->addHeader("header-1", "value-1");
        self::assertArrayHasEntry("header-1", "value-1", self::headersToAssociativeArray($this->part->headers()));
    }

    /** Ensure a Header object can be added. */
    public function testAddHeader(): void
    {
        self::assertArrayNotHasEntry("header-1", "value-1", $this->part->headers());
        $this->part->addHeader(new Header("header-1", "value-1"));
        self::assertArrayHasEntry("header-1", "value-1", self::headersToAssociativeArray($this->part->headers()));
    }

    /** Ensure adding content-type header by name and value sets the content type. */
    public function testAddHeaderContentTypeNameValue(): void
    {
        self::assertNotEquals("text/html", $this->part->contentType());
        $this->part->addHeader("content-type", "text/html");
        self::assertEquals("text/html", $this->part->contentType());
    }

    /** Ensure adding content-type Header sets the content type. */
    public function testAddHeaderContentType(): void
    {
        self::assertNotEquals("text/html", $this->part->contentType());
        $this->part->addHeader(new Header("content-type", "text/html"));
        self::assertEquals("text/html", $this->part->contentType());
    }

    /** Ensure adding content-transfer-encoding header by name and value sets the content encoding. */
    public function testAddHeaderContentEncodingNameValue(): void
    {
        self::assertNotEquals("8-bit", $this->part->contentEncoding());
        $this->part->addHeader("content-transfer-encoding", "8-bit");
        self::assertEquals("8-bit", $this->part->contentEncoding());
    }

    /** Ensure adding content-transfer-encoding Header sets the content encoding. */
    public function testAddHeaderContentEncoding(): void
    {
        self::assertNotEquals("8-bit", $this->part->contentEncoding());
        $this->part->addHeader(new Header("content-transfer-encoding", "8-bit"));
        self::assertEquals("8-bit", $this->part->contentEncoding());
    }

    /** Ensure headers can be cleared. */
    public function testClearHeaders(): void
    {
        self::assertArrayNotHasEntry("header-1", "value-1", self::headersToAssociativeArray($this->part->headers()));
        $this->part->addHeader("header-1", "value-1");
        self::assertArrayHasEntry("header-1", "value-1", self::headersToAssociativeArray($this->part->headers()));
        $this->part->clearHeaders();
        self::assertArrayNotHasEntry("header-1", "value-1", self::headersToAssociativeArray($this->part->headers()));
    }

    /** Ensure clearing headers retains content-type and content-transfer-encoding. */
    public function testClearHeadersRetains(): void
    {
        self::assertArrayNotHasEntry("header-1", "value-1", self::headersToAssociativeArray($this->part->headers()));
        $this->part->addHeader("header-1", "value-1");
        self::assertArrayHasEntry("header-1", "value-1", self::headersToAssociativeArray($this->part->headers()));
        $this->part->clearHeaders();
        $headers = array_map(fn(Header $header): string => strtolower($header->name()), $this->part->headers());
        self::assertContains("content-type", $headers);
        self::assertContains("content-transfer-encoding", $headers);
    }
}
