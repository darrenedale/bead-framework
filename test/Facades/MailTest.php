<?php

namespace BeadTests\Facades;

use Bead\Contracts\Email\Transport as TransportContract;
use Bead\Core\Application;
use Bead\Email\Message;
use Bead\Facades\Mail;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

final class MailTest extends TestCase
{
    /** @var Application&MockInterface The test Application instance. */
    private Application $app;

    /** @var TransportContract&MockInterface The test transport instance bound into the application. */
    private TransportContract $transport;

    public function setUp(): void
    {
        $this->transport = Mockery::mock(TransportContract::class);
        $this->app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $this->app);

        $this->app->shouldReceive("get")
            ->with(TransportContract::class)
            ->andReturn($this->transport);
    }

    public function tearDown(): void
    {
        unset($this->app, $this->transport);
        Mockery::close();
        parent::tearDown();
    }

    /** Ensure a message can be sent using the facade. */
    public function testSend1(): void
    {
        $message = new Message();

        $this->transport->shouldReceive("send")
            ->once()
            ->with($message);

        Mail::send($message);
        self::markTestAsExternallyVerified();
    }
}
