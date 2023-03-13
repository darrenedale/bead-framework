<?php

namespace Bead\Logging;

use Bead\Contracts\Logger;
use Bead\Contracts\Logger as LoggerContract;
use Bead\Exceptions\Logging\FileLoggerException;
use Bead\Exceptions\Logging\LoggerClosedException;
use RuntimeException;
use SplFileInfo;
use SplFileObject;

class FileLogger implements LoggerContract
{
    use HasLogLevel;

    /** @var int Flag indicating the log file should be opened immediately. */
    public const FlagOpen = 0x01;

    /** @var int Flag indicating the log file should be overwritten if it already exists. */
    public const FlagOverwrite = 0x02;

    /** @var int Flag indicating the log file should be appended to if it already exists. */
    public const FlagAppend = 0x04;

    /** @var int The flags with which the logger was created. */
    private int $flags;

    /** @var string The name of the log file. */
    private string $fileName;

    /** @var SplFileObject|null The open log file (when the logger is open). */
    private ?SplFileObject $file = null;

    /**
     * Initialise a new file logger.
     *
     * @param string $fileName The name of the file to log to.
     * @param int $flags Flags indicating how the logger should behave.
     */
    public function __construct(string $fileName, int $flags = 0x00)
    {
        $this->flags = $flags;
        $this->fileName = $fileName;

        if ($flags & self::FlagOpen) {
            $this->open();
        }
    }

    /**
     * Fetch the name of the log file.
     *
     * @return string The filename.
     */
    public function fileName(): string
    {
        return $this->fileName;
    }

    /**
     * Open the log file for writing.
     *
     * The log file is opened according to the flags set when it was created.
     *
     * @throws FileLoggerException if the log file cannot be opened.
     */
    public function open(): void
    {
        if ($this->isOpen()) {
            return;
        }

        try {
            $this->file = (new SplFileInfo($this->fileName()))->openFile(match (true) {
                $this->flags & self::FlagAppend => "a",
                $this->flags & self::FlagOverwrite => "w",
                default => "x",
            });
        } catch (RuntimeException $err) {
            throw new FileLoggerException("Failed to open log file {$this->fileName()} for writing: {$err->getMessage()}", previous: $err);
        }
    }

    /**
     * Close the log file.
     */
    public function close(): void
    {
        if (!$this->isOpen()) {
            return;
        }

        $this->file->fflush();
        unset ($this->file);
    }

    /**
     * Check whether the log file is open.
     *
     * @return bool true if it is open, false otherwise.
     */
    public function isOpen(): bool
    {
        return isset($this->file);
    }

    /**
     * Write a message to the log file.
     *
     * @param string $message The message to write.
     * @param int $level The level at which to write the message.
     *
     * @throws LoggerClosedException if the level of the message requires a write but the logger is not open.
     */
    public function write(string $message, int $level = self::InformationLevel): void
    {
        if ($level < $this->level()) {
            return;
        }
        if (!$this->isOpen()) {
            throw new LoggerClosedException("The log file {$this->fileName} is not open.");
        }

        if ($level >= $this->level()) {
            $this->file->fwrite("{$message}\n");
        }
    }
}
