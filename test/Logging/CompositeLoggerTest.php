<?php

namespace BeadTests\Logging;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Exceptions\Logging\LoggerException;
use Bead\Logging\CompositeLogger;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use BeadTests\Framework\TestCase;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class CompositeLoggerTest extends TestCase
{
    /** @var string The test log message. */
    private const TestMessage = "Test message: %1";

    /** @var string[] The test log context. */
    private const TestContext = ["test-context"];

    /** @var CompositeLogger The logger under test. */
    private CompositeLogger $compositeLogger;

    /** @var array<LoggerContract&MockInterface> $loggers The main loggers composing the composite logger. */
    private array $loggers;

    public function setUp(): void
    {
        $this->loggers = [
            Mockery::mock(LoggerContract::class),
            Mockery::mock(LoggerContract::class),
        ];

        $this->compositeLogger = new CompositeLogger();

        foreach ($this->loggers as $logger) {
            $this->compositeLogger->addLogger($logger);
        }
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->loggers, $this->compositeLogger);
        parent::tearDown();
    }

    /** Ensure the internal iteration index yields the correct logger. */
    public function testCurrent(): void
    {
        $idx = 0;

        foreach ($this->compositeLogger as $logger) {
            self::assertSame($this->loggers[$idx], $logger);
            ++$idx;
        }
    }

    /** Ensure the internal iteration index correctly identifies when iteration is complete. */
    public function testValid(): void
    {
        self::assertTrue($this->compositeLogger->valid());

        foreach ($this->compositeLogger as $logger) {
        }

        self::assertFalse($this->compositeLogger->valid());
    }

    /** Ensure composite logger delegates to constituent loggers. */
    public function testLog(): void
    {
        foreach ($this->loggers as $logger) {
            $logger->shouldReceive("log")
                ->with(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext)
                ->once();
        }

        $this->compositeLogger->log(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext);
        self::assertTrue(true);
    }

    /** Ensure composite logger does not delegate comstituent loggers with a message outside the log level. */
    public function testLogWithLevel(): void
    {
        $this->compositeLogger->setLevel(LoggerContract::InformationLevel);

        foreach ($this->loggers as $logger) {
            $logger->shouldNotReceive("log")
                ->with(LoggerContract::DebugLevel, self::TestMessage, self::TestContext);
        }

        $this->compositeLogger->log(LoggerContract::DebugLevel, self::TestMessage, self::TestContext);
        self::assertTrue(true);
    }

    /** Ensure compositelogger throws and skips remaining loggers when a required logger throws. */
    public function testLogThrowsWhenRequiredLoggerThrows(): void
    {
        $this->loggers[0]->shouldReceive("log")
            ->with(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext)
            ->andThrow(new RuntimeException("Test exception"));

        $this->loggers[1]->shouldNotReceive("log")
            ->with(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext);

        self::expectException(LoggerException::class);
        self::expectExceptionMessageMatches("/^Failed to write to logger of type /");
        $this->compositeLogger->log(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext);
    }

    /** Ensure compositelogger throws and skips remaining loggers when a required logger throws. */
    public function testLogContinuesWhenNonRequiredLoggerThrows(): void
    {
        $optionalLogger = Mockery::mock(LoggerContract::class);
        $requiredLogger = Mockery::mock(LoggerContract::class);

        $this->compositeLogger->addLogger($optionalLogger, false);
        $this->compositeLogger->addLogger($requiredLogger);

        $this->loggers[0]->shouldReceive("log")
            ->with(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext)
            ->once();

        $this->loggers[1]->shouldReceive("log")
            ->with(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext)
            ->once();

        // optional 3rd logger throws ...
        $optionalLogger->shouldReceive("log")
            ->with(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext)
            ->once()
            ->andThrow(new RuntimeException("Test exception"));

        // ... and 4th logger is still invoked
        $requiredLogger->shouldReceive("log")
            ->with(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext)
            ->once();

        $this->compositeLogger->log(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext);
        self::assertTrue(true);
    }

    /** Ensure we receive the correct logger count. */
    public function testCount(): void
    {
        self::assertEquals(2, count($this->compositeLogger));
    }

    /** Ensure we can add a new logger. */
    public function testAddLogger(): void
    {
        self::assertCount(2, $this->compositeLogger);
        $logger = Mockery::mock(LoggerContract::class);
        $this->compositeLogger->addLogger($logger);
        self::assertCount(3, $this->compositeLogger);
        self::assertSame($logger, $this->compositeLogger[2]);
    }

    /** Ensure we receive the correct logger index while iterating. */
    public function testKey(): void
    {
        $idx = 0;

        foreach ($this->compositeLogger as $logger) {
            self::assertEquals($idx, $this->compositeLogger->key());
            ++$idx;
        }
    }

    /** Ensure we can reset the internal logger index to the first logger. */
    public function testRewind(): void
    {
        self::assertEquals(0, $this->compositeLogger->key());

        foreach ($this->compositeLogger as $logger) {
        }

        self::assertNull($this->compositeLogger->key());
        $this->compositeLogger->rewind();
        self::assertEquals(0, $this->compositeLogger->key());
    }

    /** Ensure we can advance the internal logger index. */
    public function testNext(): void
    {
        self::assertSame($this->loggers[0], $this->compositeLogger->current());
        $this->compositeLogger->next();
        self::assertSame($this->loggers[1], $this->compositeLogger->current());
    }

    /** Ensure offsetExists returns the correct value. */
    public function testOffsetExists(): void
    {
        self::assertFalse($this->compositeLogger->offsetExists(-1));
        self::assertTrue($this->compositeLogger->offsetExists(0));
        self::assertFalse($this->compositeLogger->offsetExists("0"));
        self::assertTrue($this->compositeLogger->offsetExists(1));
        self::assertFalse($this->compositeLogger->offsetExists(2));
    }

    /**
     * Test data provider for testOffsetGetThrows().
     *
     * @return iterable The test data.
     */
    public function dataForTestOffsetGetThrows(): iterable
    {
        yield [-1];
        yield [2];
    }

    /**
     * Ensure offsetGet() only accepts in-bounds keys.
     *
     * @dataProvider dataForTestOffsetGetThrows
     *
     * @param int $offset The offset to test with.
     */
    public function testOffsetGetThrows(int $offset): void
    {
        self::expectException(OutOfBoundsException::class);
        self::expectExceptionMessage("Logger {$offset} not found in CompositeLogger.");
        $ignored = $this->compositeLogger[$offset];
    }

    /** Ensure offsetGet() only accepts int keys. */
    public function testOffsetGetThrowsWithNonInt(): void
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage("CompositLogger offsets must be integers.");
        $ignored = $this->compositeLogger["0"];
    }

    /** Ensure offsetSet() throws. */
    public function testOffsetSet(): void
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage("CompositeLoggers are read-only data structures.");
        $this->compositeLogger[] = Mockery::mock(LoggerContract::class);
    }

    /** Ensure offsetUnset() throws. */
    public function testOffsetUnset(): void
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage("CompositeLoggers are read-only data structures.");
        unset($this->compositeLogger[0]);
    }
}
