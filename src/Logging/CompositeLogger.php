<?php

namespace Bead\Logging;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Exceptions\Logging\LoggerClosedException;
use Bead\Exceptions\Logging\LoggerException;
use Countable;
use Iterator;
use Throwable;

use function Bead\Helpers\Iterable\all;

/**
 * Composite logger to write to several logs at once.
 */
class CompositeLogger implements LoggerContract, Iterator, Countable
{
    use HasLogLevel {
        setLevel as traitSetLevel;
    }

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

    /** Open all the contained loggers. */
    public function open(): void
    {
        foreach ($this->loggers as $logger) {
            try {
                $logger["logger"]->open();
            } catch (Throwable $err) {
                if (true === $logger["required"]) {
                    throw new LoggerException("The logger of type " . get_class($logger["logger"]) . " could not be opened: {$err->getMessage()}", previous: $err);
                }
            }
        }
    }

    /** Close all the contained loggers. */
    public function close(): void
    {
        foreach ($this->loggers as $logger) {
            $logger["logger"]->close();
        }
    }

    /**
     * Check whether the contained loggers are open.
     *
     * All the contained loggers that are marked as required must be open; if any is not open, false is returned.
     *
     * @return bool true if all the required loggers are open, false otherwise.
     */
    public function isOpen(): bool
    {
        return all($this->loggers, fn(array $logger) => !$logger["required"] || $logger["logger"]->isOpen());
    }

    /**
     * Set the current logging level on all contained loggers.
     *
     * @param int $level The new logging level.
     */
    public function setLevel(int $level): void
    {
        foreach ($this->loggers as $logger) {
            $logger["logger"]->setLevel($level);
        }

        $this->traitSetLevel($level);
    }

    /**
     * Write a message to the logs.
     *
     * If the level of the message is greater than or equal to the current log level it is written; otherwise it is
     * ignored.
     *
     * @param string $message The message to write.
     * @param int $level The level at which to write the message.
     *
     * @throws LoggerClosedException if the level of the message requires a write to one or more required loggers but
     * the logger is not open.
     */
    public function write(string $message, int $level = self::InformationLevel): void
    {
        if ($level < $this->level()) {
            return;
        }

        foreach ($this->loggers as $logger) {
            try {
                $logger["logger"]->write($message, $level);
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
        assert ($this->valid(), new \LogicException("Iteration reached invalid index {$this->loggerIndex}."));
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
