<?php

namespace Equit\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown by the FileSessionHandler when a session file cannot be written successfully.
 */
class SessionFileSaveException extends SessionException
{
    /** @var string The name of the invalid file. */
    private string $m_fileName;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $fileName The name of the session file that could nto be saved.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional Throwable that was previously thrown. Defaults to null.
     */
    public function __construct(string $fileName, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_fileName = $fileName;
    }

    /**
     * Fetch the name of the file that could not be written.
     * @return string The file name.
     */
    public function getFileName(): string
    {
        return $this->m_fileName;
    }
}