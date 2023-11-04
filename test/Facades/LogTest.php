<?php

namespace BeadTests\Facades;

use Bead\Application;
use Bead\Contracts\Logger as LoggerContract;
use Bead\Facades\Log;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use Stringable;

final class LogTest extends TestCase
{
    private const TestMessage = "Test message: %1";

    private const TestContext = ["test-context",];

    /** @var Application&MockInterface The test Application instance. */
    private Application $app;

    /** @var LoggerContract&MockInterface The test logger instance bound into the application. */
    private LoggerContract $logger;

    public function setUp(): void
    {
        $this->logger = Mockery::mock(LoggerContract::class);
        $this->app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $this->app);
        $this->app->shouldReceive("get")
            ->with(LoggerContract::class)
            ->andReturn($this->logger);
    }

    public function tearDown(): void
    {
        unset($this->app, $this->logger);
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper to create a Stringable instance for testing.
     *
     * @param string $string The string the instance should return.
     *
     * @return Stringable The mock instance.
     */
    private static function createStringable(string $string = self::TestMessage): \Stringable
    {
        return new class ($string) implements Stringable
        {
            private string $string;

            public function __construct(string $string)
            {
                $this->string = $string;
            }

            public function __toString(): string
            {
                return $this->string;
            }
        };
    }

    /** Ensure the log level can be set using the facade. */
    public function testLevel(): void
    {
        $this->logger->shouldReceive("level")
            ->once()
            ->andReturn(LoggerContract::NoticeLevel);

        self::assertEquals(LoggerContract::NoticeLevel, Log::level());
    }

    /** Ensure the log level can be retrieved using the facade. */
    public function testSetLevel(): void
    {
        $this->logger->shouldReceive("setLevel")
            ->once()
            ->with(LoggerContract::NoticeLevel);

        Log::setLevel(LoggerContract::NoticeLevel);
        self::markTestAsExternallyVerified();
    }

    /** Ensure an emergency log message can be written using the facade. */
    public function testEmergencyWithString(): void
    {
        $this->logger->shouldReceive("emergency")
            ->once()
            ->with(self::TestMessage, self::TestContext);

        Log::emergency(self::TestMessage, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure an emergency log message can be written using the facade with a Stringable object. */
    public function testEmergencyWithStringable(): void
    {
        $stringable = self::createStringable();
        $this->logger->shouldReceive("emergency")
            ->once()
            ->with($stringable, self::TestContext);

        Log::emergency($stringable, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure an alert log message can be written using the facade. */
    public function testAlertWithString(): void
    {
        $this->logger->shouldReceive("alert")
            ->once()
            ->with(self::TestMessage, self::TestContext);

        Log::Alert(self::TestMessage, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure an alert log message can be written using the facade with a Stringable object. */
    public function testAlertWithStringable(): void
    {
        $stringable = self::createStringable();
        $this->logger->shouldReceive("alert")
            ->once()
            ->with($stringable, self::TestContext);

        Log::Alert($stringable, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure a critical log message can be written using the facade. */
    public function testCriticalWithString(): void
    {
        $this->logger->shouldReceive("critical")
            ->once()
            ->with(self::TestMessage, self::TestContext);

        Log::Critical(self::TestMessage, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure a critical log message can be written using the facade with a Stringable object. */
    public function testCriticalWithStringable(): void
    {
        $stringable = self::createStringable();
        $this->logger->shouldReceive("critical")
            ->once()
            ->with($stringable, self::TestContext);

        Log::Critical($stringable, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure an error log message can be written using the facade. */
    public function testErrorWithString(): void
    {
        $this->logger->shouldReceive("error")
            ->once()
            ->with(self::TestMessage, self::TestContext);

        Log::Error(self::TestMessage, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure an error log message can be written using the facade with a Stringable object. */
    public function testErrorWithStringable(): void
    {
        $stringable = self::createStringable();
        $this->logger->shouldReceive("error")
            ->once()
            ->with($stringable, self::TestContext);

        Log::Error($stringable, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure a warning log message can be written using the facade. */
    public function testWarningWithString(): void
    {
        $this->logger->shouldReceive("warning")
            ->once()
            ->with(self::TestMessage, self::TestContext);

        Log::Warning(self::TestMessage, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure a warning log message can be written using the facade with a Stringable object. */
    public function testWarningWithStringable(): void
    {
        $stringable = self::createStringable();
        $this->logger->shouldReceive("warning")
            ->once()
            ->with($stringable, self::TestContext);

        Log::Warning($stringable, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure a notice log message can be written using the facade. */
    public function testNoticeWithString(): void
    {
        $this->logger->shouldReceive("notice")
            ->once()
            ->with(self::TestMessage, self::TestContext);

        Log::Notice(self::TestMessage, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure a notice log message can be written using the facade with a Stringable object. */
    public function testNoticeWithStringable(): void
    {
        $stringable = self::createStringable();
        $this->logger->shouldReceive("notice")
            ->once()
            ->with($stringable, self::TestContext);

        Log::Notice($stringable, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure an info log message can be written using the facade. */
    public function testInfoWithString(): void
    {
        $this->logger->shouldReceive("info")
            ->once()
            ->with(self::TestMessage, self::TestContext);

        Log::Info(self::TestMessage, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure an info log message can be written using the facade with a Stringable object. */
    public function testInfoWithStringable(): void
    {
        $stringable = self::createStringable();
        $this->logger->shouldReceive("info")
            ->once()
            ->with($stringable, self::TestContext);

        Log::Info($stringable, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure a debug log message can be written using the facade. */
    public function testDebugWithString(): void
    {
        $this->logger->shouldReceive("debug")
            ->once()
            ->with(self::TestMessage, self::TestContext);

        Log::debug(self::TestMessage, self::TestContext);
        self::markTestAsExternallyVerified();
    }

    /** Ensure a debug log message can be written using the facade with a Stringable object. */
    public function testDebugWithStringable(): void
    {
        $stringable = self::createStringable();
        $this->logger->shouldReceive("debug")
            ->once()
            ->with($stringable, self::TestContext);

        Log::debug($stringable, self::TestContext);
        self::markTestAsExternallyVerified();
    }
}
