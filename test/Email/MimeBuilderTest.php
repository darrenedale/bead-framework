<?php

declare(strict_types=1);

namespace BeadTests\Email;

use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Part as PartContract;
use Bead\Email\Message;
use Bead\Email\Mime;
use Bead\Email\MimeBuilder;
use Bead\Email\Part;
use Bead\Exceptions\Email\MimeException;
use BeadTests\Framework\TestCase;
use InvalidArgumentException;

class MimeBuilderTest extends TestCase
{
    private MimeBuilder $builder;

    public function setUp(): void
    {
        $this->builder = new MimeBuilder();
    }

    public function tearDown(): void
    {
        unset($this->builder);
        parent::tearDown();
    }

    /** Ensure default-constructed builder has expected MIME version. */
    public function testConstructor1(): void
    {
        self::assertEquals(MimeBuilder::MimeVersion10, $this->builder->mimeVersion());
        self::assertEquals(Mime::Rfc822LineEnd, $this->builder->lineEnd());
    }

    /** Ensure the MIME version can be set in the constructor. */
    public function testConstructor2(): void
    {
        $this->mockMethod(MimeBuilder::class, "isSupportedMimeVersion", true);
        $builder = new MimeBuilder("1.1");
        self::assertEquals("1.1", $builder->mimeVersion());
    }

    /** Provides constructor arguments for testConstructor3() */
    public static function dataForTestConstructor3(): iterable
    {
        yield "1.1" => ["1.1",];
        yield "empty string" => ["",];
        yield "whitespace" => ["  ",];
    }


    /**
     * Ensure constructor throws with unsupported MIME version.
     * 
     * @dataProvider dataForTestConstructor3
     */
    public function testConstructor3(string $mimeVersion): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected supported MIME version, found \"{$mimeVersion}\"");
        new MimeBuilder($mimeVersion);
    }

    /** Provides test data for testIsSupportedMimeVersion() */
    public static function dataForTestIsSupportedMimeVersion(): iterable
    {
        yield "1.0" => [MimeBuilder::MimeVersion10, true,];
        yield "1.1" => ["1.1", false,];
        yield "empty string" => ["", false,];
        yield "whitespace" => ["  ", false,];
    }

    /**
     * Ensure isSupportedMimeVersion provides the correct responses.
     *
     * @dataProvider dataForTestIsSupportedMimeVersion
     *
     * @param string $version
     * @param bool $expected
     */
    public function testIsSupportedMimeVersion(string $version, bool $expected): void
    {
        self::assertEquals($expected, MimeBuilder::isSupportedMimeVersion($version));
    }

    /** Ensure we get back the expected MIME version. */
    public function testMimeVersion(): void
    {
        self::assertEquals(MimeBuilder::MimeVersion10, $this->builder->mimeVersion());
    }

    /** Ensure setting MIME version preserves immutability. */
    public function testWithMimeVersion1(): void
    {
        $builder = $this->builder->withMimeVersion(MimeBuilder::MimeVersion10);
        self::assertNotSame($this->builder, $builder);
    }

    /** Ensure setting MIME version sets the new mime version on only the clone. */
    public function testWithMimeVersion2(): void
    {
        $this->mockMethod(MimeBuilder::class, "isSupportedMimeVersion", true);
        $builder = $this->builder->withMimeVersion("1.1");
        self::assertEquals(MimeBuilder::MimeVersion10, $this->builder->mimeVersion());
        self::assertEquals("1.1", $builder->mimeVersion());
    }

    /** Ensure we get back the expected line end. */
    public function testLineEnd(): void
    {
        self::assertEquals(Mime::Rfc822LineEnd, $this->builder->lineEnd());
    }

    /** Ensure setting line end to LF preserves immutability. */
    public function testWithLfLineEnd1(): void
    {
        $builder = $this->builder->withLfLineEnd();
        self::assertNotSame($this->builder, $builder);
    }

    /** Ensure setting line end to LF sets the new mime version on only the clone. */
    public function testWithLfLineEnd2(): void
    {
        $builder = $this->builder->withLfLineEnd();
        self::assertEquals(Mime::Rfc822LineEnd, $this->builder->lineEnd());
        self::assertEquals("\n", $builder->lineEnd());
    }

    /** Ensure setting line end to CRLF preserves immutability. */
    public function testWithRfc822LineEnd1(): void
    {
        $builder = $this->builder->withRfc822LineEnd();
        self::assertNotSame($this->builder, $builder);
    }

    /** Ensure setting line end to LF sets the new mime version on only the clone. */
    public function testWithRfc822LineEnd2(): void
    {
        $this->builder = $this->builder->withLfLineEnd();
        $builder = $this->builder->withRfc822LineEnd();
        self::assertEquals("\n", $this->builder->lineEnd());
        self::assertEquals(Mime::Rfc822LineEnd, $builder->lineEnd());
    }

    public static function dataForTestMime1(): iterable
    {
        // NOTE Message always provides its headers in the order they are added
        yield "plain-text-message" => [
            new Message("nobody@example.com", "Nothing", "Plain text content."),
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "to: nobody@example.com\r\n" .
            "subject: Nothing\r\n" .
            "mime-version: 1.0\r\n" .
            "\r\n" .
            "Plain text content."
        ];

        yield "plain-text-message-with-cc" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withCc("someone@example.com"),
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "to: nobody@example.com\r\n" .
            "subject: Nothing\r\n" .
            "cc: someone@example.com\r\n" .
            "mime-version: 1.0\r\n" .
            "\r\n" .
            "Plain text content."
        ];

        yield "plain-text-message-with-bcc" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withBcc("someone@example.com"),
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "to: nobody@example.com\r\n" .
            "subject: Nothing\r\n" .
            "bcc: someone@example.com\r\n" .
            "mime-version: 1.0\r\n" .
            "\r\n" .
            "Plain text content."
        ];

        yield "plain-text-message-with-from" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withFrom("someone@example.com"),
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "to: nobody@example.com\r\n" .
            "subject: Nothing\r\n" .
            "from: someone@example.com\r\n" .
            "mime-version: 1.0\r\n" .
            "\r\n" .
            "Plain text content."
        ];

        yield "plain-text-message-with-cc-bcc-and-from" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withCc("someone@example.com")
                ->withBcc("someone-else@example.com")
                ->withFrom("another-person@example.com"),
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "to: nobody@example.com\r\n" .
            "subject: Nothing\r\n" .
            "cc: someone@example.com\r\n" .
            "bcc: someone-else@example.com\r\n" .
            "from: another-person@example.com\r\n" .
            "mime-version: 1.0\r\n" .
            "\r\n" .
            "Plain text content."
        ];

        yield "multipart-message" => [
            (new Message("nobody@example.com", "Nothing"))
                ->withContentType("multipart/mixed", ["boundary" => "bead-multipart-boundary",])
                ->withPart(new Part("The first part."))
                ->withPart(new Part("The second part.")),
            "content-transfer-encoding: quoted-printable\r\n" .
            "to: nobody@example.com\r\n" .
            "subject: Nothing\r\n" .
            "content-type: multipart/mixed; boundary=bead-multipart-boundary\r\n" .
            "mime-version: 1.0\r\n" .
            "\r\n" .
            "\r\n" .
            "--bead-multipart-boundary\r\n" .
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "\r\n" .
            "The first part.\r\n" .
            "\r\n" .
            "--bead-multipart-boundary\r\n" .
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "\r\n" .
            "The second part.\r\n" .
            "--bead-multipart-boundary--"
        ];
    }

    /**
     * Ensure mime() provides the expected MIME message structure.
     *
     * @dataProvider dataForTestMime1
     * @param MessageContract $message The message to build.
     * @param string $expectedMime The expected output from the mime() method.
     */
    public function testMime1(MessageContract $message, string $expectedMime): void
    {
        self::assertEquals($expectedMime, $this->builder->mime($message));
    }

    public static function dataForTestMime2(): iterable
    {
        // NOTE Message always provides its headers in the order they are added
        yield "plain-text-message" => [
            new Message("nobody@example.com", "Nothing", "Plain text content."),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "to: nobody@example.com\n" .
            "subject: Nothing\n" .
            "mime-version: 1.0\n" .
            "\n" .
            "Plain text content."
        ];

        yield "plain-text-message-with-cc" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withCc("someone@example.com"),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "to: nobody@example.com\n" .
            "subject: Nothing\n" .
            "cc: someone@example.com\n" .
            "mime-version: 1.0\n" .
            "\n" .
            "Plain text content."
        ];

        yield "plain-text-message-with-bcc" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withBcc("someone@example.com"),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "to: nobody@example.com\n" .
            "subject: Nothing\n" .
            "bcc: someone@example.com\n" .
            "mime-version: 1.0\n" .
            "\n" .
            "Plain text content."
        ];

        yield "plain-text-message-with-from" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withFrom("someone@example.com"),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "to: nobody@example.com\n" .
            "subject: Nothing\n" .
            "from: someone@example.com\n" .
            "mime-version: 1.0\n" .
            "\n" .
            "Plain text content."
        ];

        yield "plain-text-message-with-cc-bcc-and-from" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withCc("someone@example.com")
                ->withBcc("someone-else@example.com")
                ->withFrom("another-person@example.com"),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "to: nobody@example.com\n" .
            "subject: Nothing\n" .
            "cc: someone@example.com\n" .
            "bcc: someone-else@example.com\n" .
            "from: another-person@example.com\n" .
            "mime-version: 1.0\n" .
            "\n" .
            "Plain text content."
        ];

        yield "multipart-message" => [
            (new Message("nobody@example.com", "Nothing"))
                ->withContentType("multipart/mixed", ["boundary" => "bead-multipart-boundary",])
                ->withPart(new Part("The first part."))
                ->withPart(new Part("The second part.")),
            "content-transfer-encoding: quoted-printable\n" .
            "to: nobody@example.com\n" .
            "subject: Nothing\n" .
            "content-type: multipart/mixed; boundary=bead-multipart-boundary\n" .
            "mime-version: 1.0\n" .
            "\n" .
            "\n" .
            "--bead-multipart-boundary\n" .
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "\n" .
            "The first part.\n" .
            "\n" .
            "--bead-multipart-boundary\n" .
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "\n" .
            "The second part.\n" .
            "--bead-multipart-boundary--"
        ];
    }

    /**
     * Ensure mime() respects the non-RFC822-conformant line ending if requested.
     *
     * @dataProvider dataForTestMime2
     * @param MessageContract $message The message to build.
     * @param string $expectedMime The expected output from the mime() method.
     */
    public function testMime2(MessageContract $message, string $expectedMime): void
    {
        self::assertEquals($expectedMime, $this->builder->withLfLineEnd()->mime($message));
    }

    /** Ensure mime-version header is added when not present on the message */
    public function testMime3(): void
    {
        self::assertMatchesRegularExpression("/(^|\r\n)mime-version: 1.0\r\n/", $this->builder->mime(new Message(to: "nobody@example.com", body: "")));
    }

    /** Ensure mime-version header is not added when already present on the message */
    public function testMime4(): void
    {
        // NOTE Message() provides its headers in the order they were added
        self::assertStringStartsWith(
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "to: nobody@example.com\r\n" .
            "mime-version: 1.0\r\n\r\n",
            $this->builder->mime(
                (new Message(to: "nobody@example.com", body: ""))
                    ->withHeader("mime-version", "1.0")
            )
        );
    }

    public static function dataForTestHeaders1(): iterable
    {
        // NOTE Message and Part always generates their headers in the order they are added
        yield "plain-text-message" => [
            new Message("nobody@example.com", "Nothing"),
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "to: nobody@example.com\r\n" .
            "subject: Nothing\r\n" .
            "mime-version: 1.0\r\n",
        ];

        yield "plain-text-message-with-cc" => [
            (new Message("nobody@example.com", "Nothing"))
                ->withCc("someone@example.com"),
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "to: nobody@example.com\r\n" .
            "subject: Nothing\r\n" .
            "cc: someone@example.com\r\n" .
            "mime-version: 1.0\r\n",
        ];

        yield "plain-text-message-with-bcc" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withBcc("someone@example.com"),
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "to: nobody@example.com\r\n" .
            "subject: Nothing\r\n" .
            "bcc: someone@example.com\r\n" .
            "mime-version: 1.0\r\n",
        ];

        yield "plain-text-message-with-from" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withFrom("someone@example.com"),
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "to: nobody@example.com\r\n" .
            "subject: Nothing\r\n" .
            "from: someone@example.com\r\n" .
            "mime-version: 1.0\r\n",
        ];

        yield "plain-text-message-with-cc-bcc-and-from" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withCc("someone@example.com")
                ->withBcc("someone-else@example.com")
                ->withFrom("another-person@example.com"),
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "to: nobody@example.com\r\n" .
            "subject: Nothing\r\n" .
            "cc: someone@example.com\r\n" .
            "bcc: someone-else@example.com\r\n" .
            "from: another-person@example.com\r\n" .
            "mime-version: 1.0\r\n",
        ];

        yield "plain-text-part" => [
                new Part(""),
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n",
        ];

        yield "plain-text-part-with-extra-header" => [
            (new Part(""))
                ->withHeader("x-bead-header", "bead-value"),
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "x-bead-header: bead-value\r\n",
        ];
    }

    /**
     * Ensure headers() returns the expected header block.
     *
     * @dataProvider dataForTestHeaders1
     * @param MessageContract|PartContract $source The source of the headers.
     * @param string $expectedHeaders The expected MIME header block.
     */
    public function testHeaders1(MessageContract|PartContract $source, string $expectedHeaders): void
    {
        self::assertEquals($expectedHeaders, $this->builder->headers($source));
    }

    public static function dataForTestHeaders2(): iterable
    {
        // NOTE Message and Part always generates their headers in the order they are added
        yield "plain-text-message" => [
            new Message("nobody@example.com", "Nothing"),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "to: nobody@example.com\n" .
            "subject: Nothing\n" .
            "mime-version: 1.0\n",
        ];

        yield "plain-text-message-with-cc" => [
            (new Message("nobody@example.com", "Nothing"))
                ->withCc("someone@example.com"),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "to: nobody@example.com\n" .
            "subject: Nothing\n" .
            "cc: someone@example.com\n" .
            "mime-version: 1.0\n",
        ];

        yield "plain-text-message-with-bcc" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withBcc("someone@example.com"),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "to: nobody@example.com\n" .
            "subject: Nothing\n" .
            "bcc: someone@example.com\n" .
            "mime-version: 1.0\n",
        ];

        yield "plain-text-message-with-from" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withFrom("someone@example.com"),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "to: nobody@example.com\n" .
            "subject: Nothing\n" .
            "from: someone@example.com\n" .
            "mime-version: 1.0\n",
        ];

        yield "plain-text-message-with-cc-bcc-and-from" => [
            (new Message("nobody@example.com", "Nothing", "Plain text content."))
                ->withCc("someone@example.com")
                ->withBcc("someone-else@example.com")
                ->withFrom("another-person@example.com"),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "to: nobody@example.com\n" .
            "subject: Nothing\n" .
            "cc: someone@example.com\n" .
            "bcc: someone-else@example.com\n" .
            "from: another-person@example.com\n" .
            "mime-version: 1.0\n",
        ];

        yield "plain-text-part" => [
                new Part(""),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n",
        ];

        yield "plain-text-part-with-extra-header" => [
            (new Part(""))
                ->withHeader("x-bead-header", "bead-value"),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "x-bead-header: bead-value\n",
        ];
    }

    /**
     * Ensure headers() respects the non-RFC822-compliant line ending if requested.
     *
     * @dataProvider dataForTestHeaders2
     * @param MessageContract|PartContract $source The source of the headers.
     * @param string $expectedHeaders The expected MIME header block.
     */
    public function testHeaders2(MessageContract|PartContract $source, string $expectedHeaders): void
    {
        self::assertEquals($expectedHeaders, $this->builder->withLfLineEnd()->headers($source));
    }


    public static function dataForTestBody1(): iterable
    {
        yield "plain-text-message" => [
            new Message("nobody@example.com", "Nothing", "Plain text content."),
            "Plain text content."
        ];

        // NOTE Part always provides its headers in the order they were added.
        yield "multipart-message" => [
            (new Message("nobody@example.com", "Nothing"))
                ->withContentType("multipart/mixed", ["boundary" => "bead-multipart-boundary",])
                ->withPart(new Part("The first part."))
                ->withPart(new Part("The second part.")),
            "\r\n" .
            "--bead-multipart-boundary\r\n" .
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "\r\n" .
            "The first part.\r\n" .
            "\r\n" .
            "--bead-multipart-boundary\r\n" .
            "content-type: text/plain\r\n" .
            "content-transfer-encoding: quoted-printable\r\n" .
            "\r\n" .
            "The second part.\r\n" .
            "--bead-multipart-boundary--"
        ];
    }

    /**
     * Ensure body() provides the expected MIME message body.
     *
     * @dataProvider dataForTestBody1
     * @param MessageContract $message The message to build.
     * @param string $expectedBody The expected output from the body() method.
     */
    public function testBody1(MessageContract $message, string $expectedBody): void
    {
        self::assertEquals($expectedBody, $this->builder->body($message));
    }

    public static function dataForTestBody2(): iterable
    {
        yield "plain-text-message" => [
            new Message("nobody@example.com", "Nothing", "Plain text content."),
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "to: nobody@example.com\n" .
            "subject: Nothing\n" .
            "mime-version: 1.0\n" .
            "\n" .
            "Plain text content."
        ];

        // NOTE Part always provides its headers in the order they are added
        yield "multipart-message" => [
            (new Message("nobody@example.com", "Nothing"))
                ->withContentType("multipart/mixed", ["boundary" => "bead-multipart-boundary",])
                ->withPart(new Part("The first part."))
                ->withPart(new Part("The second part.")),
            "\n" .
            "--bead-multipart-boundary\n" .
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "\n" .
            "The first part.\n" .
            "\n" .
            "--bead-multipart-boundary\n" .
            "content-type: text/plain\n" .
            "content-transfer-encoding: quoted-printable\n" .
            "\n" .
            "The second part.\n" .
            "--bead-multipart-boundary--"
        ];
    }

    /**
     * Ensure body() respects the non-RFC822-conformant line ending if requested.
     *
     * @dataProvider dataForTestBody2
     * @param MessageContract $message The message to build.
     * @param string $expectedBody The expected output from the body() method.
     */
    public function testBody2(MessageContract $message, string $expectedBody): void
    {
        self::assertEquals($expectedBody, $this->builder->withLfLineEnd()->body($message));
    }

    /** Ensure body() throws a MimeException if duplicate part boundaries are found in a multipart message. */
    public function testBody3(): void
    {
        $message = (new Message("nobody@example.com"))
            ->withContentType("multipart/mixed", ["boundary" => "bead-multipart-boundary",])
            ->withPart(
                (new Part(
                    new Part("")
                ))
                ->withContentType("multipart/mixed", ["boundary" => "bead-multipart-boundary"])
            )
            ->withPart(new Part("Plain text content."));

        self::expectException(MimeException::class);
        self::expectExceptionMessage("Message contains duplicate part boundary \"bead-multipart-boundary\"");
        $this->builder->body($message);
    }

    /** Ensure body() throws if the Message has no content-type header. */
    public function testBody4(): void
    {
        $message = (new Message())->withoutHeader("content-type");
        self::expectException(MimeException::class);
        self::expectExceptionMessage("The message or part has no content-type header.");
        $this->builder->body($message);
    }

    /** Ensure body() throws if the Message has no content-transfer-encoding header. */
    public function testBody5(): void
    {
        $message = (new Message())->withoutHeader("content-transfer-encoding");
        self::expectException(MimeException::class);
        self::expectExceptionMessage("The message or part has no content-transfer-encoding header.");
        $this->builder->body($message);
    }

    /** Ensure body() throws if the Message has multiple parts but does not have a multipart/* content-type. */
    public function testBody6(): void
    {
        $message = (new Message())
            ->withHeader("content-type", "text/plain")
            ->withPart(new Part("Plain text part 1."))
            ->withPart(new Part("Plain text part 2."));
        self::expectException(MimeException::class);
        self::expectExceptionMessage("The message or part has multiple parts but does not have a \"multipart/\" content type.");
        $this->builder->body($message);
    }

    /** Ensure body() throws if the Message has is multipart but has no boundary set. */
    public function testBody7(): void
    {
        $message = (new Message())
            ->withHeader("content-type", "multipart/mixed")
            ->withPart(new Part("Plain text part 1."))
            ->withPart(new Part("Plain text part 2."));
        self::expectException(MimeException::class);
        self::expectExceptionMessage("The message or part has multiple parts but no boundary defined in the content-type header.");
        $this->builder->body($message);
    }

    /** Ensure body() throws if the Message has no recipients. */
    public function testBody8(): void
    {
        self::expectException(MimeException::class);
        self::expectExceptionMessage("The message has no recipients.");
        $this->builder->body(new Message());
    }

    /** Ensure body() throws if a Message has no body. */
    public function testBody9(): void
    {
        self::expectException(MimeException::class);
        self::expectExceptionMessage("Message or part has no parts or body.");
        $this->builder->body(new Message("somebody@example.com"));
    }

    /** Ensure body() throws if the Part has no content-type header. */
    public function testBody10(): void
    {
        $message = (new Part(""))->withoutHeader("content-type");
        self::expectException(MimeException::class);
        self::expectExceptionMessage("The message or part has no content-type header.");
        $this->builder->body($message);
    }

    /** Ensure body() throws if the Part has no content-transfer-encoding header. */
    public function testBody11(): void
    {
        $message = (new Part(""))->withoutHeader("content-transfer-encoding");
        self::expectException(MimeException::class);
        self::expectExceptionMessage("The message or part has no content-transfer-encoding header.");
        $this->builder->body($message);
    }

    /** Ensure body() throws if the Part has multiple parts but does not have a multipart/* content-type. */
    public function testBody12(): void
    {
        $message = (new Part())
            ->withHeader("content-type", "text/plain")
            ->withPart(new Part("Plain text part 1."))
            ->withPart(new Part("Plain text part 2."));
        self::expectException(MimeException::class);
        self::expectExceptionMessage("The message or part has multiple parts but does not have a \"multipart/\" content type.");
        $this->builder->body($message);
    }

    /** Ensure body() throws if the Part has is multipart but has no boundary set. */
    public function testBody13(): void
    {
        $message = (new Part())
            ->withHeader("content-type", "multipart/mixed")
            ->withPart(new Part("Plain text part 1."))
            ->withPart(new Part("Plain text part 2."));
        self::expectException(MimeException::class);
        self::expectExceptionMessage("The message or part has multiple parts but no boundary defined in the content-type header.");
        $this->builder->body($message);
    }

    /** Ensure body() throws if a Part has no body. */
    public function testBody14(): void
    {
        self::expectException(MimeException::class);
        self::expectExceptionMessage("Message or part has no parts or body.");
        $this->builder->body(new Part());
    }
}
