<?php

declare(strict_types=1);

namespace BeadTests\Email;

use Bead\Email\Header;
use Bead\Email\Message;
use Bead\Email\Part;
use Bead\Testing\StaticXRay;
use Bead\Testing\XRay;
use InvalidArgumentException;
use BeadTests\Framework\TestCase;

final class MessageTest extends TestCase
{
    use ConvertsHeadersToMaps;

    private const TestMessageSender = "sender@example.com";

    private const TestMessageRecipient = "recipient@example.com";

    private const TestMessageCarbonCopy = "cc-recipient@example.com";

    private const TestMessageBlindCarbonCopy = "bcc-recipient@example.com";

    private const TestMessageSubject = "An example message subject.";

    private const TestMessagePlainText = "An example message.";

    private Message $message;

    public function setUp(): void
    {
        $this->message = (new Message(self::TestMessageRecipient, self::TestMessageSubject, self::TestMessagePlainText, self::TestMessageSender))
            ->withCc(self::TestMessageCarbonCopy)
            ->withBcc(self::TestMessageBlindCarbonCopy);
    }

    public function tearDown(): void
    {
        unset($this->message);
        parent::tearDown();
    }

    /** Ensure we get the expected default-constructed message. */
    public function testConstructor1(): void
    {
        $message = new Message();
        self::assertEquals([], $message->to());
        self::assertEquals("", $message->from());
        self::assertEquals([], $message->cc());
        self::assertEquals([], $message->bcc());
        self::assertEquals("", $message->subject());
        self::assertEquals(0, $message->partCount());
        self::assertCount(0, $message->parts());
        $headers = self::headersToAssociativeArray($message->headers());
        self::assertEqualsCanonicalizing(
            [
                "content-type" => "text/plain",
                "content-transfer-encoding" => "quoted-printable",
            ],
            $headers
        );
    }

    /** Ensure the constructor sets the recipient. */
    public function testConstructor2(): void
    {
        $message = new Message(self::TestMessageRecipient);
        $recipients = $message->to();
        self::assertCount(1, $recipients);
        self::assertEquals(self::TestMessageRecipient, $recipients[0]);
    }

    /** Ensure the constructor sets the sender. */
    public function testConstructor3(): void
    {
        $message = new Message(null, null, null, self::TestMessageSender);
        $sender = $message->from();
        self::assertEquals(self::TestMessageSender, $sender);
    }

    /** Ensure the constructor sets the subject. */
    public function testConstructor4(): void
    {
        $message = new Message(null, self::TestMessageSubject);
        $subject = $message->subject();
        self::assertEquals(self::TestMessageSubject, $subject);
    }

    /** Ensure the constructor sets the message body */
    public function testConstructor5(): void
    {
        $message = new Message(null, null, self::TestMessagePlainText);
        self::assertEquals("text/plain", $message->contentType());
        self::assertEquals(self::TestMessagePlainText, $message->body());
    }

    /** Ensure we can add a header. */
    public function testWithHeader1(): void
    {
        $header = new Header("header-name", "header-value", ["parameter-1" => "value-1",]);
        $message = $this->message->withHeader($header);
        self::assertNotSame($this->message, $message);
        self::assertHasHeader($header, $message);
    }

    /** Ensure we can update an existing single-use header. */
    public function testWithHeader2(): void
    {
        $messageClass = new StaticXRay(Message::class);
        self::assertContains("content-type", $messageClass->singleUseHeaders(), "testWithHeader2() must work with a single-use header, and content-type is not single-use");
        self::assertNotEquals("application/x-bead-type", $this->message->header("content-type")?->value());
        $header = new Header("content-type", "application/x-bead-type");
        $message = $this->message->withHeader($header);
        $header = $message->header("content-type");
        self::assertInstanceOf(Header::class, $header);
        self::assertEquals("application/x-bead-type", $header->value());
        self::assertCount(1, array_filter($message->headers(), fn (Header $header): bool => "content-type" === strtolower($header->name())));
    }

    /** Ensure we can add a header with name and value. */
    public function testWithHeader3(): void
    {
        $header = new Header("header-name", "header-value");
        $message = $this->message->withHeader($header->name(), $header->value());
        self::assertNotSame($this->message, $message);
        self::assertHasEquivalentHeader($header, $message);
    }

    /** Ensure we can update an existing single-use header with name and value. */
    public function testWithHeader5(): void
    {
        $messageClass = new StaticXRay(Message::class);
        self::assertContains("content-type", $messageClass->singleUseHeaders(), "testWithHeader2() must work with a single-use header, and content-type is not single-use");
        self::assertNotEquals("application/x-bead-type", $this->message->header("content-type")?->value());
        $header = new Header("content-type", "application/x-bead-type");
        $message = $this->message->withHeader($header->name(), $header->value());
        $header = $message->header("content-type");
        self::assertInstanceOf(Header::class, $header);
        self::assertEquals("application/x-bead-type", $header->value());
        self::assertCount(1, array_filter($message->headers(), fn (Header $header): bool => "content-type" === strtolower($header->name())));
    }

    /** Ensure we can get the subject of a message. */
    public function testSubject1(): void
    {
        self::assertEquals(self::TestMessageSubject, $this->message->subject());
    }

    /** Ensure we can immutably set the subject of a message. */
    public function testWithSubject1(): void
    {
        $message = $this->message->withSubject("Bead framework");
        self::assertNotSame($this->message, $message);
        self::assertEquals(self::TestMessageSubject, $this->message->subject());
        self::assertEquals("Bead framework", $message->subject());
    }

    /** Ensure we can get the recipient of a message. */
    public function testTo1(): void
    {
        self::assertEquals([self::TestMessageRecipient,], $this->message->to());
    }

    /** Ensure we can immutably add a recipient for a message. */
    public function testWithTo1(): void
    {
        $message = $this->message->withTo("someone-else@example.com");
        self::assertNotSame($this->message, $message);
        self::assertEquals([self::TestMessageRecipient,], $this->message->to());
        self::assertEqualsCanonicalizing([self::TestMessageRecipient, "someone-else@example.com",], $message->to());
    }

    /** Ensure we can immutably add a multiple recipients for a message. */
    public function testWithTo2(): void
    {
        $message = $this->message->withTo(["someone-else@example.com", "another-recipient@example.com",]);
        self::assertNotSame($this->message, $message);
        self::assertEquals([self::TestMessageRecipient,], $this->message->to());
        self::assertEqualsCanonicalizing([self::TestMessageRecipient, "someone-else@example.com", "another-recipient@example.com",], $message->to());
    }

    /** Ensure withTo() throws if any recipient is not a string. */
    public function testWithTo3(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Addresses provided to withTo() must all be strings.");
        $this->message->withTo(["someone-else@example.com", 3.14, "another-recipient@example.com",]);
    }

    /** Ensure we can get the cc recipient of a message. */
    public function testCc1(): void
    {
        self::assertEquals([self::TestMessageCarbonCopy,], $this->message->cc());
    }

    /** Ensure we can immutably add a cc recipient for a message. */
    public function testWithCc1(): void
    {
        $message = $this->message->withCc("someone-else@example.com");
        self::assertNotSame($this->message, $message);
        self::assertEquals([self::TestMessageCarbonCopy,], $this->message->cc());
        self::assertEqualsCanonicalizing([self::TestMessageCarbonCopy, "someone-else@example.com",], $message->cc());
    }

    /** Ensure we can immutably add a multiple cc recipients for a message. */
    public function testWithCc2(): void
    {
        $message = $this->message->withCc(["someone-else@example.com", "another-recipient@example.com",]);
        self::assertNotSame($this->message, $message);
        self::assertEquals([self::TestMessageCarbonCopy,], $this->message->cc());
        self::assertEqualsCanonicalizing([self::TestMessageCarbonCopy, "someone-else@example.com", "another-recipient@example.com",], $message->cc());
    }

    /** Ensure withCc() throws if any recipient is not a string. */
    public function testWithCc3(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Addresses provided to withCc() must all be strings.");
        $this->message->withCc(["someone-else@example.com", 3.14, "another-recipient@example.com",]);
    }
    /** Ensure we can get the cc recipient of a message. */
    public function testBcc1(): void
    {
        self::assertEquals([self::TestMessageBlindCarbonCopy,], $this->message->bcc());
    }

    /** Ensure we can immutably add a cc recipient for a message. */
    public function testWithBcc1(): void
    {
        $message = $this->message->withBcc("someone-else@example.com");
        self::assertNotSame($this->message, $message);
        self::assertEquals([self::TestMessageBlindCarbonCopy,], $this->message->bcc());
        self::assertEqualsCanonicalizing([self::TestMessageBlindCarbonCopy, "someone-else@example.com",], $message->bcc());
    }

    /** Ensure we can immutably add a multiple cc recipients for a message. */
    public function testWithBcc2(): void
    {
        $message = $this->message->withBcc(["someone-else@example.com", "another-recipient@example.com",]);
        self::assertNotSame($this->message, $message);
        self::assertEquals([self::TestMessageBlindCarbonCopy,], $this->message->bcc());
        self::assertEqualsCanonicalizing([self::TestMessageBlindCarbonCopy, "someone-else@example.com", "another-recipient@example.com",], $message->bcc());
    }

    /** Ensure withBcc)( throws if any recipient is not a string. */
    public function testWithBcc3(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Addresses provided to withBcc() must all be strings.");
        $this->message->withBcc(["someone-else@example.com", 3.14, "another-recipient@example.com",]);
    }

    /** Ensure we can get the sender of a message. */
    public function testFrom1(): void
    {
        self::assertEquals(self::TestMessageSender, $this->message->from());
    }

    /** Ensure we can immutably set the sender of a message. */
    public function testWithFrom1(): void
    {
        $message = $this->message->withFrom("someone-else@example.com");
        self::assertNotSame($this->message, $message);
        self::assertEquals(self::TestMessageSender, $this->message->from());
        self::assertEquals("someone-else@example.com", $message->from());
    }

    /** Ensure the body can be retrieved */
    public function testBody1(): void
    {
        self::assertEquals(self::TestMessagePlainText, $this->message->body());
    }

    /** Ensure body member is nullified if the message has parts. */
    public function testBody2(): void
    {
        $message = new XRay($this->message);
        $message->parts = [new Part(self::TestMessagePlainText),];
        self::assertIsString($message->body);
        $this->message->body();
        self::assertNull($message->body);
    }

    /** Ensure we can set the message body. */
    public function testWithBody1(): void
    {
        $message = $this->message->withBody("Some other test message content.");
        self::assertNotSame($this->message, $message);
        self::assertEquals(self::TestMessagePlainText, $this->message->body());
        self::assertEquals(0, $message->partCount());
        self::assertCount(0, $message->parts());
        self::assertEquals("Some other test message content.", $message->body());
    }

    /** Ensure we can unset the message body. */
    public function testWithBody2(): void
    {
        $message = $this->message->withBody(null);
        self::assertNotSame($this->message, $message);
        self::assertEquals(self::TestMessagePlainText, $this->message->body());
        self::assertEquals(0, $message->partCount());
        self::assertCount(0, $message->parts());
        self::assertNull($message->body());
    }

    /** Ensure we can fetch the number of parts in the message. */
    public function testPartCount1(): void
    {
        $message = $this->message->withPart(self::TestMessagePlainText, "text/plain", "quoted-printable");
        self::assertEquals(1, $message->partCount());
    }

    /** Ensure we can fetch the message parts. */
    public function testParts1(): void
    {
        $message = $this->message->withPart(self::TestMessagePlainText, "text/plain", "quoted-printable");
        $parts = $message->parts();
        self::assertCount(1, $parts);
        self::assertInstanceOf(Part::class, $parts[0]);
        self::assertEquals(self::TestMessagePlainText, $parts[0]->body());
    }

    /** Ensure we can add more parts immutably */
    public function testWithPart1(): void
    {
        $this->message = $this->message->withPart(self::TestMessagePlainText, "text/plain", "quoted-printable");
        $part = new Part("Some other content.");
        $message = $this->message->withPart($part);
        self::assertNotSame($this->message, $message);
        self::assertEquals(1, $this->message->partCount());
        self::assertEquals(2, $message->partCount());
        self::assertHasPart($part, $message);
    }

    /** Ensure we can add more parts immutably using content, content-type and content-transfer-encoding */
    public function testWithPart2(): void
    {
        $this->message = $this->message->withPart(self::TestMessagePlainText, "text/plain", "quoted-printable");
        $part = new Part("Some other content.", "application/x-bead-part", "x-bead-encoding");
        $message = $this->message->withPart($part->body(), $part->contentType(), $part->contentEncoding());
        self::assertNotSame($this->message, $message);
        self::assertEquals(1, $this->message->partCount());
        self::assertEquals(2, $message->partCount());
        self::assertHasEquivalentPart($part, $message);
    }

    /** Ensure we can add attachments immutably */
    public function testWithAttachment1(): void
    {
        $this->message = $this->message->withPart(self::TestMessagePlainText, "text/plain", "quoted-printable");
        $dispositionHeader = new Header("content-disposition", "attachment", ["filename" => "\"bead-attachment.file\""]);
        $expectedPart = (new Part("Some attachment content", "application/x-bead-attachment", "x-bead-encoding"))
            ->withHeader($dispositionHeader);

        $message = $this->message->withAttachment($expectedPart->body(), $expectedPart->contentType(), $expectedPart->contentEncoding(), "bead-attachment.file");
        self::assertNotSame($this->message, $message);
        self::assertEquals(1, $this->message->partCount());
        self::assertEquals(2, $message->partCount());
        self::assertHasEquivalentPart($expectedPart, $message);
    }

    /** Ensure we can read the content type. */
    public function testContentType(): void
    {
        self::assertEquals("text/plain", $this->message->contentType());
    }

    /** Ensure we can immutably set the content type. */
    public function testWithContentType1(): void
    {
        $originalContentType = $this->message->contentType();
        self::assertNotEquals("application/x-bead-content", $originalContentType);
        $message = $this->message->withContentType("application/x-bead-content");
        self::assertNotSame($this->message, $message);
        self::assertEquals($originalContentType, $this->message->contentType());
        self::assertEquals("application/x-bead-content", $message->contentType());
    }

    /** Ensure the content type is trimmed when set. */
    public function testWithContentType2(): void
    {
        self::assertNotEquals("application/x-bead-content", $this->message->contentType());
        $message = $this->message->withContentType("  application/x-bead-content  ");
        self::assertEquals("application/x-bead-content", $message->contentType());
    }

    /** Ensure setting an invalid media type throws. */
    public function testWithContentType3(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected valid media type, found \"foo\"");
        $this->message->withContentType("foo");
    }

    /** Ensure we can read the content transfer encoding. */
    public function testContentTransferEncoding1(): void
    {
        self::assertEquals("quoted-printable", $this->message->contentTransferEncoding());
    }

    /** Ensure we can immutably set the content transfer encoding. */
    public function testWithContentTransferEncoding1(): void
    {
        $originalContentTransferEncoding = $this->message->contentTransferEncoding();
        self::assertNotEquals("x-bead-transfer-encoding", $originalContentTransferEncoding);
        $message = $this->message->withContentTransferEncoding("x-bead-transfer-encoding");
        self::assertNotSame($this->message, $message);
        self::assertEquals($originalContentTransferEncoding, $this->message->contentTransferEncoding());
        self::assertEquals("x-bead-transfer-encoding", $message->contentTransferEncoding());
    }

    /** Ensure the content transfer encoding is trimmed when set. */
    public function testWithContentTransferEncoding2(): void
    {
        self::assertNotEquals("x-bead-transfer-encoding", $this->message->contentTransferEncoding());
        $message = $this->message->withContentTransferEncoding("  x-bead-transfer-encoding  ");
        self::assertEquals("x-bead-transfer-encoding", $message->contentTransferEncoding());
    }

    /** Ensure setting an invalid transfer encoding throws. */
    public function testWithContentTransferEncoding3(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected valid content transfer encoding, found \"foo\"");
        $this->message->withContentTransferEncoding("foo");
    }
}
