<?php

namespace BeadTests\Logging;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Logging\StandardErrorLogger;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;

class StandardErrorLoggerTest extends TestCase
{
    /** @var StandardErrorLogger The instance under test. */
    private StandardErrorLogger $logger;

    public function setUp(): void
    {
        $this->logger = new StandardErrorLogger();
    }

    public function tearDown(): void
    {
        unset($this->logger);
        parent::tearDown();
    }

    /** Ensure stream() returns the expected stream. */
    public function testStream(): void
    {
        $logger = new XRay($this->logger);
        self::assertSame(STDERR, $logger->stream());
    }
}
