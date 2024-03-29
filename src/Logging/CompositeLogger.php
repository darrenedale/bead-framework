<?php

namespace Bead\Logging;

use ArrayAccess;
use Bead\Contracts\Logger as LoggerContract;
use Bead\Exceptions\Logging\LoggerException;
use Countable;
use Iterator;
use LogicException;
use OutOfBoundsException;
use Psr\Log\AbstractLogger as PsrAbstractLogger;
use Stringable;
use Throwable;

use function Bead\Helpers\Iterable\all;
use function Bead\Helpers\Str\build;

/**
 * Composite logger to write to several logs at once.
 *
 * @template-implements Iterator<int,LoggerContract>
 * @template-implements ArrayAccess<int,LoggerContract>
 */
class CompositeLogger extends PsrAbstractLogger implements LoggerContract, Iterator, Countable, ArrayAccess
{
    use HasLogLevel;
    use ConvertsPsr3LogLevels;

    /** @var array The loggers contained in the composite logger. */
    private array $loggers = [];

    /** @var int Tracks the $loggers array for the Iterator interface. */
    private int $loggerIndex = 0;

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
    public function log($level, string | Stringable $message, array $context = []): void
    {
        $level = self::convertLogLevel($level);

        if ($level > $this->level()) {
            return;
        }

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

    // ArrayAccess interface
    public function offsetExists(mixed $offset): bool
    {
        return is_int($offset) && 0 <= $offset && $this->count() > $offset;
    }

    public function offsetGet(mixed $offset): LoggerContract
    {
        assert(is_int($offset), new LogicException("CompositLogger offsets must be integers."));
        assert(0 <= $offset && $this->count() > $offset, new OutOfBoundsException("Logger {$offset} not found in CompositeLogger."));
        return $this->loggers[$offset]["logger"];
    }

    /** @throws LogicException always - CompositeLogger instances are read-only arrays. */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException("CompositeLoggers are read-only data structures.");
    }

    /** @throws LogicException always - CompositeLogger instances are read-only arrays. */
    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException("CompositeLoggers are read-only data structures.");
    }

    // Iterator interface
    public function key(): ?int
    {
        return $this->valid() ? $this->loggerIndex : null;
    }

    public function current(): LoggerContract
    {
        assert($this->valid(), new LogicException("Iteration reached invalid index {$this->loggerIndex}."));
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
