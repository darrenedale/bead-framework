<?php

namespace Bead\Exceptions\Session;

use Throwable;

/**
 * Exception thrown when the configured session handler cannot be used.
 */
class InvalidSessionHandlerException extends SessionException
{
    /** @var string The handler that can't be used. */
    private string $m_handler;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $handler The handler that can't be used.
     * @param string $message The option
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $handler, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_handler = $handler;
    }

    /**
     * Fetch the handler that could not be used.
     *
     * @return string The handler.
     */
    public function getHandler(): string
    {
        return $this->m_handler;
    }
}