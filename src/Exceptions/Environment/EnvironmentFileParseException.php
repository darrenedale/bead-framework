<?php

declare(strict_types=1);

namespace Bead\Exceptions\Environment;

use Bead\Exceptions\Throwable;
use RuntimeException;

/**
 * Exceptions throw by the File environment provider.
 */
class EnvironmentFileParseException extends RuntimeException
{
    /** @var string The name of the environment file that triggered the exception. */
    private string $fileName;

    /** @var int The line in the environment file that triggered the exception, if available.*/
    private int $envLine;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $fileName The environment file being parsed.
     * @param int $lineNumber The line number that could not be parsed.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previous exception. Defaults to null.
     */
    public function __construct(string $fileName, int $lineNumber, string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->fileName = $fileName;
        $this->envLine = $lineNumber;
    }

    /** The name of the file that could not be parsed. */
    public function getEnvironmentFileName(): string
    {
        return $this->fileName;
    }

    /** The line number in the file that could not be parsed. */
    public function getEnvironmentFileLineNumber(): int
    {
        return $this->envLine;
    }
}
