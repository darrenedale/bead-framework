<?php

namespace Bead\Logging;

use Bead\Contracts\Logger as LoggerContract;
use Psr\Log\AbstractLogger as PsrAbstractLogger;
use Stringable;

/**
 * Fake logger to discard all messages.
 */
class NullLogger extends PsrAbstractLogger implements LoggerContract
{
    use HasLogLevel;

    /**
     * "Log" a message.
     *
     * The message is simply ignored.
     *
     * @param int|string|Stringable $level The log level.
     * @param string|Stringable $message The message to ignore.
     * @param array $context The message context, if any.
     */
    public function log($level, string | Stringable $message, array $context = []): void
    {
    }
}
