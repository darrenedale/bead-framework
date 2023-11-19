<?php

namespace BeadTests\Logging;

use Bead\Logging\StandardOutputLogger;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;

class StandardOutputLoggerTest extends TestCase
{
    /** @var StandardOutputLogger The instance under test. */
    private StandardOutputLogger $logger;

    public function setUp(): void
    {
        $this->logger = new StandardOutputLogger();
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
        self::assertSame(STDOUT, $logger->stream());
    }
}
