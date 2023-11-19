<?php

namespace Bead\Exceptions;

use Bead\Request;
use Exception;
use Throwable;

/**
 * Exception thrown when a request fails CSRF validation.
 */
class CsrfTokenVerificationException extends Exception
{
    private Request $m_request;

    /**
     * Initialise a new instance of the exception.
     *
     * @param Request $request The request that failed CSRF validation.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional Throwable that was previously thrown. Defaults to null.
     */
    public function __construct(Request $request, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_request = $request;
    }

    /**
     * Fetch the request that failed CSRF validation.
     *
     * @return Request The request.
     */
    public function getRequest(): Request
    {
        return $this->m_request;
    }
}
