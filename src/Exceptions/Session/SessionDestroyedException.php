<?php

namespace Bead\Exceptions\Session;

use Throwable;

/**
 * Exception thrown when an attempt is made to use a session after it has been destroyed.
 */
class SessionDestroyedException extends SessionException
{
    /** @var string The ID of the session that has been destroyed. */
    private string $m_id;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $id The ID of the session that has been destroyed.
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
     * Fetch the ID of the destroyed session.
     * @return string The ID.
     */
    public function getId(): string
    {
        return $this->m_id;
    }
}
