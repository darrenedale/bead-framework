<?php

namespace Bead\Logging;

use Bead\Contracts\Logger as LoggerContract;

/**
 * Fake logger to discard all messages.
 *
 * Instances of this class are always open - calling close() has no effect.
 */
class NullLogger implements Logger
{
    use HasLogLevel;

    /**
     * @inheritDoc
     */
    public function open(): void
    {}

    /**
     * @inheritDoc
     */
    public function close(): void
    {}

    /**
     * @inheritDoc
     */
    public function isOpen(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function write(string $message, int $level = self::InformationLevel): void
    {
    }
}