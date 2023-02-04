<?php

namespace Bead\Exceptions\Session;

use Throwable;

/**
 * Exception thrown when a Session is instantiated that has been unused for too long.
 */
class SessionExpiredException extends SessionException
{
    private string $m_sessionId;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $sessionId The ID of the expired session that was used.
     * @param string $message The optional error message. Defaults to an empty string..
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional Throwable that was previously thrown. Defaults to null.
     */
    public function __construct(string $sessionId, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_sessionId = $sessionId;
    }

    /**
     * Fetch the ID of the session that was used after it had expired.
     *
     * @return string The session id.
     */
    public function getSessionId(): string
    {
        return $this->m_sessionId;
    }
}
