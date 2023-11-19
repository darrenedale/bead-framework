<?php

namespace BeadTests\Logging;

use Bead\Contracts\Logger as loggerContract;
use Bead\Logging\HasLogLevel;
use BeadTests\Framework\TestCase;

class HasLogLevelTest extends TestCase
{
    /** @var object Anonymous class instance utilising the trait under test. */
    private object $logger;

    public function setUp(): void
    {
        $this->logger = new class
        {
            use HasLogLevel;
        };
    }

    public function tearDown(): void
    {
        unset($this->logger);
        parent::tearDown();
    }

    /** Ensure we get the expected level back. */
    public function testLevel()
    {
        self::assertEquals(loggerContract::InformationLevel, $this->logger->level());
    }

    /** Ensure a log level can be set. */
    public function testSetLevel()
    {
        $this->logger->setLevel(LoggerContract::DebugLevel);
        self::assertEquals(loggerContract::DebugLevel, $this->logger->level());
    }
}
