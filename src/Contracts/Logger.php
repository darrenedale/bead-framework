<?php

namespace Bead\Contracts;

use Bead\Exceptions\Logging\LoggerClosedException;

/**
 * Interface for services that log messages.
 *
 * TODO log message format support
 */
interface Logger
{
    /** @var int Log level for debug messages. */
    public const DebugLevel = 0;

    /** @var int Log level for information messages. */
    public const InformationLevel = 100;

    /** @var int Log level for warning messages. */
    public const WarningLevel = 200;

    /** @var int Log level for Error messages. */
    public const ErrorLevel = 300;

    /** @var int Log level for Critical messages. */
    public const CriticalLevel = 400;

    /** Open the log for writing. */
    public function open(): void;

    /** Close the log. */
    public function close(): void;

    /**
     * Check whether the log is open for writin.
     *
     * @return bool true if the log is open, false if not.
     */
    public function isOpen(): bool;

    /**
     * Fetch the current logging level.
     *
     * @return int The level.
     */
    public function level(): int;

    /**
     * Set the current logging level.
     *
     * @param int $level The new logging level.
     */
    public function setLevel(int $level): void;

    /**
     * Write a message to the log.
     *
     * If the level of the message is greater than or equal to the current log level it is written; otherwise it is
     * ignored. All implementations are required to permit messages of a level lower than the current level to be
     * submitted and ignored without throwing an exception when the logger is not open.
     *
     * @param string $message The message to write.
     * @param int $level The level at which to write the message.
     *
     * @throws LoggerClosedException if the level of the message requires a write but the logger is not open.
     */
    public function write(string $message, int $level = self::InformationLevel): void;
}
