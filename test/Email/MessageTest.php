<?php

declare(strict_types=1);

namespace BeadTests\Email;

use Bead\Email\Header;
use Bead\Email\Message;
use Bead\Email\Part;
use Bead\Testing\StaticXRay;
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
        // ensures that the message gets all 'a's as the part delimiter
        $this->mockFunction("rand", fn (int $min, int $max): int => 0);
        $this->message = (new Message(self::TestMessageRecipient, self::TestMessageSubject, self::TestMessagePlainText, self::TestMessageSender))
            ->withCc(self::TestMessageCarbonCopy)
            ->withBcc(self::TestMessageBlindCarbonCopy);
        $this->removeFunctionMock("rand");
    }

    public function tearDown(): void
    {
        unset ($this->message);
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
                "content-type" => "multipart/mixed",
                "content-transfer-encoding" => "7bit",
                "mime-version" => "1.0",
            ],
            $headers
        );

        $header = $message->header("content-type");
        self::assertMatchesRegularExpression("/^\"--bead-email-part-[a-z0-9]{80}--\"\$/", $header->parameter("boundary"));
    }

    public static function dataForTestConstructor2(): iterable
    {
        yield "set-content-type" => [
            [new Header("content-type", "text/plain"),],
            [
                "content-type" => "text/plain",
                "content-transfer-encoding" => "7bit",
                "mime-version" => "1.0",
            ],
            false,
        ];

        yield "set-content-type-with-boundary" => [
            [new Header("content-type", "multipart/alternative", ["boundary" => "\"this-will-be-replaced\""]),],
            [
                "content-type" => "multipart/alternative",
                "content-transfer-encoding" => "7bit",
                "mime-version" => "1.0",
            ],
        ];

        yield "set-content-type-without-boundary" => [
            [new Header("content-type", "multipart/alternative"),],
            [
                "content-type" => "multipart/alternative",
                "content-transfer-encoding" => "7bit",
                "mime-version" => "1.0",
            ],
        ];

        yield "set-content-transfer-encoding" => [
            [new Header("content-transfer-encoding", "8bit"),],
            [
                "content-type" => "multipart/mixed",
                "content-transfer-encoding" => "8bit",
                "mime-version" => "1.0",
            ],
        ];

        yield "set-mime-version" => [
            [new Header("mime-version", "1.1"),],
            [
                "content-type" => "multipart/mixed",
                "content-transfer-encoding" => "7bit",
                "mime-version" => "1.1",
            ],
        ];

        yield "set-content-and-mime-version" => [
            [
                new Header("content-type", "text/plain"),
                new Header("mime-version", "1.1"),
            ],
            [
                "content-type" => "text/plain",
                "content-transfer-encoding" => "7bit",
                "mime-version" => "1.1",
            ],
            false,
        ];

        yield "set-content-type-with-boundary-and-mime-version" => [
            [
                new Header("content-type", "multipart/alternative", ["boundary" => "\"this-will-be-replaced\""]),
                new Header("mime-version", "1.1"),
            ],
            [
                "content-type" => "multipart/alternative",
                "content-transfer-encoding" => "7bit",
                "mime-version" => "1.1",
            ],
        ];

        yield "set-content-type-without-boundary-and-mime-version" => [
            [
                new Header("content-type", "multipart/alternative"),
                new Header("mime-version", "1.1"),
            ],
            [
                "content-type" => "multipart/alternative",
                "content-transfer-encoding" => "7bit",
                "mime-version" => "1.1",
            ],
        ];

        yield "set-content-type-and-content-transfer-encoding" => [
            [
                new Header("content-type", "text/plain"),
                new Header("content-transfer-encoding", "8bit"),
            ],
            [
                "content-type" => "text/plain",
                "content-transfer-encoding" => "8bit",
                "mime-version" => "1.0",
            ],
            false,
        ];

        yield "set-content-type-with-boundary-and-content-transfer-encoding" => [
            [
                new Header("content-type", "multipart/alternative", ["boundary" => "\"this-will-be-replaced\""]),
                new Header("content-transfer-encoding", "8bit"),
            ],
            [
                "content-type" => "multipart/alternative",
                "content-transfer-encoding" => "8bit",
                "mime-version" => "1.0",
            ],
        ];

        yield "set-content-type-without-boundary-and-content-transfer-encoding" => [
            [
                new Header("content-type", "multipart/alternative"),
                new Header("content-transfer-encoding", "8bit"),
            ],
            [
                "content-type" => "multipart/alternative",
                "content-transfer-encoding" => "8bit",
                "mime-version" => "1.0",
            ],
        ];

        yield "set-content-type-content-transfer-encoding-and-mime-version" => [
            [
                new Header("content-type", "text/plain"),
                new Header("content-transfer-encoding", "8bit"),
                new Header("mime-version", "1.1"),
            ],
            [
                "content-type" => "text/plain",
                "content-transfer-encoding-and-mime-version" => "8bit",
                "mime-version" => "1.1",
            ],
            false,
        ];

        yield "set-content-type-with-boundary-and-content-transfer-encoding-and-mime-version" => [
            [
                new Header("content-type", "multipart/alternative", ["boundary" => "\"this-will-be-replaced\""]),
                new Header("content-transfer-encoding", "8bit"),
                new Header("mime-version", "1.1"),
            ],
            [
                "content-type" => "multipart/alternative",
                "content-transfer-encoding-and-mime-version" => "8bit",
                "mime-version" => "1.1",
            ],
        ];

        yield "set-content-type-without-boundary-and-content-transfer-encoding-and-mime-version" => [
            [
                new Header("content-type", "multipart/alternative"),
                new Header("content-transfer-encoding", "8bit"),
                new Header("mime-version", "1.1"),
            ],
            [
                "content-type" => "multipart/alternative",
                "content-transfer-encoding-and-mime-version" => "8bit",
                "mime-version" => "1.1",
            ],
        ];

        yield "set-content-transfer-encoding-and-mime-version" => [
            [
                new Header("content-transfer-encoding", "8bit"),
                new Header("mime-version", "1.1"),
            ],
            [
                "content-type" => "multipart/mixed",
                "content-transfer-encoding" => "8bit",
                "mime-version" => "1.1",
            ],
        ];
    }

    /**
     * Ensure the constructor creates only the required default headers.
     *
     * @dataProvider dataForTestConstructor2
     */
    public function testConstructor2(array $headers, array $expectedHeaders, bool $checkContentTypeBoundary = true): void
    {
        $message = new Message(null, null, null, null, $headers);
        $headers = self::headersToAssociativeArray($message->headers());
        self::assertEqualsCanonicalizing($expectedHeaders, $headers);
        $header = $message->header("content-type");
        self::assertInstanceOf(Header::class, $header);

        if ($checkContentTypeBoundary) {
            self::assertMatchesRegularExpression("/^\"--bead-email-part-[a-z0-9]{80}--\"\$/", $header->parameter("boundary"));
        } else {
            self::assertNull($header->parameter("boundary"));
        }
    }

    /** Ensure the constructor sets the recipient. */
    public function testConstructor3(): void
    {
        $message = new Message(self::TestMessageRecipient);
        $recipients = $message->to();
        self::assertCount(1, $recipients);
        self::assertEquals(self::TestMessageRecipient, $recipients[0]);
    }

    /** Ensure the constructor adds the recipient to the headers when "to" headers are present. */
    public function testConstructor4(): void
    {
        $headers = [
            new Header("to", "someone-else@example.com"),
            new Header("to", "another-person@example.com"),
        ];

        $message = new Message(self::TestMessageRecipient, null, null, null, $headers);
        $recipients = $message->to();
        self::assertCount(3, $recipients);
        self::assertEqualsCanonicalizing(
            [self::TestMessageRecipient, "someone-else@example.com", "another-person@example.com",],
            $recipients
        );
    }

    /** Ensure the constructor sets the sender. */
    public function testConstructor5(): void
    {
        $message = new Message(null, null, null, self::TestMessageSender);
        $sender = $message->from();
        self::assertEquals(self::TestMessageSender, $sender);
    }

    /** Ensure the constructor sets the sender from the headers. */
    public function testConstructor6(): void
    {
        $headers = [
            new Header("from", self::TestMessageSender),
        ];

        $message = new Message(null, null, null, null, $headers);
        $sender = $message->from();
        self::assertEquals(self::TestMessageSender, $sender);
    }

    /** Ensure the constructor overrides the headers with the sender set in the args. */
    public function testConstructor7(): void
    {
        $headers = [
            new Header("from", "someone-else@example.com"),
        ];

        $message = new Message(null, null, null, self::TestMessageSender, $headers);
        $sender = $message->from();
        self::assertEquals(self::TestMessageSender, $sender);

        // ensure we only have the one
        // from" header
        foreach ($message->headers() as $header) {
            self::assertFalse("from" === strtolower($header->name()) && self::TestMessageSender !== strtolower(trim($header->value())));
        }
    }

    /** Ensure the constructor sets the subject. */
    public function testConstructor8(): void
    {
        $message = new Message(null, self::TestMessageSubject);
        $subject = $message->subject();
        self::assertEquals(self::TestMessageSubject, $subject);
    }

    /** Ensure the constructor sets the subject from the headers. */
    public function testConstructor9(): void
    {
        $headers = [
            new Header("subject", self::TestMessageSubject),
        ];

        $message = new Message(null, null, null, null, $headers);
        $subject = $message->subject();
        self::assertEquals(self::TestMessageSubject, $subject);
    }

    /** Ensure the constructor overrides the headers with the subject set in the args. */
    public function testConstructor10(): void
    {
        $headers = [
            new Header("subject", "Another example message subject."),
        ];

        $message = new Message(null, self::TestMessageSubject, null, null, $headers);
        $subject = $message->subject();
        self::assertEquals(self::TestMessageSubject, $subject);

        // ensure we only have the one "from" header
        foreach ($message->headers() as $header) {
            self::assertFalse("subject" === strtolower($header->name()) && self::TestMessageSubject !== trim($header->value()));
        }
    }

    /** Ensure the constructor sets the message body */
    public function testConstructor11(): void
    {
        $message = new Message(null, null, self::TestMessagePlainText);
        self::assertEquals(1, $message->partCount());
        $part = $message->parts()[0];
        self::assertInstanceOf(Part::class, $part);
        self::assertEquals("text/plain", $part->contentType());
        self::assertEquals(self::TestMessagePlainText, $part->body());
    }

    public static function dataForTestConstructor12(): iterable
    {
        yield "first-invalid" => [
            [
                "not a header",
                new Header("field-1", "value-1"),
                new Header("field-2", "value-2"),
            ],
        ];

        yield "middle-invalid" => [
            [
                new Header("field-1", "value-1"),
                "not a header",
                new Header("field-2", "value-2"),
            ],
        ];

        yield "last-invalid" => [
            [
                new Header("field-1", "value-1"),
                new Header("field-2", "value-2"),
                "not a header",
            ],
        ];
    }

    /**
     * Ensure constructor throws when $headers contains things that aren't headers
     *
     * @dataProvider dataForTestConstructor12
     */
    public function testConstructor12(array $invalidHeaders): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage("Invalid header provided to Message constructor.");
        new Message(null, null, null, null, $invalidHeaders);
    }

    public static function dataForTestConstructor13(): iterable
    {
        $message = new StaticXRay(Message::class);

        foreach ($message->singleUseHeaders() as $headerName) {
            yield $headerName => [$headerName];
        }
    }

    /**
     * Ensure constructor throws when single-use headers are provided in the headers array more than once
     *
     * @dataProvider dataForTestConstructor13
     */
    public function testConstructor13(string $headerName): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Header {$headerName} can only appear once.");
        new Message(null, null, null, null, [new Header($headerName, "value"), new Header($headerName, "value"),]);
    }

    /** Ensure we can add a header. */
    public function testWithHeader1(): void
    {
        $header = new Header("header-name", "header-value");
        $message = $this->message->withHeader($header);
        self::assertNotSame($this->message, $message);
        self::assertHasHeader($header, $message);
    }

    /** Ensure we can update an existing single-use header. */
    public function testWithHeader2(): void
    {
        $messageClass = new StaticXRay(Message::class);
        self::assertContains("content-type", $messageClass->singleUseHeaders(), "testWithHeader2() must work with a single-use header, and content-type is not single-use");
        self::assertNotEquals("text/plain", $this->message->header("content-type")?->value());
        $header = new Header("content-type", "text/plain");
        $message = $this->message->withHeader($header);
        $header = $message->header("content-type");
        self::assertInstanceOf(Header::class, $header);
        self::assertEquals("text/plain", $header->value());
        self::assertCount(1, array_filter($message->headers(), fn (Header $header): bool => "content-type" === strtolower($header->name())));
    }

    /** Ensure the Message's multipart boundary is preserved when setting the header. */
    public function testWithHeader3(): void
    {
        $messageClass = new StaticXRay(Message::class);
        self::assertContains("content-type", $messageClass->singleUseHeaders(), "testWithHeader2() must work with a single-use header, and content-type is not single-use");
        $header = $this->message->header("content-type");
        self::assertInstanceOf(Header::class, $header);
        self::assertNotEquals("multipart/alternative", $header->value());
        $boundary = $header->parameter("boundary");
        self::assertIsString($boundary);
        self::assertGreaterThan(0, strlen($boundary));
        $header = new Header("content-type", "multipart/alternative", ["boundary" => "\"this-will-be-replaced\""]);
        $message = $this->message->withHeader($header);
        $header = $message->header("content-type");
        self::assertInstanceOf(Header::class, $header);
        self::assertEquals("multipart/alternative", $header->value());
        self::assertEquals($boundary, $header->parameter("boundary"));
    }

    /** Ensure we can add a header with name, value and parameters. */
    public function testWithHeader4(): void
    {
        $header = new Header("header-name", "header-value", ["parameter-name-1" => "parameter-value-1", "parameter-name-2" => "parameter-value-2",]);
        $message = $this->message->withHeader($header->name(), $header->value(), $header->parameters());
        self::assertNotSame($this->message, $message);
        self::assertHasEquivalentHeader($header, $message);
    }

    /** Ensure we can update an existing single-use header with name, value and parameters. */
    public function testWithHeader5(): void
    {
        $messageClass = new StaticXRay(Message::class);
        self::assertContains("content-type", $messageClass->singleUseHeaders(), "testWithHeader2() must work with a single-use header, and content-type is not single-use");
        self::assertNotEquals("text/plain", $this->message->header("content-type")?->value());
        $header = new Header("content-type", "text/plain", ["parameter-name-1" => "parameter-value-1",]);
        $message = $this->message->withHeader($header->name(), $header->value(), $header->parameters());
        $header = $message->header("content-type");
        self::assertInstanceOf(Header::class, $header);
        self::assertEquals("text/plain", $header->value());
        self::assertEqualsCanonicalizing(["parameter-name-1" => "parameter-value-1",], $header->parameters());
        self::assertCount(1, array_filter($message->headers(), fn (Header $header): bool => "content-type" === strtolower($header->name())));
    }

    /** Ensure the Message's multipart boundary is preserved when setting the header with name, value and parameters. */
    public function testWithHeader6(): void
    {
        $messageClass = new StaticXRay(Message::class);
        self::assertContains("content-type", $messageClass->singleUseHeaders(), "testWithHeader2() must work with a single-use header, and content-type is not single-use");
        $header = $this->message->header("content-type");
        self::assertInstanceOf(Header::class, $header);
        self::assertNotEquals("multipart/alternative", $header->value());
        $boundary = $header->parameter("boundary");
        self::assertIsString($boundary);
        self::assertGreaterThan(0, strlen($boundary));
        $header = new Header("content-type", "multipart/alternative", ["boundary" => "\"this-will-be-replaced\""]);
        $message = $this->message->withHeader($header->name(), $header->value(), $header->parameters());
        $header = $message->header("content-type");
        self::assertInstanceOf(Header::class, $header);
        self::assertEquals("multipart/alternative", $header->value());
        self::assertEquals($boundary, $header->parameter("boundary"));
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
        self::assertEquals("\r\n----bead-email-part-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa--\r\ncontent-type: text/plain\r\ncontent-transfer-encoding: quoted-printable\r\n\r\n" . self::TestMessagePlainText . "\r\n----bead-email-part-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa----", $this->message->body());
    }

    /** Ensure we can set the message body. */
    public function testWithBody1(): void
    {
        $message = $this->message->withBody("Some other test message content.");
        self::assertNotSame($this->message, $message);
        self::assertEquals("\r\n----bead-email-part-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa--\r\ncontent-type: text/plain\r\ncontent-transfer-encoding: quoted-printable\r\n\r\n" . self::TestMessagePlainText . "\r\n----bead-email-part-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa----", $this->message->body());
        self::assertEquals(1, $message->partCount());
        self::assertCount(1, $message->parts());
        self::assertEquals("\r\n----bead-email-part-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa--\r\ncontent-type: text/plain\r\ncontent-transfer-encoding: quoted-printable\r\n\r\nSome other test message content.\r\n----bead-email-part-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa----", $message->body());
    }

    /** Ensure we can unset the message body. */
    public function testWithBody2(): void
    {
        $message = $this->message->withBody(null);
        self::assertNotSame($this->message, $message);
        self::assertEquals("\r\n----bead-email-part-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa--\r\ncontent-type: text/plain\r\ncontent-transfer-encoding: quoted-printable\r\n\r\n" . self::TestMessagePlainText . "\r\n----bead-email-part-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa----", $this->message->body());
        self::assertEquals(0, $message->partCount());
        self::assertCount(0, $message->parts());
        self::assertEquals("----bead-email-part-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa----", $message->body());
    }

    /** Ensure we can fetch the number of parts in the message. */
    public function testPartCount1(): void
    {
        self::assertEquals(1, $this->message->partCount());
    }

    public function testParts1(): void
    {
        $parts = $this->message->parts();
        self::assertCount(1, $parts);
        self::assertInstanceOf(Part::class, $parts[0]);
        self::assertEquals(self::TestMessagePlainText, $parts[0]->body());
    }

    /** Ensure we can add more parts immutably */
    public function testWithPart1(): void
    {
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
        $dispositionHeader = new Header("content-disposition", "attachment", ["filename" => "\"bead-attachment.file\""]);
        $expectedPart = (new Part("Some attachment content", "application/x-bead-attachment", "x-bead-encoding"))
            ->withHeader($dispositionHeader);

        $message = $this->message->withAttachment($expectedPart->body(), $expectedPart->contentType(), $expectedPart->contentEncoding(), "bead-attachment.file");
        self::assertNotSame($this->message, $message);
        self::assertEquals(1, $this->message->partCount());
        self::assertEquals(2, $message->partCount());
        self::assertHasEquivalentPart($expectedPart, $message);
    }
}
