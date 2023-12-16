<?php

declare(strict_types=1);

namespace BeadTests\Email\Transport;

use Bead\Contracts\Logger;
use Bead\Core\Application;
use Bead\Email\Message;
use Bead\Email\Transport\Mailgun as MailgunTransport;
use Bead\Exceptions\Email\TransportException;
use BeadTests\Framework\TestCase;
use Mailgun\Api\Message as MessagesApi;
use Mailgun\Exception\HttpClientException;
use Mailgun\Mailgun as MailgunClient;
use Mailgun\Model\Message\SendResponse;
use Mockery;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class MailgunTest extends TestCase
{
    private const MailgunDomain = "bead.equit.dev";

    /** @var Application&MockInterface */
    private Application $app;

    /** @var Logger&MockInterface */
    private Logger $log;

    private MailgunTransport $transport;

    private MailgunClient $mailgun;

    public function setUp(): void
    {
        parent::setUp();
        $this->log = Mockery::mock(Logger::class);
        $this->app = Mockery::mock(Application::class);
        $this->mailgun = Mockery::mock(MailgunClient::class);
        $this->transport = new MailgunTransport($this->mailgun, self::MailgunDomain);

        $this->app->shouldReceive("get")
            ->with(Logger::class)
            ->andReturn($this->log)
            ->byDefault();

        $this->mockMethod(Application::class, "instance", $this->app);
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset ($this->app, $this->transport);
        parent::tearDown();
    }

    /** Ensure the constructor uses the provided client and domain. */
    public function testConstructor1(): void
    {
        $client = Mockery::mock(MailgunClient::class);
        $transport = new MailgunTransport($client, self::MailgunDomain);
        self::assertSame($client, $transport->client());
        self::assertEquals(self::MailgunDomain, $transport->domain());
    }

    /** Ensure we can fetch the client. */
    public function testClient1(): void
    {
        self::assertSame($this->mailgun, $this->transport->client());
    }

    /** Ensure we can fetch the domain. */
    public function testDomain1(): void
    {
        self::assertEquals(self::MailgunDomain, $this->transport->domain());
    }

    /** Ensure the transport reports it's not available when the Mailgun\Mailgun class does not exist. */
    public function testIsAvailable1(): void
    {
        $this->mockFunction("class_exists", function (string $class): bool {
            TestCase::assertEquals(MailgunClient::class, $class);
            return false;
        });

        self::assertFalse(MailgunTransport::isAvailable());
    }

    /** Ensure the transport reports it's available when the Mailgun\Mailgun class does exist. */
    public function testIsAvailable2(): void
    {
        $this->mockFunction("class_exists", function (string $class): bool {
            TestCase::assertEquals(MailgunClient::class, $class);
            return true;
        });

        self::assertTrue(MailgunTransport::isAvailable());
    }

    private function mockMailgunCreate(string $expectedKey, ?string $expectedEndpoint, bool & $called): MailgunClient
    {
        $client = Mockery::mock(MailgunClient::class);

        $mock = function(string $key, string $endpoint = 'THE-DEFAULT-ENDPOINT') use ($expectedKey, $expectedEndpoint, &$called, $client): MailgunClient {
            TestCase::assertEquals($expectedKey, $key);
            TestCase::assertEquals($expectedEndpoint ?? 'THE-DEFAULT-ENDPOINT', $endpoint);
            return $client;
        };

        $this->mockMethod(MailgunClient::class, "create", $mock);
        return $client;
    }

    public static function dataForTestCreate1(): iterable
    {
        yield "key-and-domain" => ["the-key", "bead.equit.dev",];
        yield "key-domain-and-endpoint" => ["the-other-key", "bead-framework.equit.dev", "https://api.eu.mailgun.net",];
    }

    /**
     * Ensure the expected transport is created.
     *
     * @dataProvider dataForTestCreate1
     */
    public function testCreate1(string $key, string $domain, ?string $endpoint = null): void
    {
        $called = false;
        $expectedClient = $this->mockMailgunCreate($key, $endpoint, $called);
        $transport = MailgunTransport::create($key, $domain, $endpoint);

        self::assertInstanceOf(MailgunTransport::class, $transport);
        self::assertEquals($domain, $transport->domain());
        self::assertSame($expectedClient, $transport->client());
    }

    /** Ensure create() throws when Mailgun isn't available. */
    public function testCreate2(): void
    {
        $this->mockFunction("class_exists", function (string $class): bool {
            TestCase::assertEquals(MailgunClient::class, $class);
            return false;
        });

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("Mailgun is not installed");
        MailgunTransport::create("the-key", "bead.equit.dev");
    }

    /** Ensure the transport attempts to send the message via Mailgun. */
    public function testSend1(): void
    {
        $response = SendResponse::create(["id" => "1", "message" => "Sent OK"]);
        $messages = Mockery::mock(MessagesApi::class);

        $this->mailgun->shouldReceive("messages")
            ->once()
            ->andReturn($messages);

        $messages->shouldReceive("sendMime")
            ->once()
            ->with(
                self::MailgunDomain,
                ["recipient@example.com"],
                "content-type: text/plain\r\ncontent-transfer-encoding: quoted-printable\r\nto: recipient@example.com\r\nsubject: Test message\r\nmime-version: 1.0\r\n\r\nTest plain text message.",
                ["from" => ""]
            )
            ->andReturn($response);

        $this->log->shouldReceive("debug")
            ->once()
            ->with("Successfully transported message with subject \"Test message\" using Mailgun: Sent OK");

        $this->transport->send(new Message("recipient@example.com", "Test message", "Test plain text message."));
        self::markTestAsExternallyVerified();
    }

    /** Ensure send() throws if Mailgun throws. */
    public function testSend2(): void
    {
        $messages = Mockery::mock(MessagesApi::class);
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive("getStatusCode")->once()->andReturn(400);
        $response->shouldReceive("getReasonPhrase")->once()->andReturn("Bad mailgun request");

        $response->shouldReceive("getBody")
            ->once()
            ->andReturn(
                new class
                {
                    public function __toString(): string
                    {
                        return "The response body";
                    }
                }
            );

        $response->shouldReceive("getHeaderLine")
            ->once()
            ->with("Content-Type")
            ->andReturn("test/plain");

        $this->mailgun->shouldReceive("messages")
            ->once()
            ->andReturn($messages);

        $messages->shouldReceive("sendMime")
            ->once()
            ->with(
                self::MailgunDomain,
                ["recipient@example.com"],
                "content-type: text/plain\r\ncontent-transfer-encoding: quoted-printable\r\nto: recipient@example.com\r\nsubject: Test message\r\nmime-version: 1.0\r\n\r\nTest plain text message.",
                ["from" => ""]
            )
            ->andThrow(new HttpClientException("Test mailgun HTTP exception", 42, $response));

        self::expectException(TransportException::class);
        self::expectExceptionMessage("Failed to transport message with subject \"Test message\" using Mailgun: \"Bad mailgun request\"");
        $this->transport->send(new Message("recipient@example.com", "Test message", "Test plain text message."));
    }
}
