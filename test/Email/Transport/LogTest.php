<?php

declare(strict_types=1);

namespace BeadTests\Email\Transport;

use Bead\Contracts\Logger;
use Bead\Core\Application;
use Bead\Email\Message;
use Bead\Email\Transport\Log as LogTransport;
use Bead\Exceptions\Email\TransportException;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

class LogTest extends TestCase
{
    /** @var Application&MockInterface */
    private Application $app;

    private Logger $log;

    private LogTransport $transport;

    public function setUp(): void
    {
        parent::setUp();
        $this->app = Mockery::mock(Application::class);
        $this->log = Mockery::mock(Logger::class);
        $this->app->shouldReceive("get")
            ->with(Logger::class)
            ->andReturn($this->log)
            ->byDefault();

        $this->mockMethod(Application::class, "instance", $this->app);
        $this->transport = new LogTransport();
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->app, $this->log, $this->transport);
        parent::tearDown();
    }

    /** Ensure the transport logs the MIME of the message as info messages */
    public function testSend1(): void
    {
        $this->log->shouldReceive("info")
            ->once()
            ->ordered()
            ->with("--- BEGIN MIME Email message transport ---");

        $this->log->shouldReceive("info")
            ->once()
            ->ordered()
            ->with(Mockery::on(function (string $message): bool {
                TestCase::assertEquals("content-type: text/plain\r\ncontent-transfer-encoding: quoted-printable\r\nto: recipient@example.com\r\nsubject: Test message\r\nmime-version: 1.0\r\n\r\nPlain text content.", $message);
                return true;
            }));

        $this->log->shouldReceive("info")
            ->once()
            ->ordered()
            ->with("---  END  MIME Email message transport ---");

        $this->transport->send(new Message("recipient@example.com", "Test message", "Plain text content."));
    }

    /** Ensure send() throws a TransportException when the MimeBuilder throws. */
    public function testSend2(): void
    {
        // message is multipart but has no parts
        $message = (new Message())
            ->withContentType("multipart/mixed");

        self::expectException(TransportException::class);
        self::expectExceptionMessageMatches("/^Unable to generate MIME message: /");
        $this->transport->send($message);
    }
}
