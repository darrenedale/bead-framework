<?php

namespace BeadTests\Logging;

use Bead\Exceptions\Logging\LoggerException;
use Bead\Logging\LogsToStream;
use Bead\Contracts\Logger as LoggerContract;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use Psr\Log\AbstractLogger as PsrAbstractLogger;

class LogsToStreamTest extends TestCase
{
    /** @var string The test error message. */
    const TestMessage = "Test message: %1";

    /** @var string[] The test error context. */
    const TestContext = ["test-context"];

    /** @var LogsToStream */
    private mixed $instance;

    public function setUp(): void
    {
        $this->instance = new class extends PsrAbstractLogger implements LoggerContract
        {
            use LogsToStream;

            private mixed $stream;

            public function __construct()
            {
                $this->stream = fopen("php://memory", "w");
            }

            protected function stream(): mixed
            {
                return $this->stream;
            }
        };
    }

    public function tearDown(): void
    {
        unset($this->instance);
        parent::tearDown();
    }

    /** Ensure log() builds and writes a message to the stream. */
    public function testLog()
    {
        $actual = "";
        $expectedStream = (new XRay($this->instance))->stream();

        self::mockFunction(
            "fwrite",
            function($stream, string $data) use (&$actual, $expectedStream): void
            {
                TestCase::assertSame($expectedStream, $stream);
                $actual = $data;
            }
        );

        $this->instance->log(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext);
        self::assertEquals(str_replace("%1", self::TestContext[0], self::TestMessage) . "\n", $actual);
    }

    /** Ensure log() ignores messages above the set level. */
    public function testLogIgnores(): void
    {
        $called = false;

        self::mockFunction(
            "fwrite",
            function($stream, string $data): void
            {
                $called = true;
                TestCase::fail("fwrite() should not be called.");
            }
        );

        $this->instance->setLevel(LoggerContract::InformationLevel);
        $this->instance->log(LoggerContract::DebugLevel, self::TestMessage, self::TestContext);
        TestCase::assertFalse($called);
    }

    /** Ensure log() throws with an invalid stream. */
    public function testLogThrows(): void
    {
        $instance = new class extends PsrAbstractLogger implements LoggerContract
        {
            use LogsToStream;

            protected function stream(): mixed
            {
                return null;
            }
        };

        self::expectException(LoggerException::class);
        self::expectExceptionMessage("The log stream is not valid.");
        $instance->log(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext);
    }
}
