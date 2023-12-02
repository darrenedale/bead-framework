<?php

namespace Bead\Exceptions\Http;

use Bead\Web\Request;
use Throwable;

/**
 * Exception thrown when a request fails CSRF validation.
 */
class CsrfTokenVerificationException extends HttpException
{
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
        parent::__construct($request, $message, $code, $previous);
    }

    /**
     * The HTTP status code.
     *
     * @return int 400 (Bad request).
     */
    public function statusCode(): int
    {
        return 400;
    }
}