<?php

namespace Bead\Logging;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Exceptions\Logging\FileLoggerException;
use Bead\Exceptions\Logging\LoggerException;
use Psr\Log\AbstractLogger as PsrAbstractLogger;
use RuntimeException;
use SplFileInfo;
use SplFileObject;
use Stringable;
use Throwable;

use function Bead\Helpers\Str\build;

/** Log messages to a named file. */
class FileLogger extends PsrAbstractLogger implements LoggerContract
{
    use HasLogLevel;
    use ConvertsPsr3LogLevels;

    /** @var int Flag indicating the log file should be overwritten if it already exists. */
    public const FlagOverwrite = 0x01;

    /** @var int Flag indicating the log file should be appended to if it already exists. */
    public const FlagAppend = 0x02;

    /** @var string The name of the log file. */
    private string $fileName;

    /** @var SplFileObject|null The open log file. */
    private ?SplFileObject $file;

    /**
     * Initialise a new file logger.
     *
     * @param string $fileName The name of the file to log to.
     * @param int $flags Flags indicating how the logger should treat the file.
     *
     * @throws FileLoggerException if the file can't be opened for writing.
     */
    public function __construct(string $fileName, int $flags = 0x00)
    {
        $this->fileName = $fileName;

        try {
            $this->file = (new SplFileInfo($this->fileName()))->openFile(match (true) {
                (bool) ($flags & self::FlagAppend) => "a",
                (bool) ($flags & self::FlagOverwrite) => "w",
                default => "x",
            });
        } catch (RuntimeException $err) {
            throw new FileLoggerException("Failed to open log file {$this->fileName()} for writing: {$err->getMessage()}", previous: $err);
        }
    }

    /** Flush the log write buffer to the file on destruction. */
    public function __destruct()
    {
        try {
            $this->file?->fflush();
        } catch (Throwable) {
            // do nothing - we've tried to flush the log file, but if it hasn't worked there's not much else we can do
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
     * Write a message to the log file.
     *
     * @param int|string|Stringable $level The level at which to write the message.
     * @param string|Stringable $message The message to write.
     * @param array $context The message context, if any.
     *
     * @@throws LoggerException if the provided level can't be converted from a PSR3 string-like Loglevel to a Bead log
     * level.
     */
    public function log($level, string | Stringable $message, array $context = []): void
    {
        $level = self::convertLogLevel($level);

        if ($level > $this->level()) {
            return;
        }

        $message = build($message, ...$context);
        $this->file->fwrite("{$message}\n");
    }
}
