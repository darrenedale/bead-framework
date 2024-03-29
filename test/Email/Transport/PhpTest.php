<?php

declare(strict_types=1);

namespace BeadTests\Email\Transport;

use Bead\Contracts\Email\Message as MessageContract;
use Bead\Email\Message;
use Bead\Email\Transport\Php as PhpTransport;
use Bead\Exceptions\Email\TransportException;
use BeadTests\Framework\TestCase;
use Throwable;

class PhpTest extends TestCase
{
    private const TestSender = "sender@example.com";

    private const TestRecipient = "recipient@example.com";

    private const TestSubject = "A sample unit test message";

    private const TestPlainTextBody = "The plain text body of a sample unit test message.";

    private const TestPlainTextHeaders = [
        "to: recipient@example.com",
        "content-type: text/plain",
        "mime-version: 1.0",
        "content-transfer-encoding: quoted-printable",
        "subject: A sample unit test message",
    ];

    private array $mockMailExpectations;

    private PhpTransport $transport;

    private MessageContract $message;

    public function mockMail(string $to, string $subject, string $message, array|string $additionalHeaders = [], string $additionalParameters = ""): bool
    {
        ["arguments" => $expectedArguments, "return" => $returnValue] = $this->popMockMailExpectation();

        // current implementation always puts to, cc, bcc in this order in $to
        self::assertEquals($expectedArguments["to"], $to);
        self::assertEquals($expectedArguments["subject"], $subject);
        self::assertEquals($expectedArguments["message"], $message);

        // we're not concerned about the order of headers
        if (is_string($additionalHeaders)) {
            $additionalHeaders = explode("\r\n", trim($additionalHeaders));
        }

        self::assertEqualsCanonicalizing($expectedArguments["additionalHeaders"], $additionalHeaders);
        self::assertEquals($expectedArguments["additionalParameters"], $additionalParameters);

        if (is_bool($returnValue)) {
            return $returnValue;
        }

        throw $returnValue;
    }

    private function popMockMailExpectation(): array
    {
        if ([] === $this->mockMailExpectations) {
            self::fail("mail() called but no expectations set.");
        }

        return array_shift($this->mockMailExpectations);
    }

    private function expectMailCall(string $to = self::TestRecipient, string $subject = self::TestSubject, string $message = self::TestPlainTextBody, array $additionalHeaders = self::TestPlainTextHeaders, string $additionalParameters = "", bool|Throwable $outcome = true): void
    {
        $this->mockMailExpectations[] = [
            "arguments" => compact("to", "subject", "message", "additionalHeaders", "additionalParameters"),
            "return" => $outcome,
        ];
    }

    public function setUp(): void
    {
        $self = $this;
        $this->mockFunction("mail", fn (mixed ... $args): bool => $self->mockMail(... $args));
        // ensures that the message gets all 'a's as the part delimiter
        $this->mockFunction("rand", fn (int $min, int $max): int => 0);
        $this->mockMailExpectations = [];
        $this->message = new Message(self::TestRecipient, self::TestSubject, self::TestPlainTextBody);
        $this->transport = new PhpTransport();
    }

    public function tearDown(): void
    {
        unset($this->message, $this->transport, $this->mockMailExpectations);
        parent::tearDown();
    }

    /** Ensure a simple message gets transported. */
    public function testSend1(): void
    {
        $this->expectMailCall();
        $this->transport->send($this->message);
    }

    /** Ensure we get a transport exception when the MIME builder throws. */
    public function testSend2(): void
    {
        self::expectException(TransportException::class);
        self::expectExceptionMessagematches("/^Unable to generate MIME for message with subject \"" . self::TestSubject . "\": /");
        $this->transport->send((new Message())->withSubject(self::TestSubject));
    }

    /** Ensure we get a transport exception when mail() returns false. */
    public function testSend3(): void
    {
        $this->expectMailCall(outcome: false);
        self::expectException(TransportException::class);
        self::expectExceptionMessage("Failed to transport message with subject \"" . self::TestSubject . "\"");
        $this->transport->send($this->message);
    }

    /** Ensure we can send to multiple recipients. */
    public function testSend4(): void
    {
        $message = $this->message->withTo("someone-else@example.com");
        $this->expectMailCall(to: self::TestRecipient . ",someone-else@example.com", additionalHeaders: [...self::TestPlainTextHeaders, "to: someone-else@example.com",]);
        $this->transport->send($message);
    }

    /** Ensure we can send to cc recipients. */
    public function testSend5(): void
    {
        $message = $this->message->withCc("someone-else@example.com");
        $this->expectMailCall(to: self::TestRecipient . ",someone-else@example.com", additionalHeaders: [...self::TestPlainTextHeaders, "cc: someone-else@example.com",]);
        $this->transport->send($message);
    }

    /** Ensure we can send to bcc recipients. */
    public function testSend6(): void
    {
        $message = $this->message->withBcc("someone-else@example.com");
        $this->expectMailCall(to: self::TestRecipient . ",someone-else@example.com", additionalHeaders: [...self::TestPlainTextHeaders, "bcc: someone-else@example.com",]);
        $this->transport->send($message);
    }

    /** Ensure we can send to cc and bcc recipient at the same time. */
    public function testSend7(): void
    {
        $message = $this->message->withCc("someone-else@example.com")->withBcc("another-person@example.com");
        $this->expectMailCall(to: self::TestRecipient . ",someone-else@example.com,another-person@example.com", additionalHeaders: [...self::TestPlainTextHeaders, "cc: someone-else@example.com", "bcc: another-person@example.com",]);
        $this->transport->send($message);
    }

    /** Ensure duplicate recipients aren't duplicated in $to but are all present in headers. */
    public function testSend8(): void
    {
        $message = $this->message
            ->withTo(self::TestRecipient)
            ->withCc([self::TestRecipient, self::TestRecipient,])
            ->withBcc([self::TestRecipient, self::TestRecipient,]);

        $this->expectMailCall(additionalHeaders: [...self::TestPlainTextHeaders, "to: " . self::TestRecipient, "cc: " . self::TestRecipient, "cc: " . self::TestRecipient, "bcc: " . self::TestRecipient, "bcc: " . self::TestRecipient,]);
        $this->transport->send($message);
    }

    /** Ensure we can set the sender. */
    public function testSend9(): void
    {
        $message = $this->message->withFrom(self::TestSender);
        $this->expectMailCall(additionalHeaders: [...self::TestPlainTextHeaders, "from: " . self::TestSender,]);
        $this->transport->send($message);
    }

    /** Ensure custom headers are sent. */
    public function testSend10(): void
    {
        $message = $this->message->withHeader("x-bead-header", "the-value");
        $this->expectMailCall(additionalHeaders: [...self::TestPlainTextHeaders, "x-bead-header: the-value",]);
        $this->transport->send($message);
    }
}
