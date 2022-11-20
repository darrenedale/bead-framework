<?php

namespace Bead\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when an attempt is made to initialise a session with an ID that does not exist.
 */
class SessionNotFoundException extends SessionException
{
    /** @var string The ID of the session that was not found. */
    private string $m_id;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $id The ID of the session that could not be found.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional Throwable that was previously thrown. Defaults to null.
     */
    public function __construct(string $id, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_id = $id;
    }

    /**
     * Fetch the ID of the missing session.
     * @return string The ID.
     */
    public function getId(): string
    {
        return $this->m_id;
    }
}
