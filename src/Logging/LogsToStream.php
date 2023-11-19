<?php

namespace Bead\Logging;

use Bead\Exceptions\Logging\LoggerException;
use Stringable;
use TypeError;

use function Bead\Helpers\Str\build;

/**
 * Trait for logger classes that log to a (typically PHP built-in) stream.
 */
trait LogsToStream
{
    use HasLogLevel;
    use ConvertsPsr3LogLevels;

    /**
     * Constrain utilising classes to provide the stream to write to.
     */
    abstract protected function stream(): mixed;

    /**
     * Write a message to the log stream.
     *
     * @param int|string|Stringable $level The level at which to write the message.
     * @param string|Stringable $message The message to write.
     * @param array $context The message context, if any.
     *
     * @throws LoggerException if the level of the message requires a write but the stream is not valid; or if the
     * provided level can't be converted from a PSR3 string-like Loglevel to a Bead log level.
     */
    final public function log($level, string | Stringable $message, array $context = []): void
    {
        $level = self::convertLogLevel($level);

        if ($level > $this->level()) {
            return;
        }

        $stream = $this->stream();

        if (!isset($stream)) {
            throw new LoggerException("The log stream is not valid.");
        }

        assert(is_resource($stream), new TypeError(get_class($this) . "::stream() must return a stream resource."));
        $message = build($message, ...$context);
        fwrite($stream, "{$message}\n");
    }
}
