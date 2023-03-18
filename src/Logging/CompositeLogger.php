<?php

namespace Bead\Logging;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Exceptions\Logging\LoggerException;
use Countable;
use Iterator;
use LogicException;
use Psr\Log\AbstractLogger as PsrAbstractLogger;
use Throwable;

use function Bead\Helpers\Iterable\all;
use function Bead\Helpers\Str\build;

/**
 * Composite logger to write to several logs at once.
 */
class CompositeLogger extends PsrAbstractLogger implements LoggerContract, Iterator, Countable
{
    use HasLogLevel;
    use ConvertsPsr3LogLevels;

    /** @var array The loggers contained in the composite logger. */
    private array $loggers = [];

    /** @var int Tracks the $loggers array for the Iterator interface. */
    private int $loggerIndex;

    /**
     * Add a logger to the composite logger.
     *
     * You can specify whether a logger is required. If any required logger cannot be opened or written to, the logger
     * throws; otherwise, failure to open/write to the logger is silently ignored. The default is to add a required
     * logger.
     *
     * @param LoggerContract $logger The logger to add.
     * @param bool $required Whether this logger is required to be functional.
     */
    public function addLogger(LoggerContract $logger, bool $required = true): void
    {
        $this->loggers[] = compact("logger", "required");
    }

    /**
     * Write a message to the logs.
     *
     * @param int|string|Stringable $level The log level.
     * @param string|Stringable $message The message to write.
     * @param array $context The message context, if any.
     *
     * @@throws LoggerException if one of the required composite loggers throws when logging the message, or if the
     * provided level can't be converted from a PSR3 string-like Loglevel to a Bead log level.
     */
    public function log(int | string | Stringable $level, string | Stringable $message, array $context = []): void
    {
        $level = self::convertLogLevel($level);

        if ($level > $this->level()) {
            return;
        }

        $message = build($message, ...$context);

        foreach ($this->loggers as $logger) {
            try {
                $logger["logger"]->log($level, $message, $context);
            } catch (Throwable $err) {
                if (true === $logger["required"]) {
                    throw new LoggerException("Failed to write to logger of type " . get_class($logger["logger"]) . ": {$err->getMessage()}", previous: $err);
                }
            }
        }
    }

    // Countable interface
    public function count(): int
    {
        return count($this->loggers);
    }

    // Iterator interface
    public function key(): ?int
    {
        return $this->valid() ? $this->loggerIndex : null;
    }

    public function current(): LoggerContract
    {
        assert ($this->valid(), new LogicException("Iteration reached invalid index {$this->loggerIndex}."));
        return $this->loggers[$this->loggerIndex]["logger"];
    }

    public function rewind(): void
    {
        $this->loggerIndex = 0;
    }

    public function next(): void
    {
        ++$this->loggerIndex;
    }

    public function valid(): bool
    {
        return 0 <= $this->loggerIndex && $this->count() > $this->loggerIndex;
    }
}
