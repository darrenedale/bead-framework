<?php

declare(strict_types=1);

namespace BeadTests\Web\RequestProcessors;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Contracts\Response;
use Bead\Core\Application;
use Bead\Testing\XRay;
use Bead\Web\Request;
use Bead\Web\RequestProcessors\LogRequestDuration;
use BeadTests\Framework\TestCase;
use Mockery;

class LogRequestDurationTest extends TestCase
{
    private LogRequestDuration $processor;

    public function setUp(): void
    {
        $this->processor = new LogRequestDuration();
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->processor);
        parent::tearDown();
    }

    /** Ensure we're using the correct d.p.  */
    public function testDecimalPlaces1(): void
    {
        $processor = new XRay($this->processor);
        self::assertEquals(5, $processor->decimalPlaces());
    }

    /** Ensure we're using the expected log level. */
    public function testLogLevel1(): void
    {
        $processor = new XRay($this->processor);
        self::assertEquals(LoggerContract::InformationLevel, $processor->logLevel());
    }

    /** Ensure preprocessRequest() stores the correct start time. */
    public function testPreprocessRequest1(): void
    {
        $this->mockFunction("hrtime", 1023498675);
        $processor = new XRay($this->processor);
        $actual = $this->processor->preprocessRequest(Mockery::mock(Request::class));
        self::assertNull($actual);
        self::assertEquals(1023498675, $processor->m_started);
    }

    /** Ensure postprocessRequest() logs the correct message. */
    public function testPostprocessRequest1(): void
    {
        $this->mockFunction("hrtime", 10234759086);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive("url")->once()->andReturn("/bead/framework");
        $request->shouldReceive("remoteIp4")->once()->andReturn("172.16.1.1");

        $log = Mockery::mock(LoggerContract::class);

        $log->shouldReceive("log")
            ->with(LoggerContract::InformationLevel, "Request /bead/framework from 172.16.1.1 took 99999ns (0.00010s)")
            ->once();

        $app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $app);

        $app->shouldReceive("get")
            ->with(LoggerContract::class)
            ->once()
            ->andReturn($log);

        $processor = new XRay($this->processor);
        $processor->m_started = 10234659087;

        $actual = $this->processor->postprocessRequest($request, Mockery::mock(Response::class));
        self::assertNull($actual);
    }
}
