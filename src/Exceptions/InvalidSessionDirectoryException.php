<?php

namespace Equit\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown by the FileSessionHandler when the session storage directory in the config is not valid.
 */
class InvalidSessionDirectoryException extends SessionException
{
    /** @var string The invalid directory. */
    private string $m_directory;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $directory The invalid session directory.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional Throwable that was thrown previously. Defaults to `null`.
     */
    public function __construct(string $directory, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_directory = $directory;
    }

    /**
     * Fetch the invalid directory.
     *
     * @return string The directory.
     */
    public function getDirectory(): string
    {
        return $this->m_directory;
    }
}