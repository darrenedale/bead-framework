<?php

declare(strict_types=1);

namespace BeadTests\Email\Transport;

use Bead\Core\Application;
use Bead\Email\Message;
use Bead\Email\Transport\Mailgun;
use Bead\Exceptions\Email\TransportException;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;

class MailgunTest extends TestCase
{
    /** @var Application&MockInterface */
    private Application $app;

    private Mailgun $transport;

    public function setUp(): void
    {
        parent::setUp();
        $this->transport = new Mailgun();
        $this->app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $this->app);
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset ($this->app, $this->transport);
        parent::tearDown();
    }

    private function setUpMockMailgunService(ResponseInterface $response): void
    {
        $messageApi = new class($response) {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function sendMime(string $domain, array $recipients, string $mime): ResponseInterface
            {
                TestCase::assertEquals("bead-framework.org", $domain);
                TestCase::assertEquals(["recipient@example.com",], $recipients);
                TestCase::assertEquals("content-type: text/plain\r\ncontent-transfer-encoding: quoted-printable\r\nto: recipient@example.com\r\nsubject: Test message\r\nmime-version: 1.0\r\n\r\nTest plain text message.", $mime);
                return $this->response;
            }
        };

        $mailgun = new class($messageApi) {
            public function __construct(private mixed $messageApi)
            {
            }

            public function messages(): mixed
            {
                return $this->messageApi;
            }
        };

        $this->app->shouldReceive("config")
            ->with("mail.transport.mailgun.domain")
            ->andReturn("bead-framework.org");

        $this->app->shouldReceive("has")
            ->with("Mailgun\Mailgun")
            ->andReturn(true);

        $this->app->shouldReceive("get")
            ->with("Mailgun\Mailgun")
            ->andReturn($mailgun);

        $this->mockFunction("class_exists", function (string $class): bool {
            return true;
        });

        $this->mockFunction("is_a", function (mixed $object, string $class) use ($mailgun): bool {
            TestCase::assertSame($mailgun, $object);
            TestCase::assertEquals("Mailgun\Mailgun", $class);
            return true;
        });
    }

    /** Ensure the transport reports it's not available when there is no mailgun domain in the config. */
    public function testIsAvailable1(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transport.mailgun.domain")
            ->andReturn(null);

        self::assertFalse(Mailgun::isAvailable());
    }

    /** Ensure the transport reports it's not available when the mailgun domain in the config is empty. */
    public function testIsAvailable2(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transport.mailgun.domain")
            ->andReturn("");

        self::assertFalse(Mailgun::isAvailable());
    }

    /** Ensure the transport reports it's not available when the Mailgun\Mailgun class does not exist. */
    public function testIsAvailable3(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transport.mailgun.domain")
            ->andReturn("bead-framework.org");

        $this->mockFunction("class_exists", function (string $class): bool {
            TestCase::assertEquals("Mailgun\Mailgun", $class);
            return false;
        });

        self::assertFalse(Mailgun::isAvailable());
    }

    /** Ensure the transport reports it's not available when the Mailgun\Mailgun service is not bound. */
    public function testIsAvailable4(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transport.mailgun.domain")
            ->andReturn("bead-framework.org");

        $this->app->shouldReceive("has")
            ->once()
            ->with("Mailgun\Mailgun")
            ->andReturn(false);

        $this->mockFunction("class_exists", function (string $class): bool {
            TestCase::assertEquals("Mailgun\Mailgun", $class);
            return true;
        });

        self::assertFalse(Mailgun::isAvailable());
    }

    /** Ensure the transport reports it's not available when the bound Mailgun\Mailgun service is not an object. */
    public function testIsAvailable5(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transport.mailgun.domain")
            ->andReturn("bead-framework.org");

        $this->app->shouldReceive("has")
            ->once()
            ->with("Mailgun\Mailgun")
            ->andReturn(true);

        $this->app->shouldReceive("get")
            ->once()
            ->with("Mailgun\Mailgun")
            ->andReturn("a string");

        $this->mockFunction("class_exists", function (string $class): bool {
            TestCase::assertEquals("Mailgun\Mailgun", $class);
            return true;
        });

        self::assertFalse(Mailgun::isAvailable());
    }

    /** Ensure the transport reports it's not available when the bound Mailgun\Mailgun service is not a Mailgun\Mailgun instance. */
    public function testIsAvailable6(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transport.mailgun.domain")
            ->andReturn("bead-framework.org");

        $this->app->shouldReceive("has")
            ->once()
            ->with("Mailgun\Mailgun")
            ->andReturn(true);

        $this->app->shouldReceive("get")
            ->once()
            ->with("Mailgun\Mailgun")
            ->andReturn(new class {});

        $this->mockFunction("class_exists", function (string $class): bool {
            TestCase::assertEquals("Mailgun\Mailgun", $class);
            return true;
        });

        self::assertFalse(Mailgun::isAvailable());
    }

    /** Ensure the transport reports it's available when the Mailgun\Mailgun service is correctly bound. */
    public function testIsAvailable7(): void
    {
        $this->setUpMockMailgunService(Mockery::mock(ResponseInterface::class));

        $this->mockFunction("class_exists", function (string $class): bool {
            return true;
        });

        $this->mockFunction("is_a", function (mixed $object, string $class): bool {
            TestCase::assertEquals("Mailgun\Mailgun", $class);
            return true;
        });

        self::assertTrue(Mailgun::isAvailable());
    }

    /** Ensure send() throws when Mailgun\Mailgun is not available. */
    public function testSend1(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("mail.transport.mailgun.domain")
            ->andReturn(null);

        self::expectException(TransportException::class);
        self::expectExceptionMessage("Mailgun transport is not available");
        $this->transport->send(new Message("recipient@example.com", "Test message", "Test message plain text content."));
    }

    /** Ensure the transport attempts to send the message via Mailgun. */
    public function testSend2(): void
    {
        $response = Mockery::mock(ResponseInterface::class);
        $this->setUpMockMailgunService($response);
        $response->shouldReceive("getStatusCode")->andReturn(200);
        $this->transport->send(new Message("recipient@example.com", "Test message", "Test plain text message."));
    }

    /** Ensure send() throws if Mailgun receives a non-200 response. */
    public function testSend3(): void
    {
        $response = Mockery::mock(ResponseInterface::class);
        $this->setUpMockMailgunService($response);

        $response->shouldReceive("getStatusCode")->andReturn(403);
        $response->shouldReceive("getReasonPhrase")->andReturn("Bad mailgun request.");
        self::expectException(TransportException::class);
        self::expectExceptionMessage("Failed to transport message with subject \"Test message\": \"Bad mailgun request.\"");
        $this->transport->send(new Message("recipient@example.com", "Test message", "Test plain text message."));
    }
}
